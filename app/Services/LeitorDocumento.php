<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lê um documento (RG/CNH) por IA Vision e extrai os dados do passageiro.
 * Suporta Claude (Anthropic) e OpenAI. Se o modelo escolhido não existir (404),
 * tenta automaticamente modelos garantidos em seguida.
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

    protected const CLAUDE_FALLBACK = ['claude-3-5-sonnet-20241022', 'claude-3-5-haiku-20241022', 'claude-3-haiku-20240307'];
    protected const OPENAI_FALLBACK = ['gpt-4o-mini', 'gpt-4o'];

    /** @return array{ok:bool,campos?:array,erro?:string,modelo?:string,bruto?:mixed} */
    public function extrair(string $imagem, array $cfg): array
    {
        [$mediaType, $base64] = $this->partesImagem($imagem);
        if (! $base64) return ['ok' => false, 'erro' => 'Imagem inválida.'];

        $provider = ($cfg['provider'] ?? 'claude') === 'openai' ? 'openai' : 'claude';
        $token = trim((string) ($cfg['token'] ?? ''));
        if (! $token) return ['ok' => false, 'erro' => 'Configure o token da IA em Minha empresa.'];

        $modelos = array_values(array_unique(array_filter(array_merge(
            [trim((string) ($cfg['modelo'] ?? ''))],
            $provider === 'openai' ? self::OPENAI_FALLBACK : self::CLAUDE_FALLBACK
        ))));

        $ultErro = null;
        foreach ($modelos as $modelo) {
            try {
                $r = $provider === 'openai'
                    ? $this->openai($token, $modelo, $mediaType, $base64)
                    : $this->claude($token, $modelo, $mediaType, $base64);
            } catch (\Throwable $e) {
                $ultErro = $e->getMessage();
                break; // erro de conexão
            }

            if ($r['ok']) {
                $campos = $this->parseJson((string) $r['text']);
                if ($campos) return ['ok' => true, 'campos' => $campos, 'modelo' => $modelo, 'bruto' => $r['text']];
                return ['ok' => false, 'erro' => 'Não consegui ler o documento.', 'modelo' => $modelo, 'bruto' => $r['text']];
            }

            $ultErro = 'HTTP '.$r['status'].' ('.$modelo.')';
            // modelo inexistente -> tenta o próximo; outros erros (auth, cota) -> para
            $modeloInvalido = $r['status'] === 404 || str_contains(mb_strtolower($r['body']), 'not_found') || str_contains(mb_strtolower($r['body']), 'model');
            if (! $modeloInvalido) { $ultErro .= ': '.$r['body']; break; }
        }

        Log::warning('LeitorDocumento falhou: '.$ultErro);
        return ['ok' => false, 'erro' => $ultErro ?: 'IA não respondeu.'];
    }

    protected function partesImagem(string $img): array
    {
        if (preg_match('/^data:(image\/\w+);base64,(.+)$/s', $img, $m)) return [$m[1], $m[2]];
        return ['image/jpeg', $img];
    }

    /** @return array{ok:bool,text:?string,status:int,body:string} */
    protected function claude(string $token, string $modelo, string $mediaType, string $base64): array
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
        return ['ok' => $r->successful(), 'text' => data_get($r->json(), 'content.0.text'), 'status' => $r->status(), 'body' => $r->body()];
    }

    /** @return array{ok:bool,text:?string,status:int,body:string} */
    protected function openai(string $token, string $modelo, string $mediaType, string $base64): array
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
        return ['ok' => $r->successful(), 'text' => data_get($r->json(), 'choices.0.message.content'), 'status' => $r->status(), 'body' => $r->body()];
    }

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
