<?php

namespace App\Services;

use App\Models\LlmProvedor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gera voz (texto -> áudio) para o agente responder em áudio no WhatsApp.
 * Usa a API de fala da OpenAI (TTS). Procura qualquer provedor OpenAI ativo.
 */
class SinteseVoz
{
    /** Último erro de síntese (pra mostrar ao operador quando a voz não gera). */
    public string $ultimoErro = '';

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
        $this->ultimoErro = '';
        $texto = trim($texto);
        if ($texto === '') { $this->ultimoErro = 'Texto vazio.'; return ''; }
        $token = $this->tokenOpenAI($tokenPref);
        if (! $token) { $this->ultimoErro = 'Nenhum provedor OpenAI ativo (configure em Provedores de IA).'; Log::warning('SinteseVoz: nenhum provedor OpenAI ativo para gerar áudio.'); return ''; }

        $texto = mb_substr($texto, 0, 1800); // limite seguro de TTS
        $instrucoes = trim($instrucoes);
        $this->ultimoErro = 'A OpenAI não retornou áudio (verifique se a chave tem acesso a TTS).';

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

    /**
     * Troca o JSON da conta de serviço do Google por um access token OAuth2 (cacheado ~55 min).
     * É o que funciona com as vozes Chirp 3 HD (a chave de API comum é recusada com 401 nelas).
     */
    public function googleAccessToken(string $saJson): ?string
    {
        $saJson = trim($saJson);
        if ($saJson === '') { $this->ultimoErro = 'Sem conta de serviço do Google.'; return null; }
        $sa = json_decode($saJson, true);
        if (! is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
            $this->ultimoErro = 'JSON da conta de serviço inválido (precisa ter client_email e private_key).';
            return null;
        }

        $cacheKey = 'gtts_oauth_'.md5((string) $sa['client_email']);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') return $cached;

        $now = time();
        $b64 = fn ($d) => rtrim(strtr(base64_encode((string) $d), '+/', '-_'), '=');
        $header = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $b64(json_encode([
            'iss' => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        $unsigned = $header.'.'.$claims;
        $sig = '';
        if (! openssl_sign($unsigned, $sig, (string) $sa['private_key'], 'SHA256')) {
            $this->ultimoErro = 'Não consegui assinar com a chave da conta de serviço (private_key inválida).';
            return null;
        }
        $jwt = $unsigned.'.'.$b64($sig);

        try {
            $r = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);
        } catch (\Throwable $e) {
            $this->ultimoErro = 'Erro ao autenticar no Google: '.$e->getMessage();
            return null;
        }
        if (! $r->successful()) {
            $this->ultimoErro = 'OAuth Google falhou: '.(string) ($r->json('error_description') ?: $r->json('error') ?: mb_substr((string) $r->body(), 0, 200));
            return null;
        }
        $token = (string) $r->json('access_token');
        if ($token === '') { $this->ultimoErro = 'O Google não devolveu access_token.'; return null; }
        $exp = (int) ($r->json('expires_in') ?: 3600);
        Cache::put($cacheKey, $token, now()->addSeconds(max(60, $exp - 300)));

        return $token;
    }

    /**
     * Google Cloud Text-to-Speech (Chirp 3 HD, WaveNet, Neural2). Retorna base64 mp3 ('' se falhar).
     * Se $saJson (conta de serviço) estiver preenchido, usa OAuth2 (funciona com TODAS as vozes, Chirp 3 HD inclusa).
     * Senão, usa a chave de API (só Standard/WaveNet/Neural2 — Chirp 3 HD recusa com 401).
     */
    public function sintetizarGoogle(string $texto, string $voz, string $apiKey, float $ritmo = 1.0, string $saJson = ''): string
    {
        $this->ultimoErro = '';
        $texto = trim($texto);
        $apiKey = trim($apiKey);
        $saJson = trim($saJson);
        if ($texto === '') { $this->ultimoErro = 'Texto vazio.'; return ''; }
        if ($saJson === '' && $apiKey === '') { $this->ultimoErro = 'Sem credenciais do Google (cole a chave da API ou o JSON da conta de serviço).'; return ''; }
        $texto = mb_substr($texto, 0, 1800);
        $voz = $voz ?: 'pt-BR-Chirp3-HD-Aoede';
        $audio = ['audioEncoding' => 'MP3'];
        if (abs($ritmo - 1.0) > 0.001) $audio['speakingRate'] = max(0.5, min(1.5, $ritmo));

        $usarSA = $saJson !== '';
        $token = null;
        if ($usarSA) {
            $token = $this->googleAccessToken($saJson);
            if (! $token) return ''; // ultimoErro já preenchido
        }

        $url = 'https://texttospeech.googleapis.com/v1/text:synthesize';
        try {
            $req = Http::timeout(60);
            if ($usarSA) {
                $req = $req->withToken($token); // Authorization: Bearer <access_token>
            } else {
                $url .= '?key='.urlencode($apiKey);
            }
            $r = $req->post($url, [
                'input' => ['text' => $texto],
                'voice' => ['languageCode' => 'pt-BR', 'name' => $voz],
                'audioConfig' => $audio,
            ]);
            if ($r->successful()) {
                $b64 = (string) $r->json('audioContent');
                if ($b64 !== '') return $b64; // o Google já devolve base64
                $this->ultimoErro = 'O Google respondeu sem áudio.';
            } else {
                $msg = (string) ($r->json('error.message') ?: mb_substr((string) $r->body(), 0, 220));
                // Dica específica do erro clássico da Chirp 3 HD com chave de API.
                if (! $usarSA && ($r->status() === 401 || str_contains(mb_strtolower($msg), 'api key'))) {
                    $msg .= ' — essa voz (Chirp 3 HD) exige conta de serviço (JSON), não chave de API. Cole o JSON nas Integrações, ou troque pra uma voz Neural2/Wavenet.';
                }
                $this->ultimoErro = 'Google HTTP '.$r->status().': '.$msg;
            }
            Log::warning('Google TTS: '.$this->ultimoErro);
        } catch (\Throwable $e) {
            $this->ultimoErro = 'Erro de conexão com o Google: '.$e->getMessage();
            Log::warning('Google TTS erro: '.$e->getMessage());
        }

        return '';
    }
}
