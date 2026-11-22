<?php

namespace App\Services;

use App\Models\LlmProvedor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gera voz (texto -> áudio) para o agente responder em áudio no WhatsApp.
 * Usa a API de fala da OpenAI (TTS). Procura qualquer provedor OpenAI ativo.
 */
class SinteseVoz
{
    protected function tokenOpenAI(?LlmProvedor $pref = null): ?string
    {
        if ($pref && $pref->provider === 'openai' && trim((string) $pref->token) !== '') {
            return trim((string) $pref->token);
        }
        $p = LlmProvedor::where('provider', 'openai')->where('ativo', true)
            ->whereNotNull('token')->orderByDesc('id')->first();
        $tok = $p ? trim((string) $p->token) : '';
        return $tok !== '' ? $tok : null;
    }

    /** Sintetiza o texto em áudio (base64 mp3). Retorna '' se não der. */
    public function sintetizar(string $texto, string $voz = 'alloy', ?LlmProvedor $pref = null): string
    {
        $texto = trim($texto);
        if ($texto === '') return '';
        $token = $this->tokenOpenAI($pref);
        if (! $token) { Log::warning('SinteseVoz: nenhum provedor OpenAI ativo para gerar áudio.'); return ''; }

        $texto = mb_substr($texto, 0, 1800); // limite seguro de TTS

        foreach (['gpt-4o-mini-tts', 'tts-1'] as $modelo) {
            try {
                $r = Http::timeout(60)->withToken($token)
                    ->post('https://api.openai.com/v1/audio/speech', [
                        'model' => $modelo,
                        'voice' => $voz ?: 'alloy',
                        'input' => $texto,
                        'response_format' => 'mp3',
                    ]);
                if ($r->successful()) {
                    $bin = (string) $r->body();
                    if ($bin !== '') return base64_encode($bin);
                }
                $body = mb_strtolower((string) $r->body());
                if ($r->status() !== 404 && ! str_contains($body, 'model')) {
                    Log::warning('TTS falhou HTTP '.$r->status().': '.mb_substr((string) $r->body(), 0, 200));
                    break;
                }
            } catch (\Throwable $e) {
                Log::warning('TTS erro: '.$e->getMessage());
                break;
            }
        }
        return '';
    }
}
