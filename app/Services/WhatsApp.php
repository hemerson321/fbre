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
}
