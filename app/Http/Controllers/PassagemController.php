<?php

namespace App\Http\Controllers;

use App\Models\Embarcacao;
use App\Models\Pagamento;
use App\Models\Passagem;
use App\Models\PassagemPassageiro;
use App\Models\Pessoa;
use App\Models\Rota;
use App\Models\TabelaPreco;
use App\Models\TaxaEmbarque;
use App\Models\TrechoPreco;
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
        $colunas = ['id', 'nome', 'cor', 'percentual', 'pct_sobre', 'paga_taxa', 'modo', 'eh_normal', 'tarifa_padrao', 'alimentacao_padrao', 'idade_min', 'idade_max'];
        $tipos = TipoPassageiro::where('ativo', true)->orderBy('eh_normal', 'desc')->orderBy('ordem')->orderBy('nome')->get($colunas);
        if ($tipos->isEmpty()) {
            // auto-reparo: cria o Normal (Adulto) e alguns tipos padrão
            TipoPassageiro::create(['nome' => 'Adulto', 'modo' => 'valor', 'eh_normal' => true, 'percentual' => 100, 'paga_taxa' => true, 'ativo' => true, 'ordem' => 1]);
            foreach ([['Criança meia', 50, 2], ['Idoso meia', 50, 3]] as [$n, $pc, $o]) {
                TipoPassageiro::create(['nome' => $n, 'modo' => 'porcentagem', 'percentual' => $pc, 'paga_taxa' => true, 'ativo' => true, 'ordem' => $o]);
            }
            $tipos = TipoPassageiro::where('ativo', true)->orderBy('eh_normal', 'desc')->orderBy('ordem')->orderBy('nome')->get($colunas);
        }
        $normalId = optional($tipos->firstWhere('eh_normal', true))->id ?? optional($tipos->first())->id;
        $trechoPrecos = TrechoPreco::where('ativo', true)->get(['origem', 'destino', 'tipo_passageiro_id', 'tarifa', 'alimentacao', 'integral']);
        $taxas = TaxaEmbarque::where('ativo', true)->get(['origem', 'destino', 'taxa']);
        $localidades = $trechoPrecos->pluck('origem')
            ->merge($trechoPrecos->pluck('destino'))
            ->merge(Rota::todasLocalidades())->filter()->unique()->sort()->values();
        $cidadesPerm = $u->cidadesPermitidas();
        if ($cidadesPerm) $localidades = $localidades->intersect($cidadesPerm)->values();
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
            'normalId' => $normalId,
            'trechoPrecos' => $trechoPrecos,
            'taxas' => $taxas,
            'localidades' => $localidades,
            'cidades' => $localidades->values(),
            'linhas' => $linhas,
            'rotas' => $rotas,
            'ultimaOrigem' => session('ultima_origem_pass') ?: (Tenant::empresa()->cidade ?: $localidades->first()),
            'embarcacaoPrincipal' => session('ultima_embarcacao_pass') ?: $u->embarcacao_principal_id,
            'checkinAuto' => (bool) $u->checkin_auto,
            'podeParcial' => $u->podeParcial(),
            'podeRetroativo' => $u->podeRetroativo(),
            'usaTaxaEmbarque' => (bool) Tenant::empresa()->config('usa_taxa_embarque', false),
            'usaAlimentacao' => (bool) Tenant::empresa()->config('usa_alimentacao', true),
            'labelTaxa' => Tenant::empresa()->config('label_taxa_embarque') ?: 'Taxa de embarque',
            'labelAlim' => Tenant::empresa()->config('label_alimentacao') ?: 'Alimentação',
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
            'passageiros.*.valor_alimentacao' => 'nullable|numeric|min:0',
            'passageiros.*.valor_integral' => 'nullable|numeric|min:0',
            'passageiros.*.sexo' => 'nullable|in:M,F',
            'passageiros.*.telefone' => 'nullable|string|max:30',
            'passageiros.*.nacionalidade' => 'nullable|string|max:40',
            'passageiros.*.foto_rg' => 'nullable|string|max:255',
            'passageiros.*.foto' => 'nullable|string|max:255',
            'passageiros.*.checkin' => 'nullable|boolean',
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
        $ocupadas = $this->ocupadas($emb->id, $data['data_viagem'] ?? null, $data['viagem_id'] ?? null, $data['rota_id'] ?? null, $data['origem'] ?? null, $data['destino'] ?? null);
        $conflito = $assentos->intersect($ocupadas);
        abort_if($conflito->isNotEmpty(), 422, 'Poltrona(s) já vendida(s): ' . $conflito->implode(', '));
    }

    /**
     * Poltronas ocupadas. Considera o TRECHO: se a rota é Manaus→…→Tefé, um assento
     * vendido Manaus→Tefé bloqueia Coari→Tefé (trechos se sobrepõem), mas não um trecho
     * que não se cruza. Sem rota conhecida, bloqueia a viagem inteira.
     */
    protected function ocupadas($embarcacaoId, $dataViagem, $viagemId, $rotaId = null, $origem = null, $destino = null, $ignorarId = null): \Illuminate\Support\Collection
    {
        $q = Passagem::where('embarcacao_id', $embarcacaoId)->where('cancelada', false)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when(! $viagemId && $dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId));

        $ordem = [];
        if ($rotaId && ($rota = \App\Models\Rota::find($rotaId))) $ordem = array_map('mb_strtolower', $rota->localidades());
        $idx = fn ($cidade) => array_search(mb_strtolower((string) $cidade), $ordem, true);
        $reqS = $origem !== null ? $idx($origem) : false;
        $reqD = $destino !== null ? $idx($destino) : false;
        $temTrecho = $ordem && $reqS !== false && $reqD !== false;
        $reqMin = $temTrecho ? min($reqS, $reqD) : null;
        $reqMax = $temTrecho ? max($reqS, $reqD) : null;

        $ocup = collect();
        foreach ($q->with('passageiros:id,passagem_id,poltrona')->get(['id', 'origem', 'destino']) as $pg) {
            if ($temTrecho) {
                $c = $idx($pg->origem); $d = $idx($pg->destino);
                if ($c !== false && $d !== false) {
                    [$cMin, $cMax] = [min($c, $d), max($c, $d)];
                    if (! ($reqMin < $cMax && $cMin < $reqMax)) continue; // não cruza o trecho -> assento livre
                }
                // trecho do existente desconhecido: por segurança, considera ocupado
            }
            foreach ($pg->passageiros as $pax) if ($pax->poltrona) $ocup->push((string) $pax->poltrona);
        }
        return $ocup->unique()->values();
    }

    /** JSON: poltronas ocupadas de uma embarcação/viagem (AJAX da tela de cadastro). */
    public function assentos(Request $request)
    {
        $emb = Embarcacao::findOrFail($request->integer('embarcacao_id'));
        return [
            'usa_poltrona' => (bool) $emb->usa_poltrona,
            'qtd' => (int) $emb->poltronas_qtd,
            'colunas' => (int) ($emb->poltronas_colunas ?: 4),
            'ocupadas' => $this->ocupadas($emb->id, $request->input('data_viagem'), $request->input('viagem_id'), $request->input('rota_id'), $request->input('origem'), $request->input('destino'))->values(),
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

    /** Tela de check-in (vinda do QR do comprovante) — valida embarque, só operador logado. */
    public function checkin(Passagem $passagem)
    {
        abort_unless($passagem->empresa_id === Tenant::id(), 404);
        $passagem->load(['passageiros', 'embarcacao', 'operador']);
        return view('passagens.checkin', ['p' => $passagem, 'u' => auth()->user()]);
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
