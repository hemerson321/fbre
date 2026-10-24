<?php

namespace App\Http\Controllers;

use App\Models\Cobranca;
use App\Models\Encomenda;
use App\Services\MercadoPago;
use App\Support\Tenant;

/**
 * Gera PIX dinâmico (Mercado Pago) e imprime o QR pro cliente pagar.
 */
class CobrancaController extends Controller
{
    public function pixEncomenda(Encomenda $encomenda, MercadoPago $mp)
    {
        abort_unless($encomenda->empresa_id === Tenant::id(), 404);
        $empresa = Tenant::empresa();

        if (! $empresa->usaPix('encomenda')) {
            return back()->with('erro', 'PIX dinâmico não está habilitado para Encomendas. Ative em Administração → Pagamentos.');
        }
        $api = $empresa->pagamentoApi('encomenda');

        // reaproveita uma cobrança pendente da mesma encomenda (não gera outra à toa)
        $cob = Cobranca::where('referencia_tipo', Encomenda::class)
            ->where('referencia_id', $encomenda->id)
            ->where('status', 'pendente')->latest('id')->first();

        if (! $cob) {
            $valor = (float) $encomenda->devido > 0 ? (float) $encomenda->devido : (float) $encomenda->total;
            if ($valor <= 0) {
                return back()->with('erro', 'Nada a cobrar: encomenda já está paga.');
            }
            $r = $mp->cobrarPix($api, $valor, 'Encomenda '.$encomenda->numeroFormatado(), [
                'nome' => $encomenda->remetente_nome,
                'telefone' => $encomenda->remetente_telefone,
            ]);
            if (! $r['ok']) {
                return back()->with('erro', 'Não consegui gerar o PIX: '.($r['erro'] ?? 'erro'));
            }
            $cob = Cobranca::create([
                'empresa_id' => $encomenda->empresa_id,
                'pagamento_api_id' => $api->id,
                'modulo' => 'encomenda',
                'referencia_tipo' => Encomenda::class,
                'referencia_id' => $encomenda->id,
                'provider' => 'mercadopago',
                'provider_payment_id' => $r['payment_id'] ?? null,
                'valor' => $valor,
                'status' => 'pendente',
                'qr_copia_cola' => $r['copia_cola'] ?? null,
                'qr_base64' => $r['qr_base64'] ?? null,
                'ticket_url' => $r['ticket_url'] ?? null,
                'cliente_nome' => $encomenda->remetente_nome,
                'cliente_telefone' => $encomenda->remetente_telefone,
            ]);
        }

        return view('impressao.pix', ['e' => $encomenda, 'c' => $cob, 'empresa' => $empresa]);
    }
}
