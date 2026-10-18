<?php

namespace App\Http\Controllers;

use App\Models\Embarcacao;
use App\Models\Encomenda;
use App\Models\Evento;
use App\Models\Localidade;
use App\Models\Pagamento;
use App\Models\Pessoa;
use App\Models\Produto;
use App\Models\Setor;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EncomendaController extends Controller
{
    public function inicio()
    {
        $u = auth()->user();
        if ($u->podeFiltrar()) return redirect()->route('encomendas.index');
        if ($u->podeCadastrar()) return redirect()->route('encomendas.create');
        if ($u->podeVerCaixa()) return redirect()->route('caixa.index');
        return redirect()->route('relatorio.meu');
    }

    // ------------------------------------------------------------------
    // LISTA / FILTRO
    // ------------------------------------------------------------------
    public function index(Request $request)
    {
        $f = $this->filtros($request);
        $base = Encomenda::filtrar($f);

        $totais = [
            'qtd'       => (clone $base)->count(),
            'volumes'   => (clone $base)->sum('volumes'),
            'total'     => (clone $base)->sum('total'),
            'recebido'  => (clone $base)->sum('pago'),
            'a_receber' => (clone $base)->sum('devido'),
            'desconto'  => (clone $base)->sum('desconto'),
        ];

        $ids = (clone $base)->select('id');
        $porMeio = Pagamento::whereIn('encomenda_id', $ids)
            ->where('meio', '!=', 'desconto')
            ->selectRaw('meio, SUM(valor) as valor')
            ->groupBy('meio')->pluck('valor', 'meio');

        $encomendas = (clone $base)->with(['embarcacao', 'operador'])
            ->orderByDesc('id')->paginate(50)->withQueryString();

        return view('encomendas.index', [
            'encomendas' => $encomendas,
            'totais' => $totais,
            'porMeio' => $porMeio,
            'f' => $f,
            'localidades' => Localidade::where('ativo', true)->orderBy('nome')->pluck('nome'),
            'embarcacoes' => Embarcacao::where('ativo', true)->orderBy('nome')->get(),
        ]);
    }

    protected function filtros(Request $request): array
    {
        $f = $request->only(['inicio', 'fim', 'hora_inicio', 'hora_fim', 'origem', 'destino', 'embarcacao_id', 'status_pagamento', 'entregue', 'forma', 'busca', 'user_id']);
        if (! $request->has('inicio') && ! $request->filled('busca')) {
            $f['inicio'] = now()->subDays(7)->toDateString();
            $f['fim'] = now()->toDateString();
        }
        return $f;
    }

    // ------------------------------------------------------------------
    // CADASTRO
    // ------------------------------------------------------------------
    public function create()
    {
        return view('encomendas.create', [
            'localidades' => Localidade::where('ativo', true)->orderBy('nome')->pluck('nome'),
            'embarcacoes' => Embarcacao::where('ativo', true)->orderBy('nome')->get(),
            'produtos' => Produto::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'valor_padrao']),
            'setores' => Setor::orderBy('nome')->pluck('nome'),
            'ultimaOrigem' => session('ultima_origem', Tenant::empresa()->cidade),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'origem' => 'required|string|max:80',
            'destino' => 'required|string|max:80',
            'embarcacao_id' => 'nullable|integer',
            'remetente_nome' => 'required|string|max:120',
            'remetente_telefone' => 'nullable|string|max:30',
            'remetente_documento' => 'nullable|string|max:20',
            'destinatario_nome' => 'required|string|max:120',
            'destinatario_telefone' => 'nullable|string|max:30',
            'destinatario_documento' => 'nullable|string|max:20',
            'volumes' => 'required|integer|min:1|max:999',
            'forma' => 'required|in:avista,areceber,faturado',
            'setor' => 'nullable|string|max:60',
            'observacao' => 'nullable|string|max:1000',
            'itens' => 'required|array|min:1',
            'itens.*.descricao' => 'required|string|max:120',
            'itens.*.quantidade' => 'required|numeric|min:0.01',
            'itens.*.valor_unitario' => 'required|numeric|min:0',
            'itens.*.observacao' => 'nullable|string|max:200',
            'desconto' => 'nullable|numeric|min:0',
            'pagamentos' => 'nullable|array',
            'pagamentos.*.meio' => 'required_with:pagamentos|in:dinheiro,pix,debito,credito',
            'pagamentos.*.valor' => 'required_with:pagamentos|numeric|min:0.01',
            'imprimir' => 'nullable|in:navegador,direto,nao',
            'uuid' => 'nullable|uuid',
        ]);

        $encomenda = app(\App\Services\EncomendaService::class)->criar($data, auth()->id(), $request->input('uuid'));

        session(['ultima_origem' => $data['origem']]);

        $imprimir = $data['imprimir'] ?? 'navegador';

        if ($imprimir === 'direto') {
            app(ImpressaoController::class)->enfileirar($encomenda, 'comprovante');
            app(ImpressaoController::class)->enfileirar($encomenda, 'etiqueta');
            return redirect()->route('encomendas.show', $encomenda)->with('ok', "Encomenda {$encomenda->numeroFormatado()} salva e enviada para impressão.");
        }

        return redirect()->route('encomendas.show', ['encomenda' => $encomenda, 'print' => $imprimir === 'navegador' ? 1 : 0])
            ->with('ok', "Encomenda {$encomenda->numeroFormatado()} salva.");
    }

    public function buscarPessoa(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if (strlen($q) < 2) return [];
        return Pessoa::where(fn ($w) => $w->where('nome', 'like', "%$q%")->orWhere('telefone', 'like', "%$q%"))
            ->orderBy('nome')->limit(8)->get(['nome', 'telefone', 'documento']);
    }

    // ------------------------------------------------------------------
    // DETALHE
    // ------------------------------------------------------------------
    public function show(Encomenda $encomenda)
    {
        $encomenda->load(['itens', 'pagamentos.user', 'embarcacao', 'operador', 'entreguePor', 'eventos.user']);
        return view('encomendas.show', ['e' => $encomenda, 'meios' => Pagamento::MEIOS]);
    }

    // ------------------------------------------------------------------
    // BAIXA DE PAGAMENTO
    // ------------------------------------------------------------------
    public function pagar(Request $request, Encomenda $encomenda)
    {
        abort_if($encomenda->cancelada, 422, 'Encomenda cancelada.');

        $data = $request->validate([
            'dinheiro' => 'nullable|numeric|min:0',
            'pix' => 'nullable|numeric|min:0',
            'debito' => 'nullable|numeric|min:0',
            'credito' => 'nullable|numeric|min:0',
            'desconto' => 'nullable|numeric|min:0',
            'entregar' => 'nullable|boolean',
            'entregue_para' => 'nullable|string|max:120',
            'uuid' => 'nullable|uuid',
        ]);

        app(\App\Services\EncomendaService::class)->baixa($encomenda, $data, auth()->id(), $request->input('uuid'));

        return redirect()->route('encomendas.show', ['encomenda' => $encomenda, 'print' => 1])
            ->with('ok', 'Pagamento registrado.');
    }

    // ------------------------------------------------------------------
    // BAIXA DE ENTREGA
    // ------------------------------------------------------------------
    public function entregar(Request $request, Encomenda $encomenda)
    {
        abort_if($encomenda->cancelada, 422, 'Encomenda cancelada.');
        $data = $request->validate(['entregue_para' => 'nullable|string|max:120', 'uuid' => 'nullable|uuid']);

        app(\App\Services\EncomendaService::class)->baixa($encomenda, $data + ['entregar' => true], auth()->id(), $request->input('uuid'));

        return redirect()->route('encomendas.show', ['encomenda' => $encomenda, 'print' => 1])
            ->with('ok', 'Entrega registrada.');
    }

    public function cancelar(Encomenda $encomenda)
    {
        $encomenda->forceFill(['cancelada' => true])->save();
        $encomenda->pagamentos()->update(['estornado' => true]);
        $encomenda->recalcular();
        Evento::registrar('cancelamento', $encomenda);
        return redirect()->route('encomendas.index')->with('ok', 'Encomenda cancelada.');
    }
}
