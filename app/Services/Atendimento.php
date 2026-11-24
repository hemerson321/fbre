<?php

namespace App\Services;

use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\WhatsappConexao;
use Illuminate\Support\Carbon;

/**
 * Núcleo do canal de atendimento: registra mensagens recebidas/enviadas,
 * mantém o status da conversa e envia pelo WhatsApp. Usado pelo webhook,
 * pela caixa de atendimento (humano) e pelo agente IA.
 */
class Atendimento
{
    public function __construct(protected WhatsApp $wa) {}

    /** Mensagem recebida do cliente -> grava e atualiza a conversa. */
    public function registrarEntrada(WhatsappConexao $conexao, string $telefone, string $texto, ?string $nome = null, ?string $waId = null): ?Mensagem
    {
        // dedupe: Evolution às vezes manda o mesmo evento 2x
        if ($waId && Mensagem::where('empresa_id', $conexao->empresa_id)->where('wa_message_id', $waId)->exists()) {
            return null;
        }
        $fone = $this->wa->normalizar($telefone);

        $conversa = Conversa::firstOrNew([
            'empresa_id' => $conexao->empresa_id,
            'modulo' => 'passagem',
            'cliente_telefone' => $fone,
        ]);
        if (! $conversa->exists) {
            $conversa->status = 'aguardando';
            $conversa->funil = 'novo';
        }
        $reabrindo = $conversa->exists && $conversa->status === 'fechada';
        $conversa->whatsapp_conexao_id = $conexao->id;
        if ($nome && ! $conversa->cliente_nome) $conversa->cliente_nome = mb_substr($nome, 0, 120);
        if ($reabrindo) { $conversa->status = 'aguardando'; $conversa->funil = 'novo'; }
        $conversa->atendida = false;
        $conversa->cliente_respondeu_em = now();
        $conversa->ultima_msg_texto = mb_substr($texto, 0, 255);
        $conversa->ultima_msg_origem = 'cliente';
        $conversa->ultima_msg_em = now();
        $conversa->nao_lidas = (int) $conversa->nao_lidas + 1;
        $conversa->save();

        $msg = Mensagem::create([
            'empresa_id' => $conexao->empresa_id,
            'conversa_id' => $conversa->id,
            'direcao' => 'entrada',
            'origem' => 'cliente',
            'texto' => $texto,
            'wa_message_id' => $waId,
            'lida' => false,
        ]);

        // conversa reaberta apos fechar = atendimento novo: agente so olha daqui pra frente
        if ($reabrindo) {
            $ctx = $conversa->contexto ?? [];
            $ctx['reinicio_id'] = $msg->id;
            // atendimento novo começa limpo (trecho/data/poltrona do atendimento anterior não valem mais).
            // Exceção: PIX já pago e passagem ainda não emitida — aí mantém pra não perder a compra.
            if (! (! empty($ctx['pago']) && empty($ctx['passagem_id']))) {
                unset($ctx['venda'], $ctx['passageiros'], $ctx['poltronas_agente'], $ctx['poltrona_agente'], $ctx['pref_poltrona'], $ctx['lancha_agente'], $ctx['pago'], $ctx['passagem_id'], $ctx['ocioso_aviso_em'], $ctx['mapa_pendente']);
                try { app(\App\Services\Vagas::class)->liberarConversa($conversa->id); } catch (\Throwable $e) {}
            }
            $conversa->forceFill(['contexto' => $ctx])->save();
        }

        return $msg;
    }

