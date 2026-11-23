<?php

namespace App\Services;

use App\Models\LlmProvedor;
use Illuminate\Support\Facades\Http;

/**
 * Chat de texto genérico sobre Claude (Anthropic) e OpenAI.
 * $mensagens = [['role'=>'user'|'assistant','content'=>'...'], ...].
 * À prova de modelo descontinuado: descobre modelos válidos na conta se o escolhido falhar.
 */
class LLM
{
    /** @return array{ok:bool,texto?:string,modelo?:string,erro?:string} */
    public function chat(LlmProvedor $p, array $mensagens, string $sistema = '', int $maxTokens = 700): array
    {
        $token = (string) $p->token;
        if ($token === '') return ['ok' => false, 'erro' => 'Provedor sem token.'];
        $provider = in_array($p->provider, ['openai', 'gemini'], true) ? $p->provider : 'claude';

        $candidatos = array_values(array_filter(array_unique(array_merge(
            [trim((string) $p->modelo)],
            $this->descobrir($provider, $token)
        ))));
        if (! $candidatos) return ['ok' => false, 'erro' => 'Nenhum modelo disponível na conta.'];

        $ultErro = 'falha';
        foreach ($candidatos as $modelo) {
            $r = match ($provider) {
                'openai' => $this->openai($token, $modelo, $mensagens, $sistema, $maxTokens),
                'gemini' => $this->gemini($token, $modelo, $mensagens, $sistema, $maxTokens),
                default  => $this->claude($token, $modelo, $mensagens, $sistema, $maxTokens),
            };
            if ($r['ok']) return ['ok' => true, 'texto' => $r['texto'], 'modelo' => $modelo];
            $ultErro = 'HTTP '.$r['status'].' ('.$modelo.'): '.mb_substr($r['body'], 0, 200);
            $invalido = $r['status'] === 404 || str_contains(mb_strtolower($r['body']), 'model');
            if (! $invalido) break; // auth/cota: para
        }
        return ['ok' => false, 'erro' => $ultErro];
    }

    protected function claude(string $token, string $modelo, array $msgs, string $sistema, int $max): array
    {
        $body = ['model' => $modelo, 'max_tokens' => $max, 'messages' => array_map(fn ($m) => [
            'role' => $m['role'] === 'assistant' ? 'assistant' : 'user',
            'content' => (string) $m['content'],
        ], $msgs)];
        // Prompt caching: o system (grande e estável) é enviado 1x e reusado nas próximas
        // mensagens da mesma conversa a ~10% do custo. Corta MUITO o consumo de API.
        if ($sistema !== '') {
            $body['system'] = [[
                'type' => 'text',
                'text' => $sistema,
                'cache_control' => ['type' => 'ephemeral'],
            ]];
        }

        $r = Http::timeout(40)->withHeaders([
            'x-api-key' => $token,
            'anthropic-version' => '2023-06-01',
            'anthropic-beta' => 'prompt-caching-2024-07-31',
            'Content-Type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', $body);

        if (! $r->successful()) return ['ok' => false, 'status' => $r->status(), 'body' => $r->body()];
        $txt = '';
        foreach ((array) $r->json('content') as $bloco) {
            if (($bloco['type'] ?? '') === 'text') $txt .= $bloco['text'] ?? '';
        }
        return ['ok' => true, 'texto' => trim($txt)];
    }

    protected function openai(string $token, string $modelo, array $msgs, string $sistema, int $max): array
    {
        $mensagens = [];
        if ($sistema !== '') $mensagens[] = ['role' => 'system', 'content' => $sistema];
        foreach ($msgs as $m) {
            $mensagens[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => (string) $m['content']];
        }
        $r = Http::timeout(40)->withToken($token)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $modelo, 'max_tokens' => $max, 'messages' => $mensagens,
        ]);
        if (! $r->successful()) return ['ok' => false, 'status' => $r->status(), 'body' => $r->body()];
        return ['ok' => true, 'texto' => trim((string) $r->json('choices.0.message.content'))];
    }

    protected function gemini(string $token, string $modelo, array $msgs, string $sistema, int $max): array
    {
        $contents = [];
        foreach ($msgs as $m) {
            $contents[] = [
                'role' => $m['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => (string) $m['content']]],
            ];
        }
        $body = ['contents' => $contents, 'generationConfig' => ['maxOutputTokens' => $max]];
        if ($sistema !== '') $body['systemInstruction'] = ['parts' => [['text' => $sistema]]];

        $r = Http::timeout(40)->withHeaders(['x-goog-api-key' => $token])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent", $body);

        if (! $r->successful()) return ['ok' => false, 'status' => $r->status(), 'body' => $r->body()];
        $txt = '';
        foreach ((array) $r->json('candidates.0.content.parts', []) as $parte) {
            $txt .= $parte['text'] ?? '';
        }
        return ['ok' => true, 'texto' => trim($txt)];
    }

    /** Modelos de texto disponíveis na conta (fallback). */
    protected function descobrir(string $provider, string $token): array
    {
        try {
            if ($provider === 'openai') {
                $r = Http::timeout(15)->withToken($token)->get('https://api.openai.com/v1/models');
                if (! $r->successful()) return [];
                $ids = collect($r->json('data', []))->pluck('id')
                    ->filter(fn ($id) => str_contains($id, 'gpt'))->values()->all();
                $pref = array_values(array_filter($ids, fn ($id) => str_contains($id, 'mini')));
                return array_slice(array_merge($pref, $ids), 0, 4);
            }
            if ($provider === 'gemini') {
                $r = Http::timeout(15)->withHeaders(['x-goog-api-key' => $token])
                    ->get('https://generativelanguage.googleapis.com/v1beta/models');
                if (! $r->successful()) return [];
                $ids = collect($r->json('models', []))->pluck('name')
                    ->map(fn ($n) => str_replace('models/', '', (string) $n))
                    ->filter(fn ($id) => str_contains($id, 'gemini'))->values()->all();
                $flashNorm = array_values(array_filter($ids, fn ($id) => str_contains($id, 'flash') && ! str_contains($id, 'lite')));
                $flash = array_values(array_filter($ids, fn ($id) => str_contains($id, 'flash')));
                return array_slice(array_merge($flashNorm, $flash, $ids), 0, 4);
            }
            $r = Http::timeout(15)->withHeaders(['x-api-key' => $token, 'anthropic-version' => '2023-06-01'])
                ->get('https://api.anthropic.com/v1/models?limit=100');
            if (! $r->successful()) return [];
            $ids = collect($r->json('data', []))->pluck('id')->values()->all();
            $pref = array_values(array_filter($ids, fn ($id) => str_contains($id, 'haiku')));
            $mid = array_values(array_filter($ids, fn ($id) => str_contains($id, 'sonnet')));
            return array_slice(array_merge($pref, $mid, $ids), 0, 4);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Valida credenciais tentando uma resposta curta. */
    public function validar(LlmProvedor $p): array
    {
        $r = $this->chat($p, [['role' => 'user', 'content' => 'Responda apenas: OK']], '', 10);
        return $r['ok'] ? ['ok' => true, 'modelo' => $r['modelo'] ?? ''] : ['ok' => false, 'erro' => $r['erro'] ?? 'falha'];
    }
}
