<?php

namespace App\Http\Controllers;

use App\Models\Embarcacao;
use App\Models\Pagamento;
use App\Models\Passagem;
use App\Models\PassagemPassageiro;
use App\Models\Pessoa;
use App\Models\Rota;
use App\Models\TabelaPreco;
use App\Models\TipoPassageiro;
use App\Support\Tenant;
use Illuminate\Http\Request;

class PassagemController extends Controller
{
    // ---------------- LISTA / FILTRO ----------------
    public function index(Request $request)
    {
        $f = $this->filtros($request);
        $base = Passagem::filtrar($f);

        $totais = [
            'qtd'       => (clone $base)->count(),
            'passageiros' => (clone $base)->sum('qtd_passageiros'),
            'total'     => (clone $base)->sum('total'),
            'recebido'  => (clone $base)->sum('pago'),
            'a_receber' => (clone $base)->sum('devido'),
        ];

        $ids = (clone $base)->select('id');
        $porMeio = Pagamento::where('modulo', 'passagem')->whereIn('origem_id', $ids)
            ->where('meio', '!=', 'desconto')->selectRaw('meio, SUM(valor) as valor')
            ->groupBy('meio')->pluck('valor', 'meio');

        $passagens = (clone $base)->with(['embarcacao', 'operador'])->orderByDesc('id')->paginate(50)->withQueryString();

        return view('passagens.index', [
            'passagens' => $passagens,
            'totais' => $totais,
            'porMeio' => $porMeio,
            'f' => $f,
            'localidades' => TabelaPreco::select('origem')->distinct()->pluck('origem')
                ->merge(Rota::todasLocalidades())->unique()->sort()->values(),
            'embarcacoes' => Embarcacao::where('ativo', true)->orderBy('nome')->get(),
        ]);
    }

    protected function filtros(Request $request): array
    {
        $f = $request->only(['inicio', 'fim', 'hora_inicio', 'hora_fim', 'origem', 'destino', 'embarcacao_id', 'viagem_id', 'status_pagamento', 'embarcou', 'forma', 'busca', 'user_id']);
        if (! $request->has('inicio') && ! $request->filled('busca')) {
            $f['inicio'] = now()->subDays(7)->toDateString();
            $f['fim'] = now()->toDateString();
        }
        return $f;
    }

