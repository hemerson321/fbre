<?php

namespace App\Services;

use App\Models\PagamentoApi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Integração Mercado Pago (PIX). Gera a cobrança e consulta o status.
 * Usa o access_token da API cadastrada (por empresa/módulo).
 */
class MercadoPago
{
    protected string $base = 'https://api.mercadopago.com';

    /** Valida as credenciais (não gera cobrança). */
    public function validar(PagamentoApi $api): array
    {
        try {
            $r = Http::timeout(20)->withToken((string) $api->access_token)->get($this->base.'/users/me');
            if ($r->successful()) {
                return ['ok' => true, 'conta' => $r->json('nickname') ?? $r->json('email') ?? 'conta MP'];
            }
            return ['ok' => false, 'erro' => 'HTTP '.$r->status().': '.$r->body()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /**
     * Gera uma cobrança PIX. Retorna id do pagamento, copia-e-cola, QR (base64) e link.
     * @return array{ok:bool,payment_id?:string,status?:string,copia_cola?:string,qr_base64?:string,ticket_url?:string,erro?:string}
     */
    public function cobrarPix(PagamentoApi $api, float $valor, string $descricao, array $cliente = []): array
    {
        $email = $cliente['email'] ?? 'cliente+'.Str::random(6).'@fbre.com.br';
        $nome = trim((string) ($cliente['nome'] ?? 'Cliente'));

        try {
            $r = Http::timeout(25)
                ->withToken((string) $api->access_token)
                ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
                ->post($this->base.'/v1/payments', [
                    'transaction_amount' => round($valor, 2),
                    'description' => Str::limit($descricao, 250, ''),
                    'payment_method_id' => 'pix',
                    'payer' => [
                        'email' => $email,
                        'first_name' => $nome ?: 'Cliente',
                    ],
                ]);

            if (! $r->successful()) {
                Log::warning('MP cobrarPix falhou: '.$r->status().' '.$r->body());
                return ['ok' => false, 'erro' => 'HTTP '.$r->status().': '.$r->body()];
            }
            $j = $r->json();
            $tx = $j['point_of_interaction']['transaction_data'] ?? [];

            return [
                'ok' => true,
                'payment_id' => (string) ($j['id'] ?? ''),
                'status' => (string) ($j['status'] ?? 'pending'),
                'copia_cola' => (string) ($tx['qr_code'] ?? ''),
                'qr_base64' => (string) ($tx['qr_code_base64'] ?? ''),
                'ticket_url' => (string) ($tx['ticket_url'] ?? ''),
            ];
        } catch (\Throwable $e) {
            Log::warning('MP cobrarPix erro: '.$e->getMessage());
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    /** Consulta o status de um pagamento. Mapeia para pendente|pago|expirado|cancelado. */
    public function consultar(PagamentoApi $api, string $paymentId): array
    {
        try {
            $r = Http::timeout(20)->withToken((string) $api->access_token)->get($this->base.'/v1/payments/'.$paymentId);
            if (! $r->successful()) {
                return ['ok' => false, 'erro' => 'HTTP '.$r->status().': '.$r->body()];
            }
            $mp = (string) $r->json('status'); // approved|pending|rejected|cancelled|refunded|...
            $status = match ($mp) {
                'approved' => 'pago',
                'cancelled', 'rejected' => 'cancelado',
                'refunded', 'charged_back' => 'cancelado',
                default => 'pendente',
            };
            return ['ok' => true, 'status' => $status, 'pago' => $status === 'pago', 'mp_status' => $mp];
        } catch (\Throwable $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }
}
