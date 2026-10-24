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

    public function receber(Request $request, int $conexao, Atendimento $at)
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
                if (($m['texto'] ?? '') === '' || ($m['telefone'] ?? '') === '') continue;
                $msg = $at->registrarEntrada($c, $m['telefone'], $m['texto'], $m['nome'] ?? null, $m['wa_id'] ?? null);
                if ($msg && $msg->conversa) {
                    app(\App\Services\AgenteBot::class)->processar($msg->conversa);
                }
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
            $texto = $msg['conversation']
                ?? ($msg['extendedTextMessage']['text'] ?? null)
                ?? ($msg['imageMessage']['caption'] ?? null)
                ?? ($msg['buttonsResponseMessage']['selectedDisplayText'] ?? null)
                ?? ($msg['listResponseMessage']['title'] ?? null);
            if (! $texto) continue;
            $out[] = [
                'telefone' => explode('@', $jid)[0],
                'texto' => (string) $texto,
                'nome' => $d['pushName'] ?? null,
                'wa_id' => $key['id'] ?? null,
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
