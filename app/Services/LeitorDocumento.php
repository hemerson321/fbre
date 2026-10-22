<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lê um documento (RG/CNH) por IA Vision e extrai os dados do passageiro.
 * Se o modelo escolhido não existir (404), DESCOBRE na própria API quais
 * modelos a conta tem e usa um válido automaticamente (à prova de descontinuação).
 */
class LeitorDocumento
{
    public const PROMPT = 'Analise esta imagem de documento brasileiro de identidade (RG ou CNH). '
        .'Extraia os campos e retorne APENAS um JSON válido, sem explicação: '
        .'{"nome":"nome completo","documento":"RG ou CPF como está no documento","nascimento":"dd/mm/aaaa",'
        .'"sexo":"M ou F","nacionalidade":"nacionalidade",'
        .'"foto_box":{"x":0,"y":0,"w":0,"h":0}}. '
        .'Em foto_box informe a posição da FOTO 3x4 do titular na imagem, em PORCENTAGEM (0 a 100) relativa à largura/altura. '
        .'Se um campo não for legível, use "". Retorne SOMENTE o JSON.';

    /** @return array{ok:bool,campos?:array,erro?:string,modelo?:string,bruto?:mixed} */
    public function extrair(string $imagem, array $cfg): array
    {
        [$mediaType, $base64] = $this->partesImagem($imagem);
        if (! $base64) return ['ok' => false, 'erro' => 'Imagem inválida.'];

        $provider = ($cfg['provider'] ?? 'claude') === 'openai' ? 'openai' : 'claude';
        $token = trim((string) ($cfg['token'] ?? ''));
        if (! $token) return ['ok' => false, 'erro' => 'Configure o token da IA em Minha empresa.'];

        // 1) modelo escolhido; 2) modelos que a conta realmente tem (descobertos na API)
        $candidatos = array_filter([trim((string) ($cfg['modelo'] ?? ''))]);
        $descobertos = $this->descobrir($provider, $token);
        $candidatos = array_values(array_unique(array_merge($candidatos, $descobertos)));
        if (! $candidatos) {
            return ['ok' => false, 'erro' => 'Nenhum modelo disponível na sua conta '.($provider === 'openai' ? 'OpenAI' : 'Anthropic').'. Verifique o token/créditos.'];
        }

        $ultErro = null;
        foreach ($candidatos as $modelo) {
            try {
                $r = $provider === 'openai'
                    ? $this->openai($token, $modelo, $mediaType, $base64)
                    : $this->claude($token, $modelo, $mediaType, $base64);
            } catch (\Throwable $e) {
                $ultErro = $e->getMessage();
                break;
            }
            if ($r['ok']) {
                $campos = $this->parseJson((string) $r['text']);
                if ($campos) return ['ok' => true, 'campos' => $campos, 'modelo' => $modelo, 'bruto' => $r['text']];
                $diag = trim((string) $r['text']);
                if ($diag === '') $diag = mb_substr($r['body'], 0, 240);
                return ['ok' => false, 'erro' => 'IA respondeu mas não li JSON ('.$modelo.'): '.mb_substr($diag, 0, 240), 'modelo' => $modelo, 'bruto' => $r['text']];
            }
            $ultErro = 'HTTP '.$r['status'].' ('.$modelo.'): '.mb_substr($r['body'], 0, 240);
            $modeloInvalido = $r['status'] === 404 || str_contains(mb_strtolower($r['body']), 'not_found') || str_contains(mb_strtolower($r['body']), 'model');
            if (! $modeloInvalido) break; // auth/cota: para e reporta
        }

        Log::warning('LeitorDocumento falhou: '.$ultErro);
        return ['ok' => false, 'erro' => $ultErro ?: 'IA não respondeu.'];
    }

    /** Lista os modelos de visão disponíveis na conta (ordena do mais barato pro melhor). */
    protected function descobrir(string $provider, string $token): array
    {
        try {
            if ($provider === 'openai') {
                $r = Http::timeout(15)->withToken($token)->get('https://api.openai.com/v1/models');
                if (! $r->successful()) return [];
                $ids = collect($r->json('data') ?? [])->pluck('id')->filter()
                    ->filter(fn ($id) => preg_match('/^(gpt-4o|gpt-4\.1|gpt-5|o4)/', $id)
                        && ! preg_match('/(audio|realtime|transcribe|tts|search)/', $id))
                    ->values()->all();
                usort($ids, fn ($a, $b) => (str_contains($a, 'mini') ? 0 : 1) <=> (str_contains($b, 'mini') ? 0 : 1));
                return $ids;
            }

            $r = Http::timeout(15)->withHeaders(['x-api-key' => $token, 'anthropic-version' => '2023-06-01'])
                ->get('https://api.anthropic.com/v1/models?limit=100');
            if (! $r->successful()) return [];
            $ids = collect($r->json('data') ?? [])->pluck('id')->filter()
                ->filter(fn ($id) => str_contains($id, 'claude'))->values()->all();
            $rank = fn ($id) => str_contains($id, 'haiku') ? 0 : (str_contains($id, 'sonnet') ? 1 : 2);
            usort($ids, fn ($a, $b) => $rank($a) <=> $rank($b));
            return $ids;
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function partesImagem(string $img): array
    {
        if (preg_match('/^data:(image\/\w+);base64,(.+)$/s', $img, $m)) return [$m[1], $m[2]];
        return ['image/jpeg', $img];
    }

    protected function claude(string $token, string $modelo, string $mediaType, string $base64): array
    {
        $r = Http::timeout(40)->withHeaders([
            'x-api-key' => $token, 'anthropic-version' => '2023-06-01', 'Content-Type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $modelo, 'max_tokens' => 1024,
            'messages' => [['role' => 'user', 'content' => [
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => $base64]],
                ['type' => 'text', 'text' => self::PROMPT],
            ]]],
        ]);
        return ['ok' => $r->successful(), 'text' => $this->textoClaude($r->json()), 'status' => $r->status(), 'body' => $r->body()];
    }

    protected function openai(string $token, string $modelo, string $mediaType, string $base64): array
    {
        $r = Http::timeout(40)->withToken($token)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $modelo, 'max_tokens' => 1024,
            'messages' => [['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => self::PROMPT],
                ['type' => 'image_url', 'image_url' => ['url' => "data:{$mediaType};base64,{$base64}"]],
            ]]],
        ]);
        return ['ok' => $r->successful(), 'text' => data_get($r->json(), 'choices.0.message.content'), 'status' => $r->status(), 'body' => $r->body()];
    }

    /** Junta os blocos de texto da resposta do Claude, ignorando blocos de 'thinking'. */
    protected function textoClaude($json): ?string
    {
        $t = collect(data_get($json, 'content', []))
            ->filter(fn ($b) => ($b['type'] ?? '') === 'text')
            ->pluck('text')->filter()->implode("\n");
        return $t !== '' ? $t : null;
    }

    protected function parseJson(string $texto): ?array
    {
        $t = preg_replace('/^```(json)?|```$/m', '', trim($texto));
        if (preg_match('/\{.*\}/s', $t, $m)) {
            $j = json_decode($m[0], true);
            if (is_array($j)) return $j;
        }
        return null;
    }
}
