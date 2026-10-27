<?php

namespace App\Http\Controllers;

use App\Models\Cobranca;
use App\Models\Estacao;
use App\Models\FilaImpressao;
use App\Models\Encomenda;
use App\Models\Passagem;
use App\Services\MercadoPago;
use App\Support\Tenant;
use Illuminate\Http\Request;

/**
 * Gera PIX dinâmico (Mercado Pago) e imprime o QR pro cliente pagar.
 */
class CobrancaController extends Controller
{
    /** Gera um PIX dinâmico avulso (sem pedido ainda) — o pedido só é criado após o pagamento confirmar. */
    public function gerarAvulso(Request $request, MercadoPago $mp)
    {
        $data = $request->validate([
            'modulo' => 'required|in:encomenda,passagem,frete',
            'valor' => 'required|numeric|min:0.01',
            'descricao' => 'nullable|string|max:120',
            'cliente_nome' => 'nullable|string|max:120',
            'cliente_telefone' => 'nullable|string|max:30',
            'payload' => 'nullable|array',      // pedido a criar quando o PIX confirmar
            'imprimir' => 'nullable|string|max:20',
        ]);
        if (! auth()->user()->podePixDinamico()) {
            return response()->json(['ok' => false, 'erro' => 'Você não tem permissão para gerar PIX dinâmico.'], 403);
        }
        $empresa = Tenant::empresa();
        if (! $empresa->usaPix($data['modulo'])) {
            return response()->json(['ok' => false, 'erro' => 'PIX não está habilitado neste módulo.']);
        }
        $api = $empresa->pagamentoApi($data['modulo']);
        $r = $mp->cobrarPix($api, (float) $data['valor'], $data['descricao'] ?: ucfirst($data['modulo']), [
            'nome' => $data['cliente_nome'] ?? null,
            'telefone' => $data['cliente_telefone'] ?? null,
        ]);
        if (! $r['ok']) {
            return response()->json(['ok' => false, 'erro' => 'Não consegui gerar o PIX: '.($r['erro'] ?? 'erro')]);
        }
        $cob = Cobranca::create([
            'empresa_id' => $empresa->id,
            'pagamento_api_id' => $api->id,
            'user_id' => auth()->id(),
            'modulo' => $data['modulo'],
            'referencia_tipo' => null,
            'referencia_id' => null,
            'payload' => $data['payload'] ?? null,
            'imprimir' => $data['imprimir'] ?? null,
            'provider' => 'mercadopago',
            'provider_payment_id' => $r['payment_id'] ?? null,
            'valor' => (float) $data['valor'],
            'status' => 'pendente',
            'qr_copia_cola' => $r['copia_cola'] ?? null,
            'qr_base64' => $r['qr_base64'] ?? null,
            'ticket_url' => $r['ticket_url'] ?? null,
            'cliente_nome' => $data['cliente_nome'] ?? null,
            'cliente_telefone' => $data['cliente_telefone'] ?? null,
        ]);
        return response()->json([
            'ok' => true, 'id' => $cob->id, 'valor' => (float) $cob->valor,
            'qr_base64' => $cob->qr_base64, 'copia_cola' => $cob->qr_copia_cola,
        ]);
    }

    /** Enfileira o QR do PIX pro agente de impressão (igual ao comprovante), na estação ativa. */
    public function imprimirDireto(Cobranca $cobranca)
    {
        abort_unless($cobranca->empresa_id === Tenant::id(), 404);
        $empresa = Tenant::empresa();
        $estacao = Estacao::where('ativo', true)->orderByDesc('visto_em')->first();
        if (! $estacao) {
            return response()->json(['ok' => false, 'erro' => 'Nenhuma estação de impressão ativa.']);
        }
        $job = FilaImpressao::create([
            'estacao_id' => $estacao->id,
            'encomenda_id' => null,
            'tipo' => 'pix',
            'dados' => [
                'empresa' => ['nome' => $empresa->nomeExibicao(), 'cnpj' => $empresa->cnpj],
                'pix' => [
                    'rotulo' => ucfirst((string) $cobranca->modulo),
                    'valor' => (float) $cobranca->valor,
                    'copia_cola' => $cobranca->qr_copia_cola,
                    'qr_base64' => $cobranca->qr_base64,
                ],
            ],
        ]);
        return response()->json(['ok' => true, 'job' => $job->id]);
    }

    /** Impressão enxuta do QR de uma cobrança (avulsa ou de pedido). */
    public function imprimir(Cobranca $cobranca)
    {
        abort_unless($cobranca->empresa_id === Tenant::id(), 404);
        return view('impressao.pix', ['numero' => (string) $cobranca->id, 'c' => $cobranca, 'rotulo' => ucfirst((string) $cobranca->modulo), 'empresa' => Tenant::empresa()]);
    }

    /**
     * Status de uma cobrança (consulta o MP se ainda pendente). Usado pela tela que aguarda o pagamento.
     * Ao confirmar, o SERVIDOR já cria o pedido (encomenda/passagem) e devolve a URL pra tela redirecionar.
     */
    public function status(Cobranca $cobranca, MercadoPago $mp, \App\Services\ConfirmaPagamento $confirma)
    {
        abort_unless($cobranca->empresa_id === Tenant::id(), 404);

        // ainda pendente: consulta o MP (curto). Se pagou, aplica (cria o pedido).
        if ($cobranca->status !== 'pago' && $cobranca->provider_payment_id && $cobranca->api) {
            try {
                $st = $mp->consultar($cobranca->api, (string) $cobranca->provider_payment_id, 8);
                if (($st['ok'] ?? false) && ($st['pago'] ?? false)) {
                    $confirma->aplicar($cobranca);
                }
            } catch (\Throwable $e) {}
        } elseif ($cobranca->status === 'pago' && ! $cobranca->referencia_id && $cobranca->payload) {
            // pago pelo webhook antes da tela ver, mas o pedido ainda não foi criado → cria agora
            try { $confirma->criarPedidoDoPayload($cobranca); } catch (\Throwable $e) {}
        }

        $cobranca->refresh();
        $resp = ['pago' => $cobranca->status === 'pago', 'valor' => (float) $cobranca->valor];

        if ($cobranca->status === 'pago') {
            $resp['imprimir'] = $cobranca->imprimir;
            $resp['erro'] = $cobranca->erro;
            if ($cobranca->referencia_id) {
                $resp['id'] = (int) $cobranca->referencia_id;
                $resp['url'] = match ($cobranca->referencia_tipo) {
                    Passagem::class => route('passagens.show', $cobranca->referencia_id),
                    \App\Models\Frete::class => route('fretes.show', $cobranca->referencia_id),
                    default => route('encomendas.show', $cobranca->referencia_id),
                };
            }
        }
        return response()->json($resp);
    }

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
