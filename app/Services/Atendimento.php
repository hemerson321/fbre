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
            $conversa->forceFill(['contexto' => $ctx])->save();
        }

        return $msg;
    }

    /** Mensagem enviada ao cliente (humano/ia/sistema) -> envia pelo WhatsApp e grava. */
    public function registrarSaida(Conversa $conversa, string $texto, string $origem = 'humano', ?int $userId = null): array
    {
        $conexao = $conversa->conexao;
        $envio = $conexao
            ? $this->wa->enviarTexto($conexao, $conversa->cliente_telefone, $texto)
            : ['ok' => false, 'erro' => 'Conversa sem conexão de WhatsApp.'];

        $msg = Mensagem::create([
            'empresa_id' => $conversa->empresa_id,
            'conversa_id' => $conversa->id,
            'direcao' => 'saida',
            'origem' => $origem,
            'texto' => $texto,
            'user_id' => $userId,
            'wa_message_id' => $envio['resposta']['key']['id'] ?? ($envio['resposta']['messages'][0]['id'] ?? null),
            'lida' => true,
            'meta' => $envio['ok'] ? null : ['erro' => $envio['erro'] ?? 'falha'],
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
        $conversa->assumida_por = $userId;
        $conversa->atendida = true;
        $conversa->nao_lidas = 0;
        $conversa->save();
    }
}