    /** Mensagem enviada ao cliente (humano/ia/sistema) -> envia pelo WhatsApp e grava. */
    public function registrarSaida(Conversa $conversa, string $texto, string $origem = 'humano', ?int $userId = null, ?string $remetente = null, string $audioModo = 'texto'): array
    {
        // Nome de quem fala aparece pro cliente no WhatsApp (operador que assumiu ou o agente).
        // O texto é salvo limpo; o prefixo vai só no que sai pelo WhatsApp.
        if ($remetente === null && $origem === 'humano' && $userId) {
            $remetente = optional(\App\Models\User::find($userId))->name;
        }
        $textoWa = $remetente ? '*'.trim($remetente)."*\n".$texto : $texto;

        $conexao = $conversa->conexao;
        $querAudio = in_array($audioModo, ['audio', 'audio_texto'], true);
        $querTextoJunto = ($audioModo === 'audio_texto');
        $envioAudio = null;
        $enviadoAudio = false;

        // Responder em áudio (ex.: cliente mandou áudio). A voz lê o texto limpo, sem o nome em asterisco.
        if ($querAudio && $conexao) {
            $ag  = \App\Models\Agente::where('modulo', $conversa->modulo)->where('ativo', true)->first();
            $tok = $ag ? trim((string) ($ag->regras['audio_token'] ?? '')) : '';
            $voz = $ag ? (string) ($ag->regras['audio_voz'] ?? 'alloy') : 'alloy';
            $b64 = app(\App\Services\SinteseVoz::class)->sintetizar($texto, $voz ?: 'alloy', $tok ?: null);
            if ($b64 === '') {
                \Illuminate\Support\Facades\Log::warning('Audio: voz nao gerada (TTS vazio) na conversa '.$conversa->id.'. Confira o token OpenAI do agente (campo "Token OpenAI p/ audio").');
            } else {
                $envioAudio = $this->wa->enviarAudio($conexao, $conversa->cliente_telefone, $b64);
                $enviadoAudio = ($envioAudio['ok'] ?? false);
                if (! $enviadoAudio) {
                    \Illuminate\Support\Facades\Log::warning('Audio: falha ao ENVIAR pelo WhatsApp na conversa '.$conversa->id.': '.($envioAudio['erro'] ?? 'erro'));
                }
            }
        }

        // Texto: manda quando NÃO é só-áudio, quando é áudio+texto, ou como fallback se o áudio falhou.
        $envioTexto = null;
        if (! $querAudio || $querTextoJunto || ! $enviadoAudio) {
            $envioTexto = $conexao
                ? $this->wa->enviarTexto($conexao, $conversa->cliente_telefone, $textoWa)
                : ['ok' => false, 'erro' => 'Conversa sem conexão de WhatsApp.'];
        }

        // Resultado representativo (prioriza o texto quando houve; senão o áudio).
        $envio = $envioTexto ?? $envioAudio ?? ['ok' => false, 'erro' => 'Conversa sem conexão de WhatsApp.'];
        $envio['ok'] = (($envioTexto['ok'] ?? false) || ($envioAudio['ok'] ?? false));

        $msg = Mensagem::create([
            'empresa_id' => $conversa->empresa_id,
            'conversa_id' => $conversa->id,
            'direcao' => 'saida',
            'origem' => $origem,
            'texto' => $texto,
            'user_id' => $userId,
            'wa_message_id' => $envio['resposta']['key']['id'] ?? ($envio['resposta']['messages'][0]['id'] ?? null),
            'lida' => true,
            'meta' => $envio['ok'] ? ($enviadoAudio ? ['audio' => true] : null) : ['erro' => $envio['erro'] ?? 'falha'],
        ]);

        $conversa->ultima_msg_texto = mb_substr($texto, 0, 255);
        $conversa->ultima_msg_origem = $origem;
        $conversa->ultima_msg_em = now();
        $conversa->save();

        return ['ok' => $envio['ok'] ?? false, 'mensagem' => $msg, 'erro' => $envio['erro'] ?? null];
    }

    /** Humano assume a conversa. */
    public function assumirHumano(Conversa $conversa, int $userId): void
    {
        $conversa->status = 'humano';
        $conversa->ia_ativa = false;
        $conversa->precisa_humano = false;
        $conversa->assumida_por = $userId;
        $conversa->atendida = true;
        $conversa->nao_lidas = 0;
        $conversa->save();
    }

    /** A IA transferiu pro humano: entra na fila de atendimento com alerta/bip. */
    public function transferir(Conversa $conversa, ?string $motivo = null): void
    {
        $conversa->forceFill([
            'status' => 'aguardando',
            'ia_ativa' => false,
            'precisa_humano' => true,
            'transferida_motivo' => $motivo ? mb_substr($motivo, 0, 160) : null,
            'transferida_em' => now(),
            'atendida' => false,
        ])->save();
    }
}
