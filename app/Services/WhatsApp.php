<?php

namespace App\Services;

use App\Models\WhatsappConexao;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envio de WhatsApp abstraindo API oficial (Meta Cloud) e não oficial (Evolution).
 * Reutilizável por qualquer módulo: $wa->enviarTexto($conexao, $fone, $msg).
 */
class WhatsApp
{
    /** Normaliza telefone para o formato internacional só com dígitos (Brasil por padrão). */
    public function normalizar(string $telefone): string
    {
        $d = preg_replace('/\D/', '', $telefone);
        if ($d === '') return '';
        if (strlen($d) <= 11 && ! str_starts_with($d, '55')) $d = '55'.$d;
        return $d;
    }

    public function enviarTexto(WhatsappConexao $c, string $telefone, string $texto): array
    {
        $fone = $this->normalizar($telefone);
        if ($fone === '') return ['ok' => false, 'erro' => 'Telefone inválido.'];

        try {
            return $c->tipo === 'oficial'
                ? $this->oficial($c, $fone, $texto)
                : $this->naoOficial($c, $fone, $texto);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp envio falhou: '.$e->getMessage());
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /** Evolution API (não oficial): POST {base_url}/message/sendText/{instancia} com header apikey. */
    protected function naoOficial(WhatsappConexao $c, string $fone, string $texto): array
    {
        $base = rtrim((string) $c->base_url, '/');
        if (! $base || ! $c->instancia) return ['ok' => false, 'erro' => 'Configure base_url e instância.'];

        $r = Http::timeout(20)
            ->withHeaders(['apikey' => (string) $c->api_key])
            ->post("{$base}/message/sendText/{$c->instancia}", [
                'number' => $fone,
                'text' => $texto,
            ]);

        return $r->successful()
            ? ['ok' => true, 'resposta' => $r->json()]
            : ['ok' => false, 'erro' => 'HTTP '.$r->status().': '.$r->body()];
    }

    /** Meta WhatsApp Cloud API (oficial). */
    protected function oficial(WhatsappConexao $c, string $fone, string $texto): array
    {
        if (! $c->phone_number_id || ! $c->token) return ['ok' => false, 'erro' => 'Configure phone_number_id e token.'];
        $ver = $c->api_version ?: 'v21.0';

        $r = Http::timeout(20)
            ->withToken((string) $c->token)
            ->post("https://graph.facebook.com/{$ver}/{$c->phone_number_id}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $fone,
                'type' => 'text',
                'text' => ['body' => $texto],
            ]);

        return $r->successful()
            ? ['ok' => true, 'resposta' => $r->json()]
            : ['ok' => false, 'erro' => 'HTTP '.$r->status().': '.$r->body()];
    }
    // ====================== Gerenciar instância Evolution (criar/QR/status) ======================

    protected function evoBase(WhatsappConexao $c): ?string
    {
        $b = rtrim((string) $c->base_url, '/');
        return ($b && $c->instancia) ? $b : null;
    }

    protected function evoHttp(WhatsappConexao $c)
    {
        return Http::timeout(25)->withHeaders(['apikey' => (string) $c->api_key]);
    }

    /** Cria a instância na Evolution (se não existir) e já configura o webhook. */
    public function evoCriar(WhatsappConexao $c, string $webhookUrl): array
    {
        $base = $this->evoBase($c);
        if (! $base) return ['ok' => false, 'erro' => 'Configure URL base e instância.'];
        try {
            $r = $this->evoHttp($c)->post("{$base}/instance/create", [
                'instanceName' => $c->instancia,
                'integration' => 'WHATSAPP-BAILEYS',
                'qrcode' => true,
            ]);
            // 403/409 = já existe -> segue pra configurar webhook mesmo assim
            $this->evoWebhook($c, $webhookUrl);
            if ($r->successful() || in_array($r->status(), [403, 409])) {
                return ['ok' => true, 'ja_existia' => ! $r->successful()];
            }
            return ['ok' => false, 'erro' => 'HTTP '.$r->status().': '.mb_substr($r->body(), 0, 200)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /** Define o webhook da instância apontando pro sistema. */
    public function evoWebhook(WhatsappConexao $c, string $url): array
    {
        $base = $this->evoBase($c);
        if (! $base) return ['ok' => false, 'erro' => 'sem base'];
        try {
            // Evolution v2 (formato aninhado)
            $r = $this->evoHttp($c)->post("{$base}/webhook/set/{$c->instancia}", [
                'webhook' => [
                    'enabled' => true,
                    'url' => $url,
                    'byEvents' => false,
                    'base64' => false,
                    'events' => ['MESSAGES_UPSERT'],
                ],
            ]);
            if (! $r->successful()) {
                // fallback: formato plano (versões mais antigas)
                $r = $this->evoHttp($c)->post("{$base}/webhook/set/{$c->instancia}", [
                    'enabled' => true,
                    'url' => $url,
                    'webhook_by_events' => false,
                    'events' => ['MESSAGES_UPSERT'],
                ]);
            }
            return ['ok' => $r->successful(), 'erro' => $r->successful() ? null : ('HTTP '.$r->status().': '.mb_substr($r->body(),0,150))];
        } catch (\Throwable $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /** Pega o QR code (base64 ou code) para conectar o WhatsApp. Tenta algumas vezes (o QR demora a gerar). */
    public function evoConectar(WhatsappConexao $c): array
    {
        $base = $this->evoBase($c);
        if (! $base) return ['ok' => false, 'erro' => 'Configure URL base e instância.'];

        $ultBody = '';
        for ($i = 0; $i < 4; $i++) {
            try {
                $r = $this->evoHttp($c)->get("{$base}/instance/connect/{$c->instancia}");
                $ultBody = $r->body();
                if (! $r->successful()) {
                    // 404 (instância não existe) tenta criar e repete
                    if ($r->status() === 404) {
                        $this->evoHttp($c)->post("{$base}/instance/create", [
                            'instanceName' => $c->instancia, 'integration' => 'WHATSAPP-BAILEYS', 'qrcode' => true,
                        ]);
                        usleep(1500000);
                        continue;
                    }
                    return ['ok' => false, 'erro' => 'HTTP '.$r->status().': '.mb_substr($r->body(), 0, 220)];
                }
                $j = $r->json();
                $base64 = $j['base64'] ?? ($j['qrcode']['base64'] ?? ($j['qrcode'] ?? null));
                if (is_array($base64)) $base64 = $base64['base64'] ?? null;
                $code = $j['code'] ?? ($j['qrcode']['code'] ?? ($j['pairingCode'] ?? null));
                $estado = $j['instance']['state'] ?? ($j['state'] ?? null);

                if ($base64 || $code) {
                    return ['ok' => true, 'base64' => $base64, 'code' => $code];
                }
                if ($estado === 'open') {
                    return ['ok' => true, 'conectado' => true];
                }
                // sem QR ainda: espera e tenta de novo
                usleep(1500000);
            } catch (\Throwable $e) {
                return ['ok' => false, 'erro' => $e->getMessage()];
            }
        }
        return ['ok' => true, 'base64' => null, 'code' => null, 'bruto' => mb_substr($ultBody, 0, 220)];
    }

    /** Lê a config de webhook da instância no Evolution (diagnóstico). */
    public function evoVerWebhook(WhatsappConexao $c): array
    {
        $base = $this->evoBase($c);
        if (! $base) return ['ok' => false, 'erro' => 'sem base'];
        try {
            $r = $this->evoHttp($c)->get("{$base}/webhook/find/{$c->instancia}");
            if (! $r->successful()) return ['ok' => false, 'erro' => 'HTTP '.$r->status().': '.mb_substr($r->body(), 0, 200)];
            return ['ok' => true, 'config' => $r->json()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /** Estado da conexão: open (conectado) | connecting | close. */
    public function evoStatus(WhatsappConexao $c): array
    {
        $base = $this->evoBase($c);
        if (! $base) return ['ok' => false, 'erro' => 'sem base'];
        try {
            $r = $this->evoHttp($c)->get("{$base}/instance/connectionState/{$c->instancia}");
            if (! $r->successful()) return ['ok' => false, 'erro' => 'HTTP '.$r->status()];
            $j = $r->json();
            $estado = $j['instance']['state'] ?? ($j['state'] ?? 'desconhecido');
            return ['ok' => true, 'estado' => $estado, 'conectado' => $estado === 'open'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /** Baixa a mídia (base64) de uma mensagem do Evolution (ex.: foto do documento). */
    public function evoBaixarMidia(WhatsappConexao $c, array $key): array
    {
        $base = $this->evoBase($c);
        if (! $base || empty($key)) return ['ok' => false, 'erro' => 'sem base/chave'];
        try {
            $r = $this->evoHttp($c)->post("{$base}/chat/getBase64FromMediaMessage/{$c->instancia}", [
                'message' => ['key' => $key],
                'convertToMp4' => false,
            ]);
            if (! $r->successful()) return ['ok' => false, 'erro' => 'HTTP '.$r->status().': '.mb_substr($r->body(), 0, 150)];
            $j = $r->json();
            $b64 = $j['base64'] ?? ($j['media'] ?? null);
            $mime = $j['mimetype'] ?? ($j['mime'] ?? 'image/jpeg');
            return $b64 ? ['ok' => true, 'base64' => $b64, 'mime' => $mime] : ['ok' => false, 'erro' => 'sem base64 na resposta'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /** Remove a instância do Evolution (logout + delete). Best-effort. */
    public function evoDeletar(WhatsappConexao $c): array
    {
        $base = $this->evoBase($c);
        if (! $base) return ['ok' => false, 'erro' => 'sem base'];
        try {
            $this->evoHttp($c)->delete("{$base}/instance/logout/{$c->instancia}");
            $r = $this->evoHttp($c)->delete("{$base}/instance/delete/{$c->instancia}");
            return ['ok' => $r->successful(), 'erro' => $r->successful() ? null : ('HTTP '.$r->status())];
        } catch (\Throwable $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /** Desconecta (logout) a instância. */
    public function evoDesconectar(WhatsappConexao $c): array
    {
        $base = $this->evoBase($c);
        if (! $base) return ['ok' => false, 'erro' => 'sem base'];
        try {
            $r = $this->evoHttp($c)->delete("{$base}/instance/logout/{$c->instancia}");
            return ['ok' => $r->successful(), 'erro' => $r->successful() ? null : ('HTTP '.$r->status())];
        } catch (\Throwable $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }
}
