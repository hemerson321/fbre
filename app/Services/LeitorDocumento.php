<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lê um documento (RG/CNH) por IA Vision e extrai os dados do passageiro.
 * Suporta Claude (Anthropic) e OpenAI. Configuração vem da empresa (iaConfig).
 */
class LeitorDocumento
{
    public const PROMPT = 'Analise esta imagem de documento brasileiro de identidade (RG ou CNH). '
        .'Extraia os campos e retorne APENAS um JSON válido, sem explicação: '
        .'{"nome":"nome completo","documento":"RG ou CPF como está no documento","nascimento":"dd/mm/aaaa",'
        .'"sexo":"M ou F","nacionalidade":"nacionalidade"}. '
        .'Se um campo não for legível, use "". Retorne SOMENTE o JSON.';

    /** @return array{ok:bool,campos?:array,erro?:string,bruto?:mixed} */
    public function extrair(string $imagem, array $cfg): array
    {
        [$mediaType, $base64] = $this->partesImagem($imagem);
        if (! $base64) return ['ok' => false, 'erro' => 'Imagem inválida.'];

        $provider = $cfg['provider'] ?? 'claude';
        $token = trim((string) ($cfg['token'] ?? ''));
        if (! $token) return ['ok' => false, 'erro' => 'Configure o token da IA em Minha empresa.'];

        try {
            $texto = $provider === 'openai'
                ? $this->openai($token, $cfg['modelo'] ?? 'gpt-4o-mini', $mediaType, $base64)
                : $this->claude($token, $cfg['modelo'] ?? 'claude-sonnet-4-20250514', $mediaType, $base64);
            if ($texto === null) return ['ok' => false, 'erro' => 'IA não respondeu.'];

            $campos = $this->parseJson($texto);
            if (! $campos) return ['ok' => false, 'erro' => 'Não consegui ler o documento.', 'bruto' => $texto];
            return ['ok' => true, 'campos' => $campos, 'bruto' => $texto];
        } catch (\Throwable $e) {
            Log::warning('LeitorDocumento: '.$e->getMessage());
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    protected function partesImagem(string $img): array
    {
        if (preg_match('/^data:(image\/\w+);base64,(.+)$/s', $img, $m)) return [$m[1], $m[2]];
        return ['image/jpeg', $img]; // assume base64 puro
    }

    protected function claude(string $token, string $modelo, string $mediaType, string $base64): ?string
    {
        $r = Http::timeout(40)->withHeaders([
            'x-api-key' => $token,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $modelo,
            'max_tokens' => 1024,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => $base64]],
                    ['type' => 'text', 'text' => self::PROMPT],
                ],
            ]],
        ]);
        if (! $r->successful()) throw new \RuntimeException('Claude HTTP '.$r->status().': '.$r->body());
        return data_get($r->json(), 'content.0.text');
    }

    protected function openai(string $token, string $modelo, string $mediaType, string $base64): ?string
    {
        $r = Http::timeout(40)->withToken($token)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $modelo,
            'max_tokens' => 1024,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => self::PROMPT],
                    ['type' => 'image_url', 'image_url' => ['url' => "data:{$mediaType};base64,{$base64}"]],
                ],
            ]],
        ]);
        if (! $r->successful()) throw new \RuntimeException('OpenAI HTTP '.$r->status().': '.$r->body());
        return data_get($r->json(), 'choices.0.message.content');
    }

    /** Extrai o primeiro objeto JSON do texto (tolera cercas de código). */
    protected function parseJson(string $texto): ?array
    {
        $t = trim($texto);
        $t = preg_replace('/^```(json)?|```$/m', '', $t);
        if (preg_match('/\{.*\}/s', $t, $m)) {
            $j = json_decode($m[0], true);
            if (is_array($j)) return $j;
        }
        return null;
    }
}
