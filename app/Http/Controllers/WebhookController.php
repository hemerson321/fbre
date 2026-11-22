<?php

namespace App\Http\Controllers;

use App\Models\WhatsappConexao;
use App\Services\Atendimento;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Recebe eventos de WhatsApp (público, sem login).
 * URL por conexão: /wa/webhook/{id}. Suporta Evolution (não oficial) e Meta Cloud (oficial).
 */
class WebhookController extends Controller
{
    /** Verificação do webhook da Meta (GET hub.challenge). */
    public function verificar(Request $request, int $conexao)
    {
        if ($request->query('hub_mode') === 'subscribe' || $request->query('hub.mode') === 'subscribe') {
            return response($request->query('hub_challenge', $request->query('hub.challenge', 'ok')), 200);
        }
        return response('ok', 200);
    }

    public function receber(Request $request, int $conexao, Atendimento $at, \App\Services\WhatsApp $wa)
    {
        $c = WhatsappConexao::withoutGlobalScopes()->find($conexao);
        if (! $c) {
            return response()->json(['ok' => false], 200); // responde 200 p/ não gerar retry infinito
        }
        Tenant::set($c->empresa_id);
        try { Cache::put('wa_hit_'.$c->id, ['at' => now()->toDateTimeString(), 'raw' => mb_substr($request->getContent(), 0, 600)], now()->addDay()); } catch (\Throwable $e) {}

        try {
            $msgs = $c->tipo === 'oficial'
                ? $this->extrairMeta($request->all())
                : $this->extrairEvolution($request->all());

            foreach ($msgs as $m) {
                if (($m['telefone'] ?? '') === '') continue;
                $tipo     = $m['tipo'] ?? 'texto';
                $ehImagem = $tipo === 'imagem';
                $ehAudio  = $tipo === 'audio';
                $ehDoc    = $tipo === 'documento';
                $mime     = (string) ($m['mime'] ?? '');
                $texto = (string) ($m['texto'] ?? '');
                if ($texto === '' && ! $ehImagem && ! $ehAudio && ! $ehDoc) continue;
                if ($texto === '') {
                    $texto = $ehAudio ? '🎤 (áudio)' : ($ehImagem ? '📷 (foto enviada)' : '📎 (documento enviado)');
                }

                $msg = $at->registrarEntrada($c, $m['telefone'], $texto, $m['nome'] ?? null, $m['wa_id'] ?? null);
                if (! $msg || ! $msg->conversa) continue;

                // mídias (só Evolution/não-oficial baixa base64)
                if (($ehImagem || $ehAudio || $ehDoc) && $c->tipo === 'nao_oficial') {
                    $mid = $wa->evoBaixarMidia($c, $m['key'] ?? []);
                    if (($mid['ok'] ?? false)) {
                        $b64   = $mid['base64'];
                        $mmime = (string) ($mid['mime'] ?? $mime);
                        $agNome = optional(\App\Models\Agente::where('modulo', $msg->conversa->modulo)->where('ativo', true)->first())->nome;

                        // ÁUDIO -> transcreve e processa como se fosse texto do cliente
                        if ($ehAudio) {
                            $txt = trim(app(\App\Services\TranscritorAudio::class)->transcrever($b64, $mmime ?: 'audio/ogg'));
                            if ($txt !== '') {
                                $msg->update(['texto' => '🎤 '.$txt]);
                                $msg->conversa->forceFill(['ultima_msg_texto' => mb_substr('🎤 '.$txt, 0, 255)])->save();
                                app(\App\Services\AgenteBot::class)->processar($msg->conversa);
                            } else {
                                $at->registrarSaida($msg->conversa, 'Não consegui ouvir seu áudio direitinho 😕 Pode me mandar por escrito, por favor?', 'ia', null, $agNome);
                            }
                            continue;
                        }

                        // FOTO / documento enviado como imagem -> visão (valida documento)
                        if ($ehImagem || str_starts_with(mb_strtolower($mmime), 'image/')) {
                            app(\App\Services\AgenteBot::class)->processarFoto($msg->conversa, $b64, $mmime ?: 'image/jpeg');
                            continue;
                        }

                        // documento que não é imagem (ex.: PDF) -> pede como foto
                        $at->registrarSaida($msg->conversa, 'Recebi seu arquivo! Pra eu validar, me manda o documento como *foto* (imagem nítida), por favor 📸', 'ia', null, $agNome);
                        continue;
                    }
                }
                app(\App\Services\AgenteBot::class)->processar($msg->conversa);
            }
        } catch (\Throwable $e) {
            Log::warning('Webhook WA erro: '.$e->getMessage());
        }

        return response()->json(['ok' => true], 200);
    }

