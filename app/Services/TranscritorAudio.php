<?php

namespace App\Services;

use App\Models\LlmProvedor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Transcreve áudios (mensagens de voz do WhatsApp) para texto.
 * Usa a API de transcrição da OpenAI (Whisper) — Claude não transcreve áudio.
 * Procura qualquer provedor OpenAI ativo cadastrado em "Provedores de IA".
 */
class TranscritorAudio
{
    /** Token de um provedor OpenAI ativo (prefere o passado, se for OpenAI). */
    protected function tokenOpenAI(?string $pref = null): ?string
    {
        $pref = trim((string) $pref);
        if ($pref !== '') return $pref; // token colado direto no agente
        $p = LlmProvedor::where('provider', 'openai')->where('ativo', true)
            ->whereNotNull('token')->orderByDesc('id')->first();
        $tok = $p ? trim((string) $p->token) : '';
        return $tok !== '' ? $tok : null;
    }

    /** Transcreve o áudio (base64). $tokenPref = token OpenAI do agente (preferido). Retorna o texto, ou '' se não der. */
    public function transcrever(string $base64, string $mime = 'audio/ogg', ?string $tokenPref = null): string
    {
        $token = $this->tokenOpenAI($tokenPref);
        if (! $token) { Log::warning('TranscritorAudio: nenhum provedor OpenAI ativo para transcrever áudio.'); return ''; }

        $bin = base64_decode($base64, true);
        if ($bin === false || $bin === '') return '';

        $arquivo = 'audio.'.$this->ext($mime);

        foreach (['whisper-1', 'gpt-4o-mini-transcribe'] as $modelo) {
            try {
                $r = Http::timeout(90)->withToken($token)
                    ->attach('file', $bin, $arquivo)
                    ->post('https://api.openai.com/v1/audio/transcriptions', [
                        'model' => $modelo,
                        'language' => 'pt',
                        'response_format' => 'text',
                    ]);
                if ($r->successful()) {
                    $t = trim((string) $r->body());
                    if ($t !== '') return mb_substr($t, 0, 2000);
                }
                // modelo inexistente nessa conta -> tenta o próximo; outro erro -> para
                $body = mb_strtolower((string) $r->body());
                if ($r->status() !== 404 && ! str_contains($body, 'model')) {
                    Log::warning('Transcrição falhou HTTP '.$r->status().': '.mb_substr((string) $r->body(), 0, 200));
                    break;
                }
            } catch (\Throwable $e) {
                Log::warning('Transcrição erro: '.$e->getMessage());
                break;
            }
        }
        return '';
    }

    /** Extensão do arquivo a partir do mime (Whisper aceita ogg/mp3/m4a/wav/webm...). */
    protected function ext(string $mime): string
    {
        $mime = mb_strtolower($mime);
        return match (true) {
            str_contains($mime, 'ogg')                             => 'ogg',
            str_contains($mime, 'mpeg'), str_contains($mime, 'mp3') => 'mp3',
            str_contains($mime, 'mp4'), str_contains($mime, 'm4a')  => 'm4a',
            str_contains($mime, 'wav')                             => 'wav',
            str_contains($mime, 'webm')                            => 'webm',
            default                                                => 'ogg',
        };
    }
}
