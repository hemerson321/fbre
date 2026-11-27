<?php

namespace App\Services;

use App\Models\LlmProvedor;
use Illuminate\Support\Facades\Http;

/**
 * Chat de texto genérico sobre Claude (Anthropic), OpenAI, Gemini, DeepSeek e GLM (Z.ai).
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
        $provider = in_array($p->provider, ['openai', 'gemini', 'deepseek', 'glm'], true) ? $p->provider : 'claude';
        $base = rtrim(trim((string) ($p->base_url ?? '')), '/'); // só DeepSeek/GLM usam (endereço alternativo da API)

        $candidatos = array_values(array_filter(array_unique(array_merge(
            [trim((string) $p->modelo)],
            // DeepSeek/GLM: com modelo informado não gasta chamada extra descobrindo; sem modelo usa o padrão barato
            in_array($provider, ['deepseek', 'glm'], true) ? self::PADRAO[$provider] : $this->descobrir($provider, $token)
        ))));
        if (! $candidatos) return ['ok' => false, 'erro' => 'Nenhum modelo disponível na conta.'];

        $ultErro = 'falha';
        foreach ($candidatos as $modelo) {
            $r = match ($provider) {
                'openai' => $this->openai($token, $modelo, $mensagens, $sistema, $maxTokens),
                'gemini' => $this->gemini($token, $modelo, $mensagens, $sistema, $maxTokens),
                'deepseek', 'glm' => $this->compativel($provider, $base, $token, $modelo, $mensagens, $sistema, $maxTokens),
                default  => $this->claude($token, $modelo, $mensagens, $sistema, $maxTokens),
            };
            if ($r['ok']) return ['ok' => true, 'texto' => $r['texto'], 'modelo' => $modelo, 'provider' => $provider, 'uso' => $r['uso'] ?? null];
            $ultErro = 'HTTP '.$r['status'].' ('.$modelo.'): '.mb_substr((string) ($r['body'] ?? ($r['erro'] ?? '')), 0, 200);
            $invalido = $r['status'] === 404 || str_contains(mb_strtolower((string) ($r['body'] ?? '')), 'model');
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
        // consumo de tokens (input_tokens NÃO inclui o que veio do cache)
        $uso = [
            'in' => (int) $r->json('usage.input_tokens', 0),
            'out' => (int) $r->json('usage.output_tokens', 0),
            'cache_r' => (int) $r->json('usage.cache_read_input_tokens', 0),
            'cache_w' => (int) $r->json('usage.cache_creation_input_tokens', 0),
        ];
        return ['ok' => true, 'texto' => trim($txt), 'uso' => $uso];
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
        // consumo de tokens (prompt_tokens INCLUI os tokens em cache -> separa)
        $cache = (int) $r->json('usage.prompt_tokens_details.cached_tokens', 0);
        $uso = [
            'in' => max(0, (int) $r->json('usage.prompt_tokens', 0) - $cache),
            'out' => (int) $r->json('usage.completion_tokens', 0),
            'cache_r' => $cache,
            'cache_w' => 0,
        ];
        return ['ok' => true, 'texto' => trim((string) $r->json('choices.0.message.content')), 'uso' => $uso];
    }

    /** Modelo padrão (barato) quando o cadastro do provedor não informa um. */
    public const PADRAO = [
        'deepseek' => ['deepseek-flash'],
        'glm' => ['glm-4.7', 'glm-4.6'],
    ];

    /**
     * DeepSeek e GLM (Z.ai): API no formato da OpenAI.
     * - O "pensamento" (thinking) vem LIGADO por padrão nos dois e é cobrado como saída: aqui vai desligado,
     *   porque atendimento é resposta curta — fica bem mais barato e rápido. Caso difícil sobe pro modelo de
     *   escalonamento do agente.
     * - Cache de prompt é automático nos dois (o system grande e igual é cobrado a uma fração nas próximas mensagens).
     */
    protected function compativel(string $provider, string $base, string $token, string $modelo, array $msgs, string $sistema, int $max): array
    {
        $url = ($base !== '' ? $base : ($provider === 'deepseek' ? 'https://api.deepseek.com' : 'https://api.z.ai/api/paas/v4')).'/chat/completions';
        $mensagens = [];
        if ($sistema !== '') $mensagens[] = ['role' => 'system', 'content' => $sistema];
        foreach ($msgs as $m) {
            $mensagens[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => (string) $m['content']];
        }
        $body = ['model' => $modelo, 'messages' => $mensagens, 'max_tokens' => $max, 'stream' => false];
        // GLM-5.3 tem pensamento obrigatório (não aceita desligar): dá folga no limite pra resposta não vir cortada
        $forcado = $provider === 'glm' && str_contains(mb_strtolower($modelo), 'glm-5.3');
        if ($forcado) $body['max_tokens'] = $max + 3000;
        else $body['thinking'] = ['type' => 'disabled'];

        $r = Http::timeout(60)->withToken($token)->post($url, $body);
        if (! $r->successful()) return ['ok' => false, 'status' => $r->status(), 'body' => $r->body()];

        $txt = trim((string) $r->json('choices.0.message.content'));
        $fim = (string) $r->json('choices.0.finish_reason', '');
        // cortou no limite sem texto: falha de propósito pra cascata escalar pro modelo melhor
        if ($txt === '') return ['ok' => false, 'status' => $fim === 'length' ? 'MAX_TOKENS' : 'VAZIO', 'body' => 'resposta vazia ('.$fim.')'];

        // prompt_tokens INCLUI o que veio do cache -> separa (o cache custa uma fração)
        $cache = (int) ($r->json('usage.prompt_tokens_details.cached_tokens') ?? $r->json('usage.prompt_cache_hit_tokens') ?? 0);
        $uso = [
            'in' => max(0, (int) $r->json('usage.prompt_tokens', 0) - $cache),
            'out' => (int) $r->json('usage.completion_tokens', 0),
            'cache_r' => $cache,
            'cache_w' => 0,
        ];
        return ['ok' => true, 'texto' => $txt, 'fim' => $fim, 'uso' => $uso];
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
        // thinkingBudget limita o 'pensamento' do Gemini 2.5 pra NÃO consumir todo o maxOutputTokens
        // (era isso que truncava a resposta no meio do marcador, ex.: '[[POLTRONA: ...').
        $body = ['contents' => $contents, 'generationConfig' => [
            'maxOutputTokens' => $max,
            'thinkingConfig' => ['thinkingBudget' => 512],
        ]];
        if ($sistema !== '') $body['systemInstruction'] = ['parts' => [['text' => $sistema]]];

        $r = Http::timeout(40)->withHeaders(['x-goog-api-key' => $token])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent", $body);

        if (! $r->successful()) return ['ok' => false, 'status' => $r->status(), 'body' => $r->body()];
        $txt = '';
        foreach ((array) $r->json('candidates.0.content.parts', []) as $parte) {
            $txt .= $parte['text'] ?? '';
        }
        $fim = (string) $r->json('candidates.0.finishReason', '');
        // Resposta cortada pelo limite SEM texto visível: falha de propósito pra cascata escalar (ex.: Claude).
        if (trim($txt) === '' && $fim === 'MAX_TOKENS') {
            return ['ok' => false, 'status' => 'MAX_TOKENS', 'erro' => 'gemini truncou a resposta (thinking)'];
        }
        // consumo de tokens ('pensamento' do Gemini é cobrado como saída; promptTokenCount inclui o cache)
        $cache = (int) $r->json('usageMetadata.cachedContentTokenCount', 0);
        $uso = [
            'in' => max(0, (int) $r->json('usageMetadata.promptTokenCount', 0) - $cache),
            'out' => (int) $r->json('usageMetadata.candidatesTokenCount', 0) + (int) $r->json('usageMetadata.thoughtsTokenCount', 0),
            'cache_r' => $cache,
            'cache_w' => 0,
        ];
        return ['ok' => true, 'texto' => trim($txt), 'fim' => $fim, 'uso' => $uso];
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
