<?php

namespace App\Http\Controllers;

use App\Models\Cobranca;
use App\Models\Encomenda;
use App\Models\Passagem;
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

        return view('impressao.pix', ['rotulo' => 'Encomenda', 'numero' => $encomenda->numeroFormatado(), 'c' => $cob, 'empresa' => $empresa]);
    }

    public function pixPassagem(Passagem $passagem, MercadoPago $mp)
    {
        abort_unless($passagem->empresa_id === Tenant::id(), 404);
        $empresa = Tenant::empresa();

        if (! $empresa->usaPix('passagem')) {
            return back()->with('erro', 'PIX dinâmico não está habilitado para Passagens. Ative em Administração → Pagamentos.');
        }
        $api = $empresa->pagamentoApi('passagem');

        $cob = Cobranca::where('referencia_tipo', Passagem::class)
            ->where('referencia_id', $passagem->id)
            ->where('status', 'pendente')->latest('id')->first();

        if (! $cob) {
            $valor = (float) $passagem->devido > 0 ? (float) $passagem->devido : (float) $passagem->total;
            if ($valor <= 0) {
                return back()->with('erro', 'Nada a cobrar: passagem já está paga.');
            }
            $rotulo = $passagem->tipo === 'camarote' ? 'Camarote' : 'Passagem';
            $r = $mp->cobrarPix($api, $valor, $rotulo.' '.$passagem->numeroFormatado(), [
                'nome' => $passagem->comprador_nome,
                'telefone' => $passagem->comprador_telefone,
            ]);
            if (! $r['ok']) {
                return back()->with('erro', 'Não consegui gerar o PIX: '.($r['erro'] ?? 'erro'));
            }
            $cob = Cobranca::create([
                'empresa_id' => $passagem->empresa_id,
                'pagamento_api_id' => $api->id,
                'modulo' => 'passagem',
                'referencia_tipo' => Passagem::class,
                'referencia_id' => $passagem->id,
                'provider' => 'mercadopago',
                'provider_payment_id' => $r['payment_id'] ?? null,
                'valor' => $valor,
                'status' => 'pendente',
                'qr_copia_cola' => $r['copia_cola'] ?? null,
                'qr_base64' => $r['qr_base64'] ?? null,
                'ticket_url' => $r['ticket_url'] ?? null,
                'cliente_nome' => $passagem->comprador_nome,
                'cliente_telefone' => $passagem->comprador_telefone,
            ]);
        }

        $rot = $passagem->tipo === 'camarote' ? 'Camarote' : 'Passagem';
        return view('impressao.pix', ['rotulo' => $rot, 'numero' => $passagem->numeroFormatado(), 'c' => $cob, 'empresa' => $empresa]);
    }
}
