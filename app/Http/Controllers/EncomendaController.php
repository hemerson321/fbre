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
        return redirect()->route('encomendas.index');
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
        $f = $request->only(['inicio', 'fim', 'origem', 'destino', 'embarcacao_id', 'status_pagamento', 'entregue', 'forma', 'busca', 'user_id']);
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
        ]);

        $empresa = Tenant::empresa();

        $encomenda = DB::transaction(function () use ($data, $empresa) {
            $enc = Encomenda::create([
                'numero' => $empresa->proximoNumero(),
                'embarcacao_id' => $data['embarcacao_id'] ?? null,
                'origem' => $data['origem'],
                'destino' => $data['destino'],
                'remetente_nome' => $data['remetente_nome'],
                'remetente_telefone' => $data['remetente_telefone'] ?? null,
                'remetente_documento' => $data['remetente_documento'] ?? null,
                'destinatario_nome' => $data['destinatario_nome'],
                'destinatario_telefone' => $data['destinatario_telefone'] ?? null,
                'destinatario_documento' => $data['destinatario_documento'] ?? null,
                'volumes' => $data['volumes'],
                'forma' => $data['forma'],
                'setor' => $data['setor'] ?? auth()->user()->setor,
                'observacao' => $data['observacao'] ?? null,
                'desconto' => $data['desconto'] ?? 0,
                'user_id' => auth()->id(),
            ]);

            foreach ($data['itens'] as $it) {
                $enc->itens()->create([
                    'descricao' => $it['descricao'],
                    'quantidade' => $it['quantidade'],
                    'valor_unitario' => $it['valor_unitario'],
                    'valor_total' => round($it['quantidade'] * $it['valor_unitario'], 2),
                    'observacao' => $it['observacao'] ?? null,
                ]);
            }

            foreach ($data['pagamentos'] ?? [] as $p) {
                $enc->pagamentos()->create([
                    'empresa_id' => $empresa->id,
                    'meio' => $p['meio'],
                    'valor' => $p['valor'],
                    'user_id' => auth()->id(),
                    'momento' => 'cadastro',
                ]);
            }

            $enc->recalcular();

            // guarda pessoas para autocompletar
            $this->lembrarPessoa($data['remetente_nome'], $data['remetente_telefone'] ?? null, $data['remetente_documento'] ?? null);
            $this->lembrarPessoa($data['destinatario_nome'], $data['destinatario_telefone'] ?? null, $data['destinatario_documento'] ?? null);

            Evento::registrar('cadastro', $enc, ['total' => $enc->total, 'pago' => $enc->pago]);

            return $enc;
        });

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

    protected function lembrarPessoa(string $nome, ?string $tel, ?string $doc): void
    {
        $nome = trim($nome);
        if ($nome === '') return;
        $p = Pessoa::where('nome', $nome)->when($tel, fn ($q) => $q->where('telefone', $tel))->first();
        if ($p) {
            $p->update(['telefone' => $tel ?: $p->telefone, 'documento' => $doc ?: $p->documento]);
        } else {
            Pessoa::create(['nome' => $nome, 'telefone' => $tel, 'documento' => $doc]);
        }
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
        ]);

        DB::transaction(function () use ($data, $encomenda) {
            $soma = 0;
            foreach (['dinheiro', 'pix', 'debito', 'credito', 'desconto'] as $meio) {
                $v = (float) ($data[$meio] ?? 0);
                if ($v > 0) {
                    $encomenda->pagamentos()->create([
                        'empresa_id' => $encomenda->empresa_id,
                        'meio' => $meio,
                        'valor' => $v,
                        'user_id' => auth()->id(),
                        'momento' => 'baixa',
                    ]);
                    $soma += $v;
                }
            }
            $encomenda->recalcular();
            Evento::registrar('pagamento', $encomenda, ['valor' => $soma, 'detalhe' => array_filter($data)]);

            if (! empty($data['entregar']) && ! $encomenda->entregue) {
                $this->marcarEntregue($encomenda, $data['entregue_para'] ?? null);
            }
        });

        return redirect()->route('encomendas.show', ['encomenda' => $encomenda, 'print' => 1])
            ->with('ok', 'Pagamento registrado.');
    }

    // ------------------------------------------------------------------
    // BAIXA DE ENTREGA
    // ------------------------------------------------------------------
    public function entregar(Request $request, Encomenda $encomenda)
    {
        abort_if($encomenda->cancelada, 422, 'Encomenda cancelada.');
        $data = $request->validate(['entregue_para' => 'nullable|string|max:120']);

        if (! $encomenda->entregue) {
            $this->marcarEntregue($encomenda, $data['entregue_para'] ?? null);
        }

        return redirect()->route('encomendas.show', ['encomenda' => $encomenda, 'print' => 1])
            ->with('ok', 'Entrega registrada.');
    }

    protected function marcarEntregue(Encomenda $encomenda, ?string $para): void
    {
        $encomenda->forceFill([
            'entregue' => true,
            'entregue_em' => now(),
            'entregue_por' => auth()->id(),
            'entregue_para' => $para,
        ])->save();
        Evento::registrar('entrega', $encomenda, ['para' => $para]);
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
