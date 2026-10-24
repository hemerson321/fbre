<?php

namespace App\Http\Controllers;

use App\Models\Cobranca;
use App\Models\Encomenda;
use App\Models\PagamentoApi;
use App\Models\Passagem;
use App\Services\EncomendaService;
use App\Services\MercadoPago;
use App\Services\PassagemService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook do Mercado Pago (público). URL por API: /mp/webhook/{id}.
 * A notificação é só um gatilho: a verdade vem de consultar() na API do MP com nosso token.
 * Se aprovado, marca a cobrança como paga e dá baixa na encomenda/passagem.
 */
class MpWebhookController extends Controller
{
    public function receber(Request $request, int $api, MercadoPago $mp)
    {
        $apiModel = PagamentoApi::withoutGlobalScopes()->find($api);
        if (! $apiModel) return response()->json(['ok' => true], 200);
        Tenant::set($apiModel->empresa_id);

        $type = $request->input('type', $request->input('topic', $request->query('type', $request->query('topic'))));
        $paymentId = $request->input('data.id', $request->input('id', $request->query('data.id', $request->query('id'))));

        if ($type && ! str_contains((string) $type, 'payment')) {
            return response()->json(['ok' => true], 200);
        }
        if (! $paymentId) return response()->json(['ok' => true], 200);

        try {
            $cob = Cobranca::withoutGlobalScopes()
                ->where('empresa_id', $apiModel->empresa_id)
                ->where('provider_payment_id', (string) $paymentId)->first();
            if (! $cob || $cob->status === 'pago') return response()->json(['ok' => true], 200);

            $st = $mp->consultar($apiModel, (string) $paymentId); // fonte da verdade
            if (($st['ok'] ?? false) && ($st['pago'] ?? false)) {
                app(\App\Services\ConfirmaPagamento::class)->aplicar($cob);
            }
        } catch (\Throwable $e) {
            Log::warning('MP webhook erro: '.$e->getMessage());
        }

        return response()->json(['ok' => true], 200);
    }

    protected function darBaixa(Cobranca $cob): void
    {
        $valor = (float) $cob->valor;
        if ($valor <= 0 || ! $cob->referencia_id) return;

        if ($cob->referencia_tipo === Encomenda::class) {
            $enc = Encomenda::withoutGlobalScopes()->find($cob->referencia_id);
            if ($enc) {
                app(EncomendaService::class)->baixa($enc, ['pix' => $valor], (int) $enc->user_id, 'mp-'.$cob->id);
            }
        } elseif ($cob->referencia_tipo === Passagem::class) {
            $p = Passagem::withoutGlobalScopes()->find($cob->referencia_id);
            if ($p) {
                app(PassagemService::class)->baixa($p, ['pix' => $valor], (int) $p->user_id);
            }
        }
    }
}