    // ---------------- CADASTRO ----------------
    public function create()
    {
        $u = auth()->user();
        $embarcacoes = Embarcacao::where('ativo', true)->orderBy('nome')
            ->get(['id', 'nome', 'usa_poltrona', 'poltronas_qtd', 'poltronas_colunas']);
        $tipos = TipoPassageiro::where('ativo', true)->orderBy('ordem')->orderBy('nome')
            ->get(['nome', 'percentual', 'paga_taxa']);
        if ($tipos->isEmpty()) {
            // auto-reparo: garante os tipos padrão desta empresa (evita tela vazia / erro)
            foreach ([['Adulto', 100, true, 1], ['Estudante', 50, true, 2], ['Criança', 50, true, 3], ['Idoso', 50, true, 4], ['Gratuito', 0, false, 5]] as [$n, $pc, $tx, $o]) {
                TipoPassageiro::create(['nome' => $n, 'percentual' => $pc, 'paga_taxa' => $tx, 'ativo' => true, 'ordem' => $o]);
            }
            $tipos = TipoPassageiro::where('ativo', true)->orderBy('ordem')->orderBy('nome')->get(['nome', 'percentual', 'paga_taxa']);
        }
        $precos = TabelaPreco::where('ativo', true)->get(['origem', 'destino', 'passagem', 'taxa_embarque']);
        $localidades = TabelaPreco::where('ativo', true)->pluck('origem')
            ->merge(TabelaPreco::where('ativo', true)->pluck('destino'))
            ->merge(Rota::todasLocalidades())->unique()->sort()->values();
        $rotas = Tenant::empresa()->temRecurso('viagens') ? Rota::paraSelecao() : collect();
        // linhas (com paradas) para o JS resolver a viagem a partir de origem/destino (igual ao encomenda)
        $linhas = $rotas->map(fn ($r) => [
            'id' => $r->id,
            'nome' => $r->nome,
            'paradas' => $r->paradas->map(fn ($p) => ['n' => $p->localidade, 'd' => $p->dia_semana, 'h' => $p->hora ? substr($p->hora, 0, 5) : null])->values(),
        ])->values();

        return view('passagens.create', [
            'embarcacoes' => $embarcacoes,
            'tipos' => $tipos,
            'precos' => $precos,
            'localidades' => $localidades,
            'cidades' => $localidades->values(),
            'linhas' => $linhas,
            'rotas' => $rotas,
            'ultimaOrigem' => session('ultima_origem_pass') ?: (Tenant::empresa()->cidade ?: $localidades->first()),
            'embarcacaoPrincipal' => session('ultima_embarcacao_pass') ?: $u->embarcacao_principal_id,
            'podeParcial' => $u->podeParcial(),
            'podeRetroativo' => $u->podeRetroativo(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $this->checarPoltronas($data);
        $p = app(\App\Services\PassagemService::class)->criar($data, auth()->id(), $request->input('uuid'));
        session(['ultima_origem_pass' => $data['origem']]);

        $imprimir = $request->input('imprimir', 'navegador');
        return redirect()->route('passagens.show', ['passagem' => $p, 'print' => $imprimir === 'navegador' ? 1 : 0])
            ->with('ok', "Passagem {$p->numeroFormatado()} salva.");
    }

    protected function validar(Request $request): array
    {
        return $request->validate([
            'uuid' => 'nullable|uuid',
            'numero_completo' => 'nullable|string|max:30',
            'numero_seq' => 'nullable|integer|min:1',
            'criada_em' => 'nullable|date',
            'origem' => 'required|string|max:80',
            'destino' => 'required|string|max:80',
            'embarcacao_id' => 'nullable|integer',
            'rota_id' => 'nullable|integer',
            'viagem_id' => 'nullable|integer',
            'data_viagem' => 'nullable|date',
            'comprador_nome' => 'nullable|string|max:120',
            'comprador_telefone' => 'nullable|string|max:30',
            'comprador_documento' => 'nullable|string|max:20',
            'forma' => 'required|in:avista,areceber,faturado',
            'observacao' => 'nullable|string|max:1000',
            'desconto' => 'nullable|numeric|min:0',
            'passageiros' => 'required|array|min:1',
            'passageiros.*.nome' => 'required|string|max:120',
            'passageiros.*.tipo' => 'required|string|max:60',
            'passageiros.*.percentual' => 'nullable|integer|min:0|max:100',
            'passageiros.*.documento' => 'nullable|string|max:20',
            'passageiros.*.nascimento' => 'nullable|date',
            'passageiros.*.poltrona' => 'nullable|string|max:10',
            'passageiros.*.valor_passagem' => 'required|numeric|min:0',
            'passageiros.*.valor_taxa' => 'nullable|numeric|min:0',
            'passageiros.*.observacao' => 'nullable|string|max:200',
            'pagamentos' => 'nullable|array',
            'pagamentos.*.meio' => 'required_with:pagamentos|in:dinheiro,pix,debito,credito',
            'pagamentos.*.valor' => 'required_with:pagamentos|numeric|min:0',
            'imprimir' => 'nullable|in:navegador,direto,nao',
        ]);
    }

    /** Impede vender a mesma poltrona duas vezes na mesma viagem/data. */
    protected function checarPoltronas(array $data): void
    {
        $emb = ($data['embarcacao_id'] ?? null) ? Embarcacao::find($data['embarcacao_id']) : null;
        if (! $emb || ! $emb->usa_poltrona) return;
        $assentos = collect($data['passageiros'])->pluck('poltrona')->filter()->values();
        if ($assentos->isEmpty()) return;
        abort_if($assentos->duplicates()->isNotEmpty(), 422, 'Há poltronas repetidas no mesmo cadastro.');
        $ocupadas = $this->ocupadas($emb->id, $data['data_viagem'] ?? null, $data['viagem_id'] ?? null);
        $conflito = $assentos->intersect($ocupadas);
        abort_if($conflito->isNotEmpty(), 422, 'Poltrona(s) já vendida(s): ' . $conflito->implode(', '));
    }

    protected function ocupadas($embarcacaoId, $dataViagem, $viagemId, $ignorarId = null): \Illuminate\Support\Collection
    {
        $q = Passagem::where('embarcacao_id', $embarcacaoId)->where('cancelada', false)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when(! $viagemId && $dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId));
        return PassagemPassageiro::whereIn('passagem_id', $q->select('id'))
            ->whereNotNull('poltrona')->pluck('poltrona');
    }

    /** JSON: poltronas ocupadas de uma embarcação/viagem (AJAX da tela de cadastro). */
    public function assentos(Request $request)
    {
        $emb = Embarcacao::findOrFail($request->integer('embarcacao_id'));
        return [
            'usa_poltrona' => (bool) $emb->usa_poltrona,
            'qtd' => (int) $emb->poltronas_qtd,
            'ocupadas' => $this->ocupadas($emb->id, $request->input('data_viagem'), $request->input('viagem_id'))->values(),
        ];
    }

    // ---------------- DETALHE ----------------
    public function show(Passagem $passagem)
    {
        $passagem->load(['passageiros', 'pagamentos.user', 'embarcacao', 'operador', 'embarcouPor']);
        return view('passagens.show', ['p' => $passagem, 'meios' => Pagamento::MEIOS]);
    }

    // ---------------- BAIXA ----------------
    public function pagar(Request $request, Passagem $passagem)
    {
        abort_if($passagem->cancelada, 422, 'Passagem cancelada.');
        $data = $request->validate([
            'dinheiro' => 'nullable|numeric|min:0', 'pix' => 'nullable|numeric|min:0',
            'debito' => 'nullable|numeric|min:0', 'credito' => 'nullable|numeric|min:0', 'desconto' => 'nullable|numeric|min:0',
            'embarcar' => 'nullable|boolean',
        ]);
        app(\App\Services\PassagemService::class)->baixa($passagem, $data, auth()->id());
        return redirect()->route('passagens.show', ['passagem' => $passagem, 'print' => 1])->with('ok', 'Pagamento registrado.');
    }

    public function embarcar(Request $request, Passagem $passagem)
    {
        abort_if($passagem->cancelada, 422, 'Passagem cancelada.');
        abort_if($passagem->devido > 0.009 && $passagem->forma !== 'faturado', 422, 'Registre o pagamento antes do embarque.');
        app(\App\Services\PassagemService::class)->baixa($passagem, ['embarcar' => true], auth()->id());
        return redirect()->route('passagens.show', $passagem)->with('ok', 'Embarque confirmado.');
    }

    public function cancelar(Request $request, Passagem $passagem)
    {
        $data = $request->validate(['motivo' => 'required|string|min:3|max:300'], ['motivo.required' => 'Informe o motivo.']);
        $passagem->forceFill(['cancelada' => true, 'motivo_cancelamento' => $data['motivo']])->save();
        $passagem->pagamentos()->update(['estornado' => true]);
        $passagem->recalcular();
        return redirect()->route('passagens.show', $passagem)->with('ok', 'Passagem cancelada.');
    }
}