    /** Evolution API: evento messages.upsert. */
    protected function extrairEvolution(array $p): array
    {
        $evt = $p['event'] ?? '';
        if ($evt && ! str_contains((string) $evt, 'messages.upsert') && ! str_contains((string) $evt, 'messages')) {
            return [];
        }
        $data = $p['data'] ?? [];
        // pode vir um único objeto ou lista
        $itens = isset($data['key']) ? [$data] : (is_array($data) ? $data : []);
        $out = [];
        foreach ($itens as $d) {
            $key = $d['key'] ?? [];
            if (! empty($key['fromMe'])) continue;
            $jid = (string) ($key['remoteJid'] ?? '');
            if ($jid === '' || str_contains($jid, '@g.us')) continue; // ignora grupos
            $msg = $d['message'] ?? [];
            // alguns tipos vêm embrulhados (ex.: documentWithCaptionMessage)
            $msg = $msg['documentWithCaptionMessage']['message'] ?? $msg['viewOnceMessage']['message'] ?? $msg;
            $ehImagem = isset($msg['imageMessage']);
            $ehAudio  = isset($msg['audioMessage']);
            $ehDoc    = isset($msg['documentMessage']);
            $texto = $msg['conversation']
                ?? ($msg['extendedTextMessage']['text'] ?? null)
                ?? ($msg['imageMessage']['caption'] ?? null)
                ?? ($msg['documentMessage']['caption'] ?? null)
                ?? ($msg['buttonsResponseMessage']['selectedDisplayText'] ?? null)
                ?? ($msg['listResponseMessage']['title'] ?? null);
            if (! $texto && ! $ehImagem && ! $ehAudio && ! $ehDoc) continue;
            $tipo = $ehImagem ? 'imagem' : ($ehAudio ? 'audio' : ($ehDoc ? 'documento' : 'texto'));
            $mime = $msg['audioMessage']['mimetype']
                ?? $msg['imageMessage']['mimetype']
                ?? $msg['documentMessage']['mimetype']
                ?? null;
            $out[] = [
                'telefone' => explode('@', $jid)[0],
                'texto' => (string) ($texto ?? ''),
                'nome' => $d['pushName'] ?? null,
                'wa_id' => $key['id'] ?? null,
                'tipo' => $tipo,
                'mime' => $mime,
                'key' => $key,
            ];
        }
        return $out;
    }

    /** Meta WhatsApp Cloud API. */
    protected function extrairMeta(array $p): array
    {
        $out = [];
        foreach ($p['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $ch) {
                $val = $ch['value'] ?? [];
                $nome = $val['contacts'][0]['profile']['name'] ?? null;
                foreach ($val['messages'] ?? [] as $m) {
                    $texto = $m['text']['body']
                        ?? ($m['button']['text'] ?? null)
                        ?? ($m['interactive']['button_reply']['title'] ?? null)
                        ?? ($m['interactive']['list_reply']['title'] ?? null);
                    if (! $texto) continue;
                    $out[] = [
                        'telefone' => (string) ($m['from'] ?? ''),
                        'texto' => (string) $texto,
                        'nome' => $nome,
                        'wa_id' => $m['id'] ?? null,
                    ];
                }
            }
        }
        return $out;
    }
}
