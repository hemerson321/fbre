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
    protected function tokenOpenAI(?string $pref = null): ?string
    {
        $pref = trim((string) $pref);
        if ($pref !== '') return $pref; // token colado direto no agente
        $p = LlmProvedor::where('provider', 'openai')->where('ativo', true)
            ->whereNotNull('token')->orderByDesc('id')->first();
        $tok = $p ? trim((string) $p->token) : '';
        return $tok !== '' ? $tok : null;
    }

    /** Sintetiza o texto em áudio (base64 mp3). $tokenPref = token OpenAI do agente (preferido). '' se não der. */
    public function sintetizar(string $texto, string $voz = 'alloy', ?string $tokenPref = null, string $instrucoes = ''): string
    {
        $texto = trim($texto);
        if ($texto === '') return '';
        $token = $this->tokenOpenAI($tokenPref);
        if (! $token) { Log::warning('SinteseVoz: nenhum provedor OpenAI ativo para gerar áudio.'); return ''; }

        $texto = mb_substr($texto, 0, 1800); // limite seguro de TTS
        $instrucoes = trim($instrucoes);

        foreach (['gpt-4o-mini-tts', 'tts-1'] as $modelo) {
            try {
                $payload = [
                    'model' => $modelo,
                    'voice' => $voz ?: 'alloy',
                    'input' => $texto,
                    'response_format' => 'mp3',
                ];
                // 'instructions' (tom/estilo da fala) só é suportado no gpt-4o-mini-tts
                if ($instrucoes !== '' && $modelo === 'gpt-4o-mini-tts') $payload['instructions'] = $instrucoes;
                $r = Http::timeout(60)->withToken($token)
                    ->post('https://api.openai.com/v1/audio/speech', $payload);
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

    /** Google Cloud Text-to-Speech (Chirp 3 HD, WaveNet, Neural2). Retorna base64 mp3 ('' se falhar). */
    public function sintetizarGoogle(string $texto, string $voz, string $apiKey, float $ritmo = 1.0): string
    {
        $texto = trim($texto);
        $apiKey = trim($apiKey);
        if ($texto === '' || $apiKey === '') return '';
        $texto = mb_substr($texto, 0, 1800);
        $voz = $voz ?: 'pt-BR-Chirp3-HD-Aoede';
        $audio = ['audioEncoding' => 'MP3'];
        if (abs($ritmo - 1.0) > 0.001) $audio['speakingRate'] = max(0.5, min(1.5, $ritmo));
        try {
            $r = Http::timeout(60)->post('https://texttospeech.googleapis.com/v1/text:synthesize?key='.urlencode($apiKey), [
                'input' => ['text' => $texto],
                'voice' => ['languageCode' => 'pt-BR', 'name' => $voz],
                'audioConfig' => $audio,
            ]);
            if ($r->successful()) {
                $b64 = (string) $r->json('audioContent');
                if ($b64 !== '') return $b64; // o Google já devolve base64
            }
            Log::warning('Google TTS falhou HTTP '.$r->status().': '.mb_substr((string) $r->body(), 0, 200));
        } catch (\Throwable $e) {
            Log::warning('Google TTS erro: '.$e->getMessage());
        }
        return '';
    }
}
