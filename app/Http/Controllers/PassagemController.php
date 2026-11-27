<?php

namespace App\Http\Controllers;

use App\Models\Camarote;
use App\Models\Embarcacao;
use App\Models\Pagamento;
use App\Models\Passagem;
use App\Models\PassagemPassageiro;
use App\Models\Pessoa;
use App\Models\PoltronaBloqueada;
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
        $verTodos = auth()->user()->relatorioTodos() || auth()->user()->caixaTodos() || auth()->user()->isAdmin();
        $escopo = $request->input('escopo', $verTodos ? 'todos' : 'meu');
        if (! $verTodos) { unset($f['vendedor_id']); }
        $vendedorSel = $verTodos ? ($f['vendedor_id'] ?? null) : null;
        if ($vendedorSel) { $escopo = 'todos'; }
        if (! $verTodos || ($escopo === 'meu' && ! $vendedorSel)) { $f['user_id'] = auth()->id(); $escopo = 'meu'; }
        $base = Passagem::filtrar($f);

        $totais = [
            'qtd'       => (clone $base)->count(),
            'passageiros' => (clone $base)->sum('qtd_passageiros'),
            'total'     => (clone $base)->sum('total'),
            'recebido'  => (clone $base)->sum('pago'),
            'a_receber' => (clone $base)->sum('devido'),
        ];
        $fCanc = $f; $fCanc['cancelada'] = '1'; unset($fCanc['status_pagamento'], $fCanc['embarcou']);
        $totais['cancelados'] = Passagem::filtrar($fCanc)->count();
        $totais['cancelados_valor'] = Passagem::filtrar($fCanc)->sum('total');
        // + passageiros cancelados individualmente (cancelamento parcial) nas passagens ativas
        $idsAtivas = (clone $base)->pluck('id');
        if ($idsAtivas->isNotEmpty()) {
            $paxCanc = \App\Models\PassagemPassageiro::whereIn('passagem_id', $idsAtivas)->where('cancelado', true);
            $totais['cancelados'] += (int) $paxCanc->count();
            $totais['cancelados_valor'] += (float) (clone $paxCanc)->sum('valor_total');
        }

        $ids = (clone $base)->select('id');
        $porMeio = Pagamento::where('modulo', 'passagem')->whereIn('origem_id', $ids)
            ->where('meio', '!=', 'desconto')->selectRaw('meio, SUM(valor) as valor')
            ->groupBy('meio')->pluck('valor', 'meio');

        // Lista mostra ativas + canceladas juntas (os totais acima continuam só com ativas).
        $fLista = $f;
        if (($fLista['cancelada'] ?? '') === '') $fLista['cancelada'] = 'todas';
        $passagens = Passagem::filtrar($fLista)->with(['embarcacao', 'operador', 'embarcouPor', 'passageiros:id,passagem_id,nome,poltrona,papel,cancelado'])->orderByDesc('id')->paginate(50)->withQueryString();

        return view('passagens.index', [
            'passagens' => $passagens,
            'totais' => $totais,
            'porMeio' => $porMeio,
            'f' => $f,
            'localidades' => TabelaPreco::select('origem')->distinct()->pluck('origem')
                ->merge(Rota::todasLocalidades())->unique()->sort()->values(),
            'embarcacoes' => Embarcacao::where('ativo', true)->orderBy('nome')->get(),
            'datasViagem' => Passagem::where('cancelada', false)->whereNotNull('data_viagem')
                ->when($f['embarcacao_id'] ?? null, fn ($q, $v) => $q->where('embarcacao_id', $v))
                ->whereBetween('data_viagem', [now()->subMonthsNoOverflow(3)->toDateString(), now()->addMonthsNoOverflow(3)->toDateString()])
                ->select('data_viagem')->distinct()->orderByDesc('data_viagem')->pluck('data_viagem'),
            'verTodos' => $verTodos,
            'escopo' => $escopo,
            'tiposPax' => \App\Models\TipoPassageiro::where('ativo', true)->orderBy('eh_normal', 'desc')->orderBy('nome')->get(['id', 'nome']),
            'clientesFaturado' => \App\Models\ClienteFaturado::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'empresa']),
            'vendedores' => $verTodos ? \App\Models\User::where('empresa_id', Tenant::id())->orderBy('is_revendedor')->orderBy('name')->get(['id', 'name', 'is_revendedor']) : collect(),
        ]);
    }

    protected function filtros(Request $request): array
    {
        $f = $request->only(['inicio', 'fim', 'hora_inicio', 'hora_fim', 'origem', 'destino', 'embarcacao_id', 'viagem_id', 'data_viagem', 'status_pagamento', 'embarcou', 'forma', 'busca', 'user_id', 'vendedor_id', 'cancelada', 'cliente_faturado_id']);
        if (! $request->has('inicio') && ! $request->filled('busca')) {
            $f['inicio'] = now()->subDays(7)->toDateString();
            $f['fim'] = now()->toDateString();
        }
        return $f;
    }

    // ---------------- CADASTRO ----------------
    public function create(Request $request)
    {
        return view('passagens.create', $this->dadosFormVenda($request));
    }

    /** Monta todos os dados do formulário de venda — reusado pelo Atendimento (painel nativo). */
    public function dadosFormVenda(Request $request): array
    {
        $u = auth()->user();
        TrechoPreco::curarOrfaos();
        // Só aparecem no seletor de venda os transportes que têm ROTA ativa COM trecho precificado.
        $precoPares = TrechoPreco::where('ativo', true)->get(['origem', 'destino'])
            ->map(fn ($tp) => mb_strtolower(trim((string) $tp->origem)).'|'.mb_strtolower(trim((string) $tp->destino)))
            ->filter(fn ($k) => $k !== '|')->unique()->flip();
        $embComVenda = Rota::where('ativo', true)->whereNotNull('embarcacao_id')->with('paradas:id,rota_id,localidade')->get()
            ->filter(function ($r) use ($precoPares) {
                $locs = $r->paradas->pluck('localidade')->filter()->map(fn ($l) => mb_strtolower(trim((string) $l)))->values()->all();
                $n = count($locs);
                for ($i = 0; $i < $n; $i++) {
                    for ($j = 0; $j < $n; $j++) {
                        if ($i === $j) continue;
                        if (isset($precoPares[$locs[$i].'|'.$locs[$j]])) return true;
                    }
                }
                return false;
            })->pluck('embarcacao_id')->unique()->values();
        $embarcacoes = Embarcacao::where('ativo', true)->whereIn('id', $embComVenda)->orderBy('nome')
            ->get(['id', 'nome', 'usa_poltrona', 'poltronas_qtd', 'poltronas_colunas', 'regra_idade']);
        $colBase = ['id', 'nome', 'cor', 'percentual', 'pct_sobre', 'paga_taxa', 'modo', 'eh_normal', 'tarifa_padrao', 'alimentacao_padrao', 'idade_min', 'idade_max', 'gratuito', 'pcd', 'idoso_meia', 'colo'];
        // resiliente: só seleciona colunas que existem (evita 500 se uma migration ainda não rodou no banco do cliente)
        $existentes = \Illuminate\Support\Facades\Schema::getColumnListing('tipos_passageiro');
        $colunas = array_values(array_intersect($colBase, $existentes)) ?: ['id', 'nome', 'percentual', 'paga_taxa', 'modo', 'eh_normal'];
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
        $rotas = Tenant::empresa()->usaViagens() ? Rota::paraSelecao() : collect();
        // ordena as cidades pela ORDEM DAS PARADAS das rotas (ordem da viagem), nao alfabetica
        $ordemParadas = $rotas->flatMap(fn ($r) => $r->paradas->sortBy('ordem')->pluck('localidade'))->filter()->unique()->values();
        if ($ordemParadas->isNotEmpty()) {
            $localidades = $ordemParadas->filter(fn ($c) => $localidades->contains($c))
                ->merge($localidades->reject(fn ($c) => $ordemParadas->contains($c))->sort())
                ->unique()->values();
        }
        // linhas (com paradas) para o JS resolver a viagem a partir de origem/destino (igual ao encomenda)
        $linhas = $rotas->map(fn ($r) => [
            'id' => $r->id,
            'nome' => $r->nome,
            'embarcacao_id' => $r->embarcacao_id,
            'paradas' => $r->paradas->map(fn ($p) => ['n' => $p->localidade, 'd' => $p->dia_semana, 'h' => $p->hora ? substr($p->hora, 0, 5) : null])->values(),
        ])->values();

        // Pré-preenchimento (quando aberto pelo botão "Vender" do Atendimento). Não afeta a venda normal.
        $prefill = null;
        if ($request->filled('origem') || $request->filled('destino')) {
            $dataIso = null; $dq = trim((string) $request->query('data'));
            if ($dq !== '') {
                foreach (['d/m/Y', 'd/m/y', 'Y-m-d'] as $fmt) {
                    try { $dd = \Carbon\Carbon::createFromFormat($fmt, $dq); if ($dd) { $dataIso = $dd->format('Y-m-d'); break; } } catch (\Throwable $e) {}
                }
            }
            $prefill = [
                'origem' => (string) $request->query('origem', ''),
                'destino' => (string) $request->query('destino', ''),
                'data' => $dataIso,
                'poltrona' => (string) $request->query('poltrona', ''),
                'nome' => (string) $request->query('nome', ''),
            ];
        }

        return [
            'prefill' => $prefill,
            'embarcacoes' => $embarcacoes,
            'regrasIdade' => $embarcacoes->mapWithKeys(fn ($e) => [(string) $e->id => ($e->bandasIdade() ?: [])])->filter(fn ($v) => ! empty($v)),
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
            'formasPermitidas' => $u->formasPermitidas(),
            'formaInicial' => $u->formasPermitidas()[0] ?? 'avista',
            'podeParcial' => $u->podeParcial(),
            'podeRetroativo' => $u->podeRetroativo(),
            'mostraComprador' => (bool) Tenant::empresa()->config('comprador_passagem', true),
            'usaTaxaEmbarque' => (bool) Tenant::empresa()->config('usa_taxa_embarque', false),
            'usaAlimentacao' => (bool) Tenant::empresa()->config('usa_alimentacao', true),
            'labelTaxa' => Tenant::empresa()->config('label_taxa_embarque') ?: 'Taxa de embarque',
            'labelAlim' => Tenant::empresa()->config('label_alimentacao') ?: 'Alimentação',
            'soAvista' => $u->revendedorDono() !== null,
            'agentes' => ($u->revendedorDono() === null && Tenant::empresa()?->config('usa_revendedores'))
                ? \App\Models\User::where('is_revendedor', true)->where('ativo', true)->orderBy('name')->get(['id', 'name'])
                : collect(),
        ];
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        if (auth()->user()->revendedorDono() !== null) { $data['forma'] = 'avista'; }

        // Trava por viagem: serializa a checagem+criação pra NÃO haver venda duplicada de poltrona
        // mesmo com dois operadores/agentes salvando no mesmo instante.
        $usaPol = ! empty($data['embarcacao_id']) && optional(Embarcacao::find($data['embarcacao_id']))->usa_poltrona;
        $lockKey = 'pol_'.Tenant::id().'_'.($data['embarcacao_id'] ?? 0).'_'.($data['viagem_id'] ?? $data['data_viagem'] ?? $data['rota_id'] ?? 'x');
        $locked = false;
        try {
            if ($usaPol) { \Illuminate\Support\Facades\DB::select('SELECT GET_LOCK(?, 5) AS l', [$lockKey]); $locked = true; }
            $this->checarPoltronas($data);
            try {
                $p = app(\App\Services\PassagemService::class)->criar($data, auth()->id(), $request->input('uuid'));
            } catch (\RuntimeException $e) {
                abort(422, $e->getMessage());
            }
        } finally {
            if ($locked) \Illuminate\Support\Facades\DB::select('SELECT RELEASE_LOCK(?) AS r', [$lockKey]);
        }
        // solta as reservas (holds) deste operador nas poltronas recém-vendidas
        if ($usaPol) {
            $seats = collect($data['passageiros'])->pluck('poltrona')->filter()->reject(fn ($s) => mb_strtoupper(trim((string) $s)) === 'LIVRE')->values()->all();
            if ($seats) \App\Models\PoltronaHold::where('embarcacao_id', $data['embarcacao_id'])->where('user_id', auth()->id())->whereIn('poltrona', $seats)->delete();
        }
        session(['ultima_origem_pass' => $data['origem']]);

        $imprimir = $request->input('imprimir', 'navegador');
        return redirect()->route('passagens.show', ['passagem' => $p, 'print' => $imprimir === 'navegador' ? 1 : 0])
            ->with('ok', "Passagem {$p->numeroFormatado()} salva.");
    }

    // ---------------- SUÍTES / CAMAROTES ----------------
    /**
     * Camarote já ocupado num trecho que se cruza, na mesma viagem?
     * Mesma lógica das poltronas: Manaus→Tefé bloqueia Coari→Tefé (trechos se sobrepõem),
     * mas não bloqueia um trecho que não se cruza. Sem rota conhecida, qualquer venda do
     * camarote na viagem conta como conflito (por segurança).
     */
    protected function camaroteConflito(int $camaroteId, $rotaId, $dataViagem, $viagemId, string $origem, string $destino): ?Passagem
    {
        if (! $viagemId && ! $dataViagem) return null; // sem viagem/data não dá pra escopar

        $rota = $rotaId ? Rota::find($rotaId) : null;
        if (! $rota) {
            $rota = Rota::with('paradas')->get()->first(function ($r) use ($origem, $destino) {
                $locs = array_map('mb_strtolower', $r->localidades());
                return in_array(mb_strtolower($origem), $locs, true) && in_array(mb_strtolower($destino), $locs, true);
            });
        }
        $ordem = $rota ? array_map('mb_strtolower', $rota->localidades()) : [];
        $idx = fn ($c) => array_search(mb_strtolower((string) $c), $ordem, true);
        $reqS = $idx($origem); $reqD = $idx($destino);
        $temTrecho = $ordem && $reqS !== false && $reqD !== false;
        $reqMin = $temTrecho ? min($reqS, $reqD) : null;
        $reqMax = $temTrecho ? max($reqS, $reqD) : null;

        $q = Passagem::where('tipo', 'camarote')->where('camarote_id', $camaroteId)->where('cancelada', false)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when(! $viagemId && $dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem));

        foreach ($q->get(['id', 'numero', 'numero_completo', 'origem', 'destino', 'comprador_nome']) as $pg) {
            if ($temTrecho) {
                $c = $idx($pg->origem); $d = $idx($pg->destino);
                if ($c !== false && $d !== false) {
                    [$cMin, $cMax] = [min($c, $d), max($c, $d)];
                    if (! ($reqMin < $cMax && $cMin < $reqMax)) continue; // não cruza -> livre
                }
            }
            return $pg; // conflito
        }
        return null;
    }

    public function camaroteCreate()
    {
        $u = auth()->user();
        $lista = Camarote::where('ativo', true)->with('precos')->orderBy('ordem')->orderBy('nome')->get();
        $camarotes = $lista->map(function ($c) {
            $precos = [];
            foreach ($c->precos as $pr) {
                $precos[mb_strtolower($pr->origem.'|'.$pr->destino)] = (float) $pr->valor;
                $precos[mb_strtolower($pr->destino.'|'.$pr->origem)] = (float) $pr->valor;
            }
            return [
                'id' => $c->id, 'nome' => $c->nome, 'capacidade' => (int) $c->capacidade,
                'descricao' => $c->descricao, 'adicional' => (float) $c->valor_adicional,
                'precos' => $precos,
                'trechos' => $c->precos->map(fn ($pr) => ['o' => $pr->origem, 'd' => $pr->destino, 'v' => (float) $pr->valor])->values(),
            ];
        })->values();

        $locs = $lista->flatMap(fn ($c) => $c->precos->pluck('origem')->merge($c->precos->pluck('destino')))
            ->merge(Rota::todasLocalidades())->filter()->unique()->sort()->values();
        $cidadesPerm = $u->cidadesPermitidas();
        if ($cidadesPerm) $locs = $locs->intersect($cidadesPerm)->values();

        $rotas = Tenant::empresa()->usaViagens() ? Rota::paraSelecao() : collect();
        $linhas = $rotas->map(fn ($r) => [
            'id' => $r->id, 'nome' => $r->nome,
            'paradas' => $r->paradas->map(fn ($pp) => ['n' => $pp->localidade, 'd' => $pp->dia_semana, 'h' => $pp->hora ? substr($pp->hora, 0, 5) : null])->values(),
        ])->values();

        return view('passagens.camarote', [
            'camarotes' => $camarotes,
            'localidades' => $locs,
            'rotas' => $rotas,
            'linhas' => $linhas,
            'ultimaOrigem' => session('ultima_origem_pass') ?: (Tenant::empresa()->cidade ?: $locs->first()),
            'ultimaSuite' => session('ultima_suite_cam'),
            'checkinAuto' => (bool) $u->checkin_auto,
            'soAvista' => $u->revendedorDono() !== null,
        ]);
    }

    /** Lista de CAMAROTES com filtro detalhado (igual Passagens/embarque, só tipo camarote). */
    public function camaroteLista(Request $request)
    {
        $f = $this->filtros($request);
        $base = Passagem::filtrar($f)->where('tipo', 'camarote');

        $totais = [
            'qtd'         => (clone $base)->count(),
            'passageiros' => (clone $base)->sum('qtd_passageiros'),
            'total'       => (clone $base)->sum('total'),
            'recebido'    => (clone $base)->sum('pago'),
            'a_receber'   => (clone $base)->sum('devido'),
        ];

        $passagens = (clone $base)->with(['embarcacao', 'operador', 'camarote'])
            ->orderByDesc('id')->paginate(50)->withQueryString();

        return view('passagens.camarote_lista', [
            'passagens' => $passagens,
            'totais' => $totais,
            'f' => $f,
            'localidades' => \App\Models\TabelaPreco::select('origem')->distinct()->pluck('origem')
                ->merge(Rota::todasLocalidades())->filter()->unique()->sort()->values(),
            'camarotes' => Camarote::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'operadores' => \App\Models\User::where('ativo', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Lista de passageiros (manifesto) do filtro atual — inclui ocupantes de camarote. */
    public function manifesto(Request $request)
    {
        $f = $this->filtros($request);
        $passagens = Passagem::filtrar($f)->where('cancelada', false)->with(['passageiros', 'camarote', 'embarcacao', 'operador', 'pagamentos', 'revendedor:id,name', 'clienteFaturado:id,nome,empresa'])
            ->orderBy('data_viagem')->orderBy('origem')->orderBy('id')->get();

        $linhas = collect();
        foreach ($passagens as $p) {
            foreach ($p->passageiros as $pax) {
                $linhas->push((object) [
                    'nome' => $pax->nome,
                    'documento' => $pax->documento,
                    'nacionalidade' => $pax->nacionalidade ?: 'Brasileira',
                    'nascimento' => $pax->nascimento,
                    'sexo' => $pax->sexo,
                    'telefone' => $pax->telefone,
                    'origem' => $p->origem,
                    'destino' => $p->destino,
                    'trecho' => $p->origem.'/'.$p->destino,
                    'data' => $p->data_viagem,
                    'numero' => $p->numeroFormatado(),
                    'ticket' => $p->numeroFormatado(),
                    'tipo' => $p->tipo === 'camarote' ? ('Camarote'.($p->camarote?->nome ? ' '.$p->camarote->nome : '')) : ($pax->papel ?: $pax->tipo),
                    'papel' => $pax->papel,
                    'poltrona' => $pax->poltrona,
                    'checkin' => (bool) $pax->checkin,
                    'embarcou' => (bool) $p->embarcou,
                ]);
            }
        }

        // tabela padrao (igual prestacao) para a lista de embarque/desembarque
        $linhasPrest = \App\Support\ManifestoCaixa::dados($passagens)['linhas'];

        // cabecalho (modelo oficial)
        $embs = $passagens->map(fn ($p) => $p->embarcacao?->nome)->filter()->unique()->values();
        $datas = $passagens->pluck('data_viagem')->filter()->unique()->values();

        $rota = null;
        $rotaId = $passagens->pluck('rota_id')->filter()->first();
        if ($rotaId) $rota = \App\Models\Rota::find($rotaId);
        $escalas = $rota ? array_values($rota->localidades()) : $passagens->pluck('destino')->filter()->unique()->values()->all();
        $primeiro = ! empty($escalas) ? $escalas[0] : ($passagens->pluck('origem')->filter()->first() ?: '—');
        $ultimo = ! empty($escalas) ? end($escalas) : ($passagens->pluck('destino')->filter()->last() ?: '—');

        $cab = [
            'embarcacao' => $embs->count() === 1 ? $embs->first() : ($embs->count() > 1 ? 'Vários transportes' : '—'),
            'data' => $datas->count() === 1 ? \Carbon\Carbon::parse($datas->first()) : null,
            'periodo' => (($f['inicio'] ?? '') || ($f['fim'] ?? '')) ? (($f['inicio'] ?? '…').' a '.($f['fim'] ?? '…')) : null,
            'rota' => $primeiro.'/'.$ultimo,
            'escalas' => $escalas,
            'destino' => $ultimo,
        ];

        $tipo = $request->input('tipo');
        $view = match ($tipo) {
            'trecho' => 'passagens.lista_trecho',
            'poltrona' => 'passagens.lista_poltrona',
            'federal' => 'passagens.lista_federal',
            default => 'passagens.manifesto',
        };

        return view($view, [
            'linhas' => $linhas,
            'linhasPrest' => $linhasPrest,
            'f' => $f,
            'empresa' => Tenant::empresa(),
            'cab' => $cab,
        ]);
    }

    /** Mapa de ocupação/reservas dos camarotes por data de viagem (+ baixa dos reservados). */
    public function camarotesMapa(Request $request)
    {
        $data = $request->input('data');
        $camarotes = Camarote::where('ativo', true)->orderBy('ordem')->orderBy('nome')->get();

        $vendas = Passagem::where('tipo', 'camarote')->where('cancelada', false)
            ->when($data, fn ($q) => $q->whereDate('data_viagem', $data))
            ->when(! $data, fn ($q) => $q->whereDate('data_viagem', '>=', today()))
            ->with('passageiros')
            ->orderBy('data_viagem')->orderBy('origem')->get()
            ->groupBy('camarote_id');

        return view('passagens.camarotes', [
            'camarotes' => $camarotes,
            'vendas' => $vendas,
            'data' => $data,
        ]);
    }

    public function camaroteStore(Request $request)
    {
        $data = $request->validate([
            'uuid' => 'nullable|uuid',
            'camarote_id' => 'required|integer',
            'origem' => 'required|string|max:80',
            'destino' => 'required|string|max:80',
            'rota_id' => 'nullable|integer',
            'viagem_id' => 'nullable|integer',
            'data_viagem' => 'nullable|date',
            'comprador_nome' => 'nullable|string|max:120',
            'comprador_telefone' => 'nullable|string|max:30',
            'comprador_documento' => 'nullable|string|max:20',
            'forma' => 'required|in:avista,areceber,faturado,online',
            'observacao' => 'nullable|string|max:1000',
            'desconto' => 'nullable|numeric|min:0',
            'ocupantes' => 'required|array|min:1',
            'ocupantes.*.nome' => 'required|string|max:120',
            'ocupantes.*.documento' => 'nullable|string|max:20',
            'ocupantes.*.nascimento' => 'nullable|date',
            'ocupantes.*.telefone' => 'nullable|string|max:30',
            'ocupantes.*.adicional' => 'nullable|numeric|min:0',
            'ocupantes.*.checkin' => 'nullable|boolean',
            'pagamentos' => 'nullable|array',
            'pagamentos.*.meio' => 'required_with:pagamentos|in:dinheiro,pix,debito,credito',
            'pagamentos.*.valor' => 'required_with:pagamentos|numeric|min:0',
            'pagamentos.*.proprietario' => 'nullable|boolean',
            'imprimir' => 'nullable|in:navegador,direto,nao',
        ]);

        if (auth()->user()->revendedorDono() !== null) $data['forma'] = 'avista';

        try {
            $payload = app(\App\Services\CamaroteVenda::class)->montarPayload($data);
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        $p = app(\App\Services\PassagemService::class)->criar($payload, auth()->id(), $request->input('uuid'));
        session(['ultima_origem_pass' => $data['origem'], 'ultima_suite_cam' => $data['camarote_id']]);
        $imprimir = $request->input('imprimir', 'navegador');
        $nomeCam = $p->camarote?->nome ?: 'Camarote';
        return redirect()->route('passagens.show', ['passagem' => $p, 'print' => $imprimir === 'navegador' ? 1 : 0])
            ->with('ok', "{$nomeCam} · passagem {$p->numeroFormatado()} salva.");
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
            'forma' => 'required|in:avista,areceber,faturado,online',
            'observacao' => 'nullable|string|max:1000',
            'desconto' => 'nullable|numeric|min:0',
            'agente_id' => 'nullable|integer',
            'agente_acerto' => 'nullable|boolean',
            'cliente_faturado_id' => 'nullable|integer',
            'cobrar_dias' => 'nullable|integer|min:1|max:365',
            'passageiros' => 'required|array|min:1',
            'passageiros.*.nome' => 'required|string|max:120',
            'passageiros.*.tipo' => 'required|string|max:60',
            'passageiros.*.percentual' => 'nullable|integer|min:0|max:100',
            'passageiros.*.documento' => 'nullable|string|max:20',
            'passageiros.*.doc_tipo' => 'nullable|string|max:20',
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
            'passageiros.*.papel' => 'nullable|string|max:20',
            'passageiros.*.documentos' => 'nullable|array|max:20',
            'passageiros.*.documentos.*' => 'nullable',
            'documentos_gerais' => 'nullable|array|max:20',
            'documentos_gerais.*' => 'nullable',
            'passageiros.*.checkin' => 'nullable|boolean',
            'passageiros.*.observacao' => 'nullable|string|max:200',
            'passageiros.*.bebe_colo' => 'nullable|array',
            'passageiros.*.bebe_colo.nome' => 'nullable|string|max:120',
            'passageiros.*.bebe_colo.documento' => 'nullable|string|max:30',
            'passageiros.*.bebe_colo.nascimento' => 'nullable|date',
            'pagamentos' => 'nullable|array',
            'pagamentos.*.meio' => 'required_with:pagamentos|in:dinheiro,pix,debito,credito',
            'pagamentos.*.valor' => 'required_with:pagamentos|numeric|min:0',
            'pagamentos.*.proprietario' => 'nullable|boolean',
            'imprimir' => 'nullable|in:navegador,direto,nao',
        ]);
    }

    /** Impede vender a mesma poltrona duas vezes na mesma viagem/data. */
    protected function checarPoltronas(array $data): void
    {
        $emb = ($data['embarcacao_id'] ?? null) ? Embarcacao::find($data['embarcacao_id']) : null;
        if (! $emb || ! $emb->usa_poltrona) return;
        $assentos = collect($data['passageiros'])->pluck('poltrona')->filter()->reject(fn ($s) => mb_strtoupper(trim((string) $s)) === 'LIVRE')->values();
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
        $viagemAberta = false;
        if ($viagemId) { $vg = \App\Models\Viagem::find($viagemId); $viagemAberta = ! $vg || $vg->data_partida === null; }
        $q = Passagem::where('embarcacao_id', $embarcacaoId)->where('cancelada', false)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when(($viagemAberta || ! $viagemId) && $dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))
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
        foreach ($q->with(['passageiros' => fn ($q) => $q->where('cancelado', false)->select('id', 'passagem_id', 'poltrona')])->get(['id', 'origem', 'destino']) as $pg) {
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

        // Poltronas TRAVADAS (não vendáveis) entram como ocupadas — só as travas que valem pro canal de quem vende.
        $uu = auth()->user();
        $canalU = ($uu && $uu->ehRevendedor()) ? 'revendedor' : 'operador';
        $bq = \App\Models\PoltronaBloqueada::where('embarcacao_id', $embarcacaoId)->where('ativo', true)
            ->aplicaveis($canalU, $uu?->id);
        if ($dataViagem) $bq->where(fn ($w) => $w->whereNull('data_viagem')->orWhereDate('data_viagem', $dataViagem));
        foreach ($bq->pluck('poltrona') as $pol) if ($pol !== null && $pol !== '') $ocup->push((string) $pol);

        return $ocup->unique()->values();
    }

    /** JSON: poltronas ocupadas de uma embarcação/viagem (AJAX da tela de cadastro). */
    /** Uso da cota de gratuidade no trecho (para o indicador na venda). */
    public function gratuidade(Request $request)
    {
        $svc = app(\App\Services\PassagemService::class);
        $args = [
            Tenant::id(),
            $request->integer('viagem_id') ?: null,
            $request->input('rota_id') ?: null,
            $request->input('data_viagem') ?: null,
            $request->integer('embarcacao_id') ?: null,
            (string) $request->input('origem', ''),
            (string) $request->input('destino', ''),
        ];
        $resumo = fn ($x) => ['ativo' => $x['ativo'], 'cota' => $x['cota'], 'usados' => $x['usados'], 'restante' => $x['restante']];
        $idoso = $svc->cotaUso('idoso', ...$args);   // pool unico: gratuito + meia
        $p = $svc->cotaUso('pcd', ...$args);
        $ri = $resumo($idoso);
        // 'idoso' = pool compartilhado; aliases gratuito/idoso_meia apontam pro mesmo pool (compat)
        return response()->json($ri + ['idoso' => $ri, 'pcd' => $resumo($p), 'gratuito' => $ri, 'idoso_meia' => $ri]);
    }

    public function assentos(Request $request)
    {
        $emb = Embarcacao::findOrFail($request->integer('embarcacao_id'));
        return [
            'usa_poltrona' => (bool) $emb->usa_poltrona,
            'qtd' => (int) $emb->poltronas_qtd,
            'colunas' => (int) ($emb->poltronas_colunas ?: 4),
            'layout' => (string) ($emb->poltronas_lista ?? ''),
            'ocupadas' => $this->ocupadas($emb->id, $request->input('data_viagem'), $request->input('viagem_id'), $request->input('rota_id'), $request->input('origem'), $request->input('destino'))->values(),
            'reservadas' => $this->reservadas($emb->id, $request->input('data_viagem'), $request->input('viagem_id'), $request->input('rota_id'), $request->input('origem'), $request->input('destino'), auth()->id())->values(),
            'reservadasInfo' => $this->reservadasDetalhe($emb->id, $request->input('data_viagem'), $request->input('viagem_id'), $request->input('rota_id'), $request->input('origem'), $request->input('destino'), auth()->id()),
            'travadas' => auth()->user()->ehRevendedor() ? [] : $this->travadas($emb->id, $request->input('data_viagem')),
        ];
    }

    /** Poltronas TRAVADAS (não vendáveis) com a observação — trava a viagem inteira (todo o trecho) daquela data; data nula = todas as viagens. */
    protected function travadas($embarcacaoId, $dataViagem): array
    {
        $uu = auth()->user();
        $q = PoltronaBloqueada::where('embarcacao_id', $embarcacaoId)->where('ativo', true)
            ->aplicaveis(($uu && $uu->ehRevendedor()) ? 'revendedor' : 'operador', $uu?->id);
        if ($dataViagem) $q->where(fn ($w) => $w->whereNull('data_viagem')->orWhereDate('data_viagem', $dataViagem));
        return $q->get()
            ->map(fn ($b) => ['poltrona' => (string) $b->poltrona, 'motivo' => trim(((string) ($b->motivo ?? '')).' ('.$b->escopoLabel().')'), 'todas' => $b->data_viagem === null])
            ->values()->all();
    }

    /** Poltronas RESERVADAS (hold temporário) por OUTROS operadores/agentes, no mesmo trecho. */
    protected function reservadas($embarcacaoId, $dataViagem, $viagemId, $rotaId = null, $origem = null, $destino = null, $exceptUserId = null): \Illuminate\Support\Collection
    {
        $viagemAberta = false;
        if ($viagemId) { $vg = \App\Models\Viagem::find($viagemId); $viagemAberta = ! $vg || $vg->data_partida === null; }
        $q = \App\Models\PoltronaHold::ativo()->where('embarcacao_id', $embarcacaoId)
            // casa pela viagem OU pela data (a reserva do agente do WhatsApp pode ter sido gravada só com a data)
            ->when($viagemId && ! $viagemAberta, fn ($q) => $q->where(fn ($w) => $w->where('viagem_id', $viagemId)
                ->when($dataViagem, fn ($x) => $x->orWhere(fn ($y) => $y->where('user_id', 0)->whereDate('data_viagem', $dataViagem)))))
            ->when($viagemId && $viagemAberta, fn ($q) => $q->where('viagem_id', $viagemId))
            // reserva do agente (user_id 0) gravada sem data também aparece: melhor avisar o operador do que esconder
            ->when(($viagemAberta || ! $viagemId) && $dataViagem, fn ($q) => $q->where(fn ($w) => $w->whereDate('data_viagem', $dataViagem)
                ->orWhere(fn ($y) => $y->where('user_id', 0)->whereNull('data_viagem'))))
            ->when($exceptUserId, fn ($q) => $q->where('user_id', '!=', $exceptUserId));

        $ordem = [];
        if ($rotaId && ($rota = \App\Models\Rota::find($rotaId))) $ordem = array_map('mb_strtolower', $rota->localidades());
        $idx = fn ($c) => array_search(mb_strtolower((string) $c), $ordem, true);
        $reqS = $origem !== null ? $idx($origem) : false;
        $reqD = $destino !== null ? $idx($destino) : false;
        $temTrecho = $ordem && $reqS !== false && $reqD !== false;
        $reqMin = $temTrecho ? min($reqS, $reqD) : null;
        $reqMax = $temTrecho ? max($reqS, $reqD) : null;

        $res = collect();
        foreach ($q->get(['poltrona', 'origem', 'destino']) as $h) {
            if ($temTrecho) {
                $c = $idx($h->origem); $d = $idx($h->destino);
                if ($c !== false && $d !== false) {
                    [$cMin, $cMax] = [min($c, $d), max($c, $d)];
                    if (! ($reqMin < $cMax && $cMin < $reqMax)) continue;
                }
            }
            if ($h->poltrona) $res->push((string) $h->poltrona);
        }
        return $res->unique()->values();
    }

    /** Igual reservadas(), mas retorna [poltrona => nome de quem está segurando agora]. user_id 0 = WhatsApp (agente). */
    protected function reservadasDetalhe($embarcacaoId, $dataViagem, $viagemId, $rotaId = null, $origem = null, $destino = null, $exceptUserId = null): array
    {
        $viagemAberta = false;
        if ($viagemId) { $vg = \App\Models\Viagem::find($viagemId); $viagemAberta = ! $vg || $vg->data_partida === null; }
        $q = \App\Models\PoltronaHold::ativo()->where('embarcacao_id', $embarcacaoId)
            // casa pela viagem OU pela data (a reserva do agente do WhatsApp pode ter sido gravada só com a data)
            ->when($viagemId && ! $viagemAberta, fn ($q) => $q->where(fn ($w) => $w->where('viagem_id', $viagemId)
                ->when($dataViagem, fn ($x) => $x->orWhere(fn ($y) => $y->where('user_id', 0)->whereDate('data_viagem', $dataViagem)))))
            ->when($viagemId && $viagemAberta, fn ($q) => $q->where('viagem_id', $viagemId))
            // reserva do agente (user_id 0) gravada sem data também aparece: melhor avisar o operador do que esconder
            ->when(($viagemAberta || ! $viagemId) && $dataViagem, fn ($q) => $q->where(fn ($w) => $w->whereDate('data_viagem', $dataViagem)
                ->orWhere(fn ($y) => $y->where('user_id', 0)->whereNull('data_viagem'))))
            ->when($exceptUserId, fn ($q) => $q->where('user_id', '!=', $exceptUserId));

        $ordem = [];
        if ($rotaId && ($rota = \App\Models\Rota::find($rotaId))) $ordem = array_map('mb_strtolower', $rota->localidades());
        $idx = fn ($c) => array_search(mb_strtolower((string) $c), $ordem, true);
        $reqS = $origem !== null ? $idx($origem) : false;
        $reqD = $destino !== null ? $idx($destino) : false;
        $temTrecho = $ordem && $reqS !== false && $reqD !== false;
        $reqMin = $temTrecho ? min($reqS, $reqD) : null;
        $reqMax = $temTrecho ? max($reqS, $reqD) : null;

        $holds = $q->get(['poltrona', 'origem', 'destino', 'user_id']);
        $uids = $holds->pluck('user_id')->filter(fn ($u) => (int) $u > 0)->unique()->all();
        $nomes = $uids ? \App\Models\User::withoutGlobalScopes()->whereIn('id', $uids)->pluck('name', 'id') : collect();

        $out = [];
        foreach ($holds as $h) {
            if ($temTrecho) {
                $c = $idx($h->origem); $d = $idx($h->destino);
                if ($c !== false && $d !== false) {
                    [$cMin, $cMax] = [min($c, $d), max($c, $d)];
                    if (! ($reqMin < $cMax && $cMin < $reqMax)) continue;
                }
            }
            if (! $h->poltrona) continue;
            $out[(string) $h->poltrona] = (int) $h->user_id === 0 ? 'WhatsApp (agente)' : (string) ($nomes[$h->user_id] ?? 'Operador');
        }
        return $out;
    }

    protected function ocupacaoJson($embId, array $data, $viagemId, $uid): array
    {
        return [
            'ocupadas' => $this->ocupadas($embId, $data['data_viagem'] ?? null, $viagemId, $data['rota_id'] ?? null, $data['origem'] ?? null, $data['destino'] ?? null)->values(),
            'reservadas' => $this->reservadas($embId, $data['data_viagem'] ?? null, $viagemId, $data['rota_id'] ?? null, $data['origem'] ?? null, $data['destino'] ?? null, $uid)->values(),
            'reservadasInfo' => $this->reservadasDetalhe($embId, $data['data_viagem'] ?? null, $viagemId, $data['rota_id'] ?? null, $data['origem'] ?? null, $data['destino'] ?? null, $uid),
            'travadas' => (auth()->user() && auth()->user()->ehRevendedor()) ? [] : $this->travadas($embId, $data['data_viagem'] ?? null),
        ];
    }

    /** Reserva/solta poltrona em tempo real (hold temporário). */
    public function poltronaHold(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:hold,release,heartbeat',
            'embarcacao_id' => 'required|integer',
            'viagem_id' => 'nullable|integer',
            'rota_id' => 'nullable|integer',
            'data_viagem' => 'nullable|date',
            'origem' => 'nullable|string|max:80',
            'destino' => 'nullable|string|max:80',
            'poltronas' => 'nullable|array',
            'poltronas.*' => 'string|max:10',
        ]);
        $uid = auth()->id();
        $embId = (int) $data['embarcacao_id'];
        $viagemId = $data['viagem_id'] ?? null;
        if (! $viagemId && ! empty($data['rota_id']) && ($rota = \App\Models\Rota::find($data['rota_id']))) {
            $ref = ! empty($data['data_viagem']) ? \Carbon\Carbon::parse($data['data_viagem']) : now();
            $viagemId = $rota->viagemPara($ref)->id;
        }
        $pols = collect($data['poltronas'] ?? [])->map(fn ($p) => (string) $p)->filter()->unique()->values();
        $expira = now()->addSeconds(60);
        // limpeza barata de expirados antigos
        \App\Models\PoltronaHold::where('expires_at', '<', now()->subMinutes(5))->delete();

        if ($data['action'] === 'release') {
            \App\Models\PoltronaHold::where('embarcacao_id', $embId)->where('user_id', $uid)
                ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
                ->when($pols->isNotEmpty(), fn ($q) => $q->whereIn('poltrona', $pols))
                ->delete();
            return response()->json($this->ocupacaoJson($embId, $data, $viagemId, $uid) + ['ok' => true]);
        }
        if ($data['action'] === 'heartbeat') {
            \App\Models\PoltronaHold::where('embarcacao_id', $embId)->where('user_id', $uid)
                ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
                ->when($pols->isNotEmpty(), fn ($q) => $q->whereIn('poltrona', $pols))
                ->update(['expires_at' => $expira]);
            return response()->json($this->ocupacaoJson($embId, $data, $viagemId, $uid) + ['ok' => true]);
        }
        // hold
        $vendidas = $this->ocupadas($embId, $data['data_viagem'] ?? null, $viagemId, $data['rota_id'] ?? null, $data['origem'] ?? null, $data['destino'] ?? null);
        $outras = $this->reservadas($embId, $data['data_viagem'] ?? null, $viagemId, $data['rota_id'] ?? null, $data['origem'] ?? null, $data['destino'] ?? null, $uid);
        $conflito = [];
        foreach ($pols as $p) {
            if ($vendidas->contains($p) || $outras->contains($p)) { $conflito[] = $p; continue; }
            \App\Models\PoltronaHold::updateOrCreate(
                ['embarcacao_id' => $embId, 'viagem_id' => $viagemId, 'data_viagem' => $data['data_viagem'] ?? null, 'poltrona' => $p, 'user_id' => $uid],
                ['empresa_id' => Tenant::id(), 'origem' => $data['origem'] ?? null, 'destino' => $data['destino'] ?? null, 'expires_at' => $expira],
            );
        }
        return response()->json($this->ocupacaoJson($embId, $data, $viagemId, $uid) + ['ok' => empty($conflito), 'conflito' => $conflito]);
    }

    // ---------------- DETALHE ----------------
    public function show(Passagem $passagem)
    {
        $passagem->load(['passageiros', 'pagamentos.user', 'embarcacao', 'operador', 'embarcouPor']);
        $linhasShow = \App\Support\Tenant::empresa()->usaViagens()
            ? \App\Models\Rota::paraSelecao()->map(fn ($r) => [
                'id' => $r->id, 'nome' => $r->nome, 'embarcacao_id' => $r->embarcacao_id,
                'paradas' => $r->paradas->map(fn ($p) => ['n' => $p->localidade, 'd' => $p->dia_semana, 'h' => $p->hora ? substr($p->hora, 0, 5) : null])->values(),
              ])->values()
            : collect();
        return view('passagens.show', ['p' => $passagem, 'meios' => Pagamento::MEIOS, 'linhasShow' => $linhasShow]);
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
        $passagem->passageiros()->update(['checkin' => true]); // confirmar embarque marca o check-in dos passageiros
        return redirect()->route('passagens.show', $passagem)->with('ok', 'Embarque confirmado.');
    }

    /** Check-in individual de um passageiro (embarque por passageiro, não a compra inteira). */
    /** Salva a observação da etiqueta (digitada no check-in) para um passageiro. */
    public function paxObs(Request $request, Passagem $passagem, PassagemPassageiro $pax)
    {
        abort_unless($passagem->empresa_id === Tenant::id(), 404);
        abort_unless((int) $pax->passagem_id === (int) $passagem->id, 404);
        $obs = trim((string) $request->input('obs', ''));
        $pax->forceFill(['obs_etiqueta' => $obs !== '' ? mb_substr($obs, 0, 200) : null])->save();
        return response()->json(['ok' => true]);
    }

    public function checkinPax(Request $request, Passagem $passagem, $pax)
    {
        abort_unless($passagem->empresa_id === Tenant::id(), 404);
        abort_if($passagem->cancelada, 422, 'Passagem cancelada.');
        abort_if($passagem->devido > 0.009 && $passagem->forma !== 'faturado', 422, 'Registre o pagamento antes do embarque.');

        $p = $passagem->passageiros()->findOrFail($pax);
        $novo = ! $p->checkin;
        $p->forceFill([
            'checkin' => $novo,
            'checkin_em' => $novo ? now() : null,
            'checkin_por' => $novo ? auth()->id() : null,
        ])->save();

        $total = $passagem->passageiros()->count();
        $feitos = $passagem->passageiros()->where('checkin', true)->count();

        // quando todos embarcaram, marca a compra como embarcada (p/ relatórios)
        if ($feitos >= $total && $total > 0 && ! $passagem->embarcou) {
            app(\App\Services\PassagemService::class)->baixa($passagem, ['embarcar' => true], auth()->id());
        }

        return response()->json(['ok' => true, 'checkin' => $novo, 'feitos' => $feitos, 'total' => $total, 'embarcou' => ($feitos >= $total)]);
    }

    /** Comprovante 80mm (impressão/PDF) com QR de check-in. */
    /** Minhas vendas (revendedor/vendedor): resumo do que vendeu no periodo + o que falta acertar. */
    /** Caixa por viagem: manifesto financeiro (passageiros + vendedor + valor + forma) com totais por forma de pagamento. */
    public function caixaViagem(Request $request)
    {
        $f = $this->filtros($request);
        $f['cancelada'] = 'todas'; // inclui as devolvidas/canceladas no relatorio
        // Pré-prestação: tudo que está em aberto do transporte/viagem, sem cortar por período de venda
        if ($request->input('modo') === 'prestacao') { $f['inicio'] = null; $f['fim'] = null; $f['hora_inicio'] = null; $f['hora_fim'] = null; }
        $passagens = Passagem::filtrar($f)
            ->when($request->input('modo') === 'prestacao', fn ($q) => $q->where('acerto_dono', false)) // pré-prestação: só em aberto (não carrega o histórico todo)
            ->with(['passageiros', 'operador', 'pagamentos', 'embarcacao', 'camarote', 'revendedor:id,name', 'clienteFaturado:id,nome,empresa'])
            ->orderBy('data_viagem')->orderBy('origem')->orderBy('id')->get();

        // Modo da impressão: caixa (sem agente externo) · prestacao (todas as vendas) · relatorio (todas as vendas)
        $modo = (string) ($request->input('modo') ?: ($request->boolean('relatorio') ? 'relatorio' : 'caixa'));
        if (! in_array($modo, ['caixa', 'prestacao', 'relatorio'], true)) $modo = 'caixa';

        // Prestação JÁ FECHADA (botão do histórico): mesmo layout da pré-prestação, com as vendas e avulsos daquela prestação.
        $prestFechada = null;
        if ($request->filled('prestacao_id')) {
            abort_unless(auth()->user()->caixaTodos(), 403);
            $prestFechada = \App\Models\Prestacao::findOrFail($request->integer('prestacao_id'));
            $modo = 'prestacao';
            $pid = $prestFechada->id; $embP = $prestFechada->embarcacao_id;
            $datasP = Passagem::where('prestacao_id', $pid)->whereNotNull('data_viagem')->pluck('data_viagem')
                ->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->toDateString())->unique()->values()->all();
            if ($prestFechada->data_viagem) $datasP = array_values(array_unique(array_merge($datasP, [\Illuminate\Support\Carbon::parse($prestFechada->data_viagem)->toDateString()])));
            $passagens = Passagem::where(function ($w) use ($pid, $embP, $datasP) {
                    $w->where('prestacao_id', $pid);
                    // vendas de agente externo das mesmas viagens entram só pra informar (o proprietário cobra direto)
                    if ($embP && $datasP) {
                        $w->orWhere(fn ($a) => $a->where('embarcacao_id', $embP)->whereNotNull('revendedor_id')
                            ->where(function ($d) use ($datasP) { foreach ($datasP as $dt) $d->orWhereDate('data_viagem', $dt); }));
                    }
                })
                ->with(['passageiros', 'operador', 'pagamentos', 'embarcacao', 'camarote', 'revendedor:id,name', 'clienteFaturado:id,nome,empresa'])
                ->orderBy('data_viagem')->orderBy('origem')->orderBy('id')->get();
            // Prestação FECHADA é uma foto do dia do fechamento: passagem cancelada/devolvida DEPOIS aparece como estava
            // (paga, contando no total). O cancelamento posterior entra só na PRÓXIMA prestação, como desconto.
            $fechadaEm = $prestFechada->fechada_em ? \Illuminate\Support\Carbon::parse($prestFechada->fechada_em) : null;
            foreach ($passagens as $pp) {
                if ((int) $pp->prestacao_id !== (int) $pid) continue;
                $mexeu = (bool) $pp->cancelada || $pp->passageiros->contains(fn ($x) => (bool) ($x->cancelado ?? false));
                if (! $mexeu) continue;
                $pp->cancelada = false;
                $pp->transferida = false;
                $pp->devido = 0;
                foreach ($pp->passageiros as $x) $x->cancelado = false;
                $pp->setRelation('pagamentos', $pp->pagamentos
                    ->filter(fn ($pg) => $pg->tipo_mov !== 'saida' && (! $fechadaEm || ! $pg->created_at || $pg->created_at->lte($fechadaEm)))
                    ->each(fn ($pg) => $pg->estornado = false)->values());
            }
        }
        if ($modo === 'caixa') $passagens = $passagens->filter(fn ($p) => ! $p->revendedor_id)->values();
        // Pré-prestação: só o que ainda está EM ABERTO (o que já foi fechado sai pelo botão da prestação no histórico).
        if ($modo === 'prestacao' && ! $prestFechada) $passagens = $passagens->filter(fn ($p) => ! $p->acerto_dono)->values();
        // Agente externo (revendedor) e NEUTRO na prestacao: fica SO na tabela 'Vendas por categoria x forma'. Fora do manifesto, da contagem e das formas.
        $passagensTodas = $passagens;
        if ($modo === 'prestacao') $passagens = $passagens->filter(fn ($p) => ! $p->revendedor_id)->values();
        // Prestação (pré ou já fechada): faturados EM ABERTO (falta pagar) entram na LISTA e na CONTAGEM — igual agente externo. Só NÃO entram nos VALORES em R$.
        if ($modo === 'prestacao') {
            $idsNaLista = $passagensTodas->pluck('id')->all();
            $fatBase = $prestFechada
                ? \App\Models\Passagem::where('embarcacao_id', $embP)->when(! empty($datasP), fn ($q) => $q->where(function ($d) use ($datasP) { foreach ($datasP as $dt) $d->orWhereDate('data_viagem', $dt); }))
                : Passagem::filtrar($f);
            $fatAbertos = $fatBase->where('forma', 'faturado')->where('cancelada', false)->where('transferida', false)
                ->where('devido', '>', 0.009)->when(! empty($idsNaLista), fn ($q) => $q->whereNotIn('id', $idsNaLista))
                ->with(['passageiros', 'operador', 'pagamentos', 'embarcacao', 'camarote', 'revendedor:id,name', 'clienteFaturado:id,nome,empresa'])
                ->orderBy('data_viagem')->orderBy('origem')->orderBy('id')->get();
            if ($fatAbertos->isNotEmpty()) $passagensTodas = $passagensTodas->concat($fatAbertos)->values();
        }

        // Entradas/saídas AVULSAS: caixa = as da agência; prestação = as do transporte/viagem; relatório não tem.
        $avulsos = collect();
        if ($modo !== 'relatorio') {
            $iniA = $f['inicio'] ?? null; $fimA = $f['fim'] ?? null;
            $avQ = Pagamento::where('modulo', 'avulso')->where('estornado', false)->where('momento', 'cadastro')->with(['user:id,name', 'embarcacao:id,nome']);
            $porPeriodo = fn ($q) => $q->when($iniA, fn ($x) => $x->whereDate('created_at', '>=', $iniA))->when($fimA, fn ($x) => $x->whereDate('created_at', '<=', $fimA));
            if ($prestFechada) {
                $avQ->where('prestacao_id', $prestFechada->id);
            } elseif ($modo === 'caixa') {
                $avQ->whereNull('embarcacao_id')->when(! empty($f['user_id']), fn ($q) => $q->where('user_id', $f['user_id']));
                $porPeriodo($avQ);
            } else {
                $avQ->whereNotNull('embarcacao_id')->whereNull('prestacao_id')->when(! empty($f['embarcacao_id']), fn ($q) => $q->where('embarcacao_id', $f['embarcacao_id']));
                if (! empty($f['data_viagem'])) $avQ->whereDate('data_viagem', $f['data_viagem']); else $porPeriodo($avQ);
            }
            $avulsos = $avQ->orderBy('created_at')->limit(500)->get();
        }
        $avEntradas = round((float) $avulsos->where('tipo_mov', 'entrada')->sum('valor'), 2);
        $avSaidas = round((float) $avulsos->where('tipo_mov', 'saida')->sum('valor'), 2);

        $m = \App\Support\ManifestoCaixa::dados($passagens);
        // Prestação: a LISTA e a CONTAGEM entram TODOS (balcão + agente externo + faturado a receber); só os VALORES (R$) ficam só com o balcão recebido ($m).
        $mLista = ($modo === 'prestacao') ? \App\Support\ManifestoCaixa::dados($passagensTodas) : $m;
        $linhas = $mLista['linhas'];
        $totForma = $m['totForma'];
        $totDesconto = $m['totDesconto'];
        $totGeral = $m['totGeral'];
        $totDevolvido = $m['totDevolvido']; $totCancelado = $m['totCancelado'] ?? 0;
        $nPax = $mLista['nPax']; $nIdosos = $mLista['nIdosos']; $nColos = $mLista['nColos']; $nDevolvidas = $mLista['nDevolvidas']; $nCancelados = $mLista['nCancelados'] ?? 0; $nPcd = $mLista['nPcd'] ?? 0; $nOutros = $mLista['nOutros'] ?? 0;

        $totRecebido = array_sum($totForma);
        $embs = $passagensTodas->map(fn ($p) => $p->embarcacao?->nome)->filter()->unique()->values();
        $datas = $passagensTodas->pluck('data_viagem')->filter()->unique()->values();

        // Prestação de contas (soma TODAS as passagens da impressão; comissão certa por transporte).
        $prestacao = null;
        $validas = $passagens->where('cancelada', false)->where('transferida', false);
        // Agente externo (revendedor) NÃO entra nos totais da prestação: o proprietário acerta direto com ele (só informa).
        $vBalcao = $validas->filter(fn ($p) => ! $p->revendedor_id);
        $vAgente = $validas->filter(fn ($p) => (bool) $p->revendedor_id);
        $embIdsP = $validas->pluck('embarcacao_id')->filter()->unique()->values();
        if ($validas->count() > 0 && $embIdsP->count() > 0) {
            $pctsP = Embarcacao::whereIn('id', $embIdsP)->pluck('comissao_empresa_pct', 'id');
            $pgs = $vBalcao->flatMap(fn ($p) => $p->pagamentos)->filter(fn ($pg) => ! $pg->estornado && $pg->meio !== 'desconto');
            // Comissao/prestacao andam so pelo que ENTROU no caixa: faturado (a receber) so conta quando for pago.
            $recebidoDe = fn ($p) => max(0.0, round((float) $p->total - (float) $p->devido, 2));
            $brutoF = round((float) $vBalcao->sum($recebidoDe), 2);
            $comissaoF = round((float) $vBalcao->sum(fn ($p) => $recebidoDe($p) * (int) ($pctsP[$p->embarcacao_id] ?? 0) / 100), 2);
            // Maquineta do PROPRIETÁRIO por forma (o dono já recebeu → abate)
            $somaProp = fn ($meio) => round((float) $pgs->filter(fn ($pg) => (bool) ($pg->pago_proprietario ?? false) && $pg->meio === $meio)->sum('valor'), 2);
            $debProp = $somaProp('debito'); $pixProp = $somaProp('pix'); $credProp = $somaProp('credito'); $dinProp = $somaProp('dinheiro');
            $maqProp = round($debProp + $pixProp + $credProp + $dinProp, 2);
            // Cartão de crédito da EMPRESA (cai depois → abate)
            $cartaoEmpresa = round((float) $pgs->filter(fn ($pg) => ! (bool) ($pg->pago_proprietario ?? false) && $pg->meio === 'credito')->sum('valor'), 2);
            // Saídas operacionais (despesas) no período do filtro
            $saidasF = $avSaidas; $entradasF = $avEntradas; // só os avulsos deste modo (antes somava toda saída do período)
            $liquidoF = round($brutoF - $comissaoF - $maqProp - $cartaoEmpresa - $saidasF + $entradasF, 2);
            $nomeP = $embIdsP->count() === 1 ? (optional(Embarcacao::find($embIdsP->first()))->nome ?? 'Transporte') : 'Todos os transportes';
            $pctP = $embIdsP->count() === 1 ? (int) ($pctsP[$embIdsP->first()] ?? 0) : null;
            $prestacao = (object) [
                'nome' => $nomeP, 'pct' => $pctP, 'bruto' => $brutoF, 'comissao' => $comissaoF,
                'debProp' => $debProp, 'pixProp' => $pixProp, 'credProp' => $credProp, 'dinProp' => $dinProp,
                'cartao' => $cartaoEmpresa, 'saidas' => $saidasF, 'entradas' => $entradasF, 'liquido' => $liquidoF,
                'agQtd' => $vAgente->count(),
                'agBruto' => round((float) $vAgente->sum('total'), 2),
                'agRepasse' => round((float) $vAgente->sum(fn ($p) => (float) $p->total - (float) ($p->acerto_empresa_valor ?? 0)), 2),
            ];
        }

        // DEVOLUÇÃO de passagem já acertada com o proprietário: ele fica devendo a parte dele e isso desconta na PRÓXIMA prestação
        // — mesmo que o transporte ainda não tenha nenhuma venda nova (aí a prestação fica negativa até entrar venda).
        $devolucaoDesc = 0.0;
        if ($prestFechada) {
            $devolucaoDesc = round((float) Pagamento::where('prestacao_id', $prestFechada->id)->where('estornado', false)->where('categoria', 'Débito do dono — abatido')->sum('valor'), 2);
        } elseif ($modo === 'prestacao' && ! empty($f['embarcacao_id']) && (empty($f['data_viagem']) || $validas->count() > 0)) {
            // com filtro de UMA viagem sem venda em aberto (já acertada), o débito não é dela: fica para a prestação em aberto
            $devolucaoDesc = max(0.0, round(
                (float) Pagamento::where('embarcacao_id', $f['embarcacao_id'])->where('estornado', false)->where('categoria', 'Débito do dono (devolução)')->sum('valor')
                - (float) Pagamento::where('embarcacao_id', $f['embarcacao_id'])->where('estornado', false)->where('categoria', 'Débito do dono — abatido')->sum('valor'), 2));
        }
        if ($devolucaoDesc > 0.009) {
            if (! $prestacao && ! empty($f['embarcacao_id']) && ($embDev = Embarcacao::find($f['embarcacao_id']))) {
                $prestacao = (object) [
                    'nome' => $embDev->nome, 'pct' => (int) $embDev->comissao_empresa_pct, 'bruto' => 0.0, 'comissao' => 0.0,
                    'debProp' => 0.0, 'pixProp' => 0.0, 'credProp' => 0.0, 'dinProp' => 0.0,
                    'cartao' => 0.0, 'saidas' => $avSaidas, 'entradas' => $avEntradas, 'liquido' => round($avEntradas - $avSaidas, 2),
                ];
            }
            if ($prestacao) $prestacao->liquido = round((float) $prestacao->liquido - $devolucaoDesc, 2);
        }
        if ($prestacao) {
            $prestacao->devolucao = $devolucaoDesc;
            $prestacao->devolucaoItens = (! $prestFechada && $modo === 'prestacao' && ! empty($f['embarcacao_id'])) ? \App\Support\DebitoDono::itens((int) $f['embarcacao_id']) : collect();
        }

        // Quebra por categoria (Balcão/Proprietário/Agente) × forma, pra prestação.
        $novaCat = fn () => ['qtd' => 0, 'total' => 0.0, 'dinheiro' => 0.0, 'pix' => 0.0, 'debito' => 0.0, 'credito' => 0.0, 'credito_avista' => 0.0, 'credito_parcelado' => 0.0, 'areceber' => 0.0];
        $porCategoria = ['balcao' => $novaCat(), 'proprietario' => $novaCat(), 'agente' => $novaCat()];
        foreach ($passagens->where('cancelada', false)->where('transferida', false) as $pp) {
            $cat = $pp->revendedor_id
                ? 'agente'
                : ($pp->pagamentos->contains(fn ($pg) => (bool) ($pg->pago_proprietario ?? false)) ? 'proprietario' : 'balcao');
            $parcelado = (int) ($pp->parcelas ?? 1) > 1; // crédito parcelado = passagem com parcelas > 1
            $porCategoria[$cat]['qtd']++;
            $porCategoria[$cat]['total'] += (float) $pp->total;
            $porCategoria[$cat]['areceber'] += (float) $pp->devido;
            foreach ($pp->pagamentos as $pg) {
                if ($pg->meio === 'desconto' || (float) $pg->valor <= 0) continue;
                $k = $pg->meio === 'pix' ? 'pix' : $pg->meio;
                if (isset($porCategoria[$cat][$k])) $porCategoria[$cat][$k] += (float) $pg->valor;
                if ($pg->meio === 'credito') {
                    $porCategoria[$cat][$parcelado ? 'credito_parcelado' : 'credito_avista'] += (float) $pg->valor;
                }
            }
        }

        // Cabeçalho: modo (caixa/relatório) + filtros aplicados (só os que existem)
        $ehRelatorio = $modo === 'relatorio';
        $filtros = [];
        if ($embs->count()) $filtros['Transporte'] = $embs->implode(', ');
        if (! empty($f['user_id'])) $filtros['Operador'] = optional(\App\Models\User::find($f['user_id']))->name;
        if (! empty($f['forma'])) $filtros['Forma'] = \App\Models\Passagem::FORMAS[$f['forma']] ?? $f['forma'];
        if (! empty($f['origem'])) $filtros['Origem'] = $f['origem'];
        if (! empty($f['destino'])) $filtros['Destino'] = $f['destino'];
        if (! empty($f['status_pagamento'])) $filtros['Status'] = ucfirst($f['status_pagamento']);
        if (! empty($f['cliente_faturado_id'])) $filtros['Cliente faturado'] = optional(\App\Models\ClienteFaturado::find($f['cliente_faturado_id']))->nome;
        if (! empty($f['busca'])) $filtros['Busca'] = $f['busca'];
        $filtros = array_filter($filtros, fn ($v) => $v !== null && $v !== '');

        // Trecho "ponta a ponta" da viagem (ex.: Manaus → Tabatinga): 1ª parada → última parada,
        // pela ordem da rota — não cada perninha (Manaus/Borba, Manaus/Coari...).
        // Respeita o sentido real (subida x descida) pelo fluxo das passagens.
        $trechoViagem = '';
        try {
            $norm = fn ($c) => mb_strtolower(trim((string) $c));
            $cidades = $passagens->flatMap(fn ($p) => [trim((string) $p->origem), trim((string) $p->destino)])->filter()->unique()->values();
            if ($cidades->count() === 1) {
                $trechoViagem = (string) $cidades->first();
            } elseif ($cidades->isNotEmpty()) {
                $rotaRef = $passagens->firstWhere('rota_id', '!=', null)?->rota;
                $ordem = $rotaRef ? collect($rotaRef->localidades())->map($norm)->values() : collect();
                if ($ordem->isNotEmpty()) {
                    $idx = fn ($c) => $ordem->search($norm($c));
                    $ok = $cidades->filter(fn ($c) => $idx($c) !== false)->sortBy($idx)->values();
                    // sentido real: quantas passagens vão no sentido da rota (ida) x contra (volta)
                    $ida = 0; $volta = 0;
                    foreach ($passagens as $p) {
                        $io = $idx($p->origem); $id = $idx($p->destino);
                        if ($io === false || $id === false || $io === $id) continue;
                        $io < $id ? $ida++ : $volta++;
                    }
                    if ($ok->count() >= 2) {
                        $trechoViagem = ($volta > $ida)
                            ? $ok->last().' → '.$ok->first()
                            : $ok->first().' → '.$ok->last();
                    } elseif ($ok->count() === 1) {
                        $trechoViagem = (string) $ok->first();
                    }
                }
                if ($trechoViagem === '') { // sem rota conhecida: origem que nunca é destino → destino que nunca é origem
                    $org = $passagens->pluck('origem')->map(fn ($c) => trim((string) $c))->filter();
                    $dst = $passagens->pluck('destino')->map(fn ($c) => trim((string) $c))->filter();
                    $ini = $org->reject(fn ($c) => $dst->contains($c))->first() ?: $org->first();
                    $fim = $dst->reject(fn ($c) => $org->contains($c))->last() ?: $dst->last();
                    if ($ini && $fim) $trechoViagem = ($ini === $fim) ? (string) $ini : $ini.' → '.$fim;
                }
            }
        } catch (\Throwable $e) { $trechoViagem = ''; }

        return view('passagens.caixa_viagem', [
            'linhas' => $linhas,
            'trechoViagem' => $trechoViagem,
            'ehRelatorio' => $ehRelatorio,
            'filtros' => $filtros,
            'totForma' => $totForma,
            'totDesconto' => $totDesconto,
            'totGeral' => $totGeral,
            'totRecebido' => $totRecebido,
            'totDevolvido' => $totDevolvido, 'totCancelado' => $totCancelado,
            'nPax' => $nPax, 'nIdosos' => $nIdosos, 'nPcd' => $nPcd, 'nOutros' => $nOutros, 'nColos' => $nColos, 'nDevolvidas' => $nDevolvidas, 'nCancelados' => $nCancelados,
            'embs' => $embs, 'datas' => $datas, 'f' => $f, 'prestacao' => $prestacao, 'porCategoria' => $porCategoria,
            'auto' => $request->boolean('print'),
            'totFormaProp' => $m['totFormaProp'] ?? [],
            'modo' => $modo, 'prestFechada' => $prestFechada, 'avulsos' => $avulsos, 'avEntradas' => $avEntradas, 'avSaidas' => $avSaidas,
        ]);
    }

    /**
     * Minhas vendas do AGENTE EXTERNO — tela simples: filtra por transporte e viagem e mostra
     * o que ele vendeu, a comissão dele e o que ainda deve repassar (empresa / donos das lanchas).
     */
    protected function minhasVendasAgente(Request $request, $u)
    {
        $transporteId = (int) $request->input('transporte_id') ?: null;
        $viagem = (string) $request->input('viagem', '');           // "AAAA-MM-DD" ou "AAAA-MM-DD|id da lancha"
        $dataViagem = null;
        if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:\|(\d+))?$/', $viagem, $mv)) {
            $dataViagem = $mv[1];
            if (! empty($mv[2])) $transporteId = (int) $mv[2];
        }

        // agente externo: tudo que é da agência dele | vendedor de um agente: só o que ele mesmo vendeu
        $ehAgente = $u->ehRevendedor();
        $todas = fn () => Passagem::where($ehAgente ? 'revendedor_id' : 'user_id', $u->id)->where('cancelada', false);
        // filtro escolhido (transporte / viagem) — vale pra tudo, inclusive pro que está em aberto
        $filtro = fn () => $todas()
            ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
            ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem));
        // vendas mostradas: a viagem escolhida, ou (padrão) o último mês
        $desde = now()->subMonthNoOverflow()->startOfDay();
        $base = fn () => $filtro()->when(! $dataViagem, fn ($q) => $q->where('created_at', '>=', $desde));

        // quanto de cada passagem vai pra frente (o resto é a comissão do agente) — mesma conta do Acerto
        $pct = max(0, min(100, (int) ($u->comissao_pct ?? 0)));
        $repasse = $ehAgente
            ? 'COALESCE(acerto_empresa_valor, '.((($u->modo_acerto ?? 'cheio') === 'comissao') ? 'ROUND(total * (100 - '.$pct.') / 100, 2)' : 'total').')'
            : 'COALESCE(acerto_revendedor_valor, total)';
        $comDono = \App\Models\Embarcacao::whereNotNull('dono_id')->select('id');

        $tot = $base()->selectRaw('COUNT(*) as qtd, COALESCE(SUM(total),0) as vendi, COALESCE(SUM('.$repasse.'),0) as repasse')->first();
        $vendi = (float) $tot->vendi;
        $repasseTotal = (float) $tot->repasse;

        // em aberto = TUDO que ainda não foi repassado (não só o último mês), dentro do filtro escolhido
        $devoAgente = $ehAgente ? 0.0 : (float) $filtro()->where('acerto_revendedor', false)->selectRaw('COALESCE(SUM('.$repasse.'),0) as v')->value('v');
        $devoEmpresa = ! $ehAgente ? 0.0 : (float) $filtro()->whereNotIn('embarcacao_id', $comDono)->where('acerto_empresa', false)
            ->selectRaw('COALESCE(SUM('.$repasse.'),0) as v')->value('v');
        $nomes = \App\Models\Embarcacao::with('dono:id,name')->get(['id', 'nome', 'dono_id', 'dono_nome'])->keyBy('id');
        $devoDonos = ! $ehAgente ? [] : $filtro()->whereIn('embarcacao_id', $comDono)->where('acerto_dono', false)->where('transferida', false)
            ->selectRaw('embarcacao_id, COUNT(*) as qtd, COALESCE(SUM('.$repasse.'),0) as valor')
            ->groupBy('embarcacao_id')->get()
            ->map(fn ($r) => [
                'lancha' => $nomes->get($r->embarcacao_id)?->nome ?? 'Lancha',
                'dono' => $nomes->get($r->embarcacao_id)?->dono?->name ?? $nomes->get($r->embarcacao_id)?->dono_nome,
                'qtd' => (int) $r->qtd, 'valor' => round((float) $r->valor, 2),
            ])->filter(fn ($x) => $x['valor'] > 0)->sortByDesc('valor')->values()->all();
        $devoDonosTotal = round(array_sum(array_column($devoDonos, 'valor')), 2);
        $devoTotal = round($devoEmpresa + $devoDonosTotal + $devoAgente, 2);

        $resumo = [
            'qtd' => (int) $tot->qtd,
            'passageiros' => (int) \App\Models\PassagemPassageiro::whereIn('passagem_id', $base()->select('id'))->count(),
            'vendi' => round($vendi, 2),
            'comissao' => round($vendi - $repasseTotal, 2),
            'devo' => $devoTotal,
            'devoEmpresa' => round($devoEmpresa, 2),
            'devoAgente' => round($devoAgente, 2),
            'devoDonos' => $devoDonos,
            'periodo' => $dataViagem ? 'viagem de '.\Carbon\Carbon::parse($dataViagem)->format('d/m/Y') : 'últimos 30 dias',
        ];

        // filtros: lanchas e viagens em que ele vendeu
        $transportes = \App\Models\Embarcacao::whereIn('id', $todas()->whereNotNull('embarcacao_id')->select('embarcacao_id'))->orderBy('nome')->get(['id', 'nome']);
        $viagens = $todas()->whereNotNull('data_viagem')
            ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
            ->selectRaw('data_viagem, embarcacao_id, COUNT(*) as qtd')
            ->groupBy('data_viagem', 'embarcacao_id')->orderByDesc('data_viagem')->limit(80)->get()
            ->map(function ($r) use ($nomes) {
                $d = \Carbon\Carbon::parse($r->data_viagem);
                return [
                    'valor' => $d->toDateString().'|'.(int) $r->embarcacao_id,
                    'rotulo' => $d->format('d/m/Y').' · '.($nomes->get($r->embarcacao_id)?->nome ?? 'Lancha').' ('.(int) $r->qtd.')',
                ];
            })->all();
        $viagemSel = $dataViagem ? $dataViagem.'|'.(int) $transporteId : '';

        // lista: cada venda com o que é dele e o que vai pra frente
        $limite = 200;
        $vendas = $base()->select('*')->selectRaw($repasse.' as repasse_valor')
            ->withCount('passageiros')->with('embarcacao:id,nome,dono_id')
            ->orderByDesc('id')->limit($limite)->get()
            ->map(function ($p) use ($ehAgente) {
                $temDono = $ehAgente && (bool) ($p->embarcacao?->dono_id);
                $quitado = ! $ehAgente ? (bool) $p->acerto_revendedor : ($temDono ? ((bool) $p->acerto_dono || (bool) $p->transferida) : (bool) $p->acerto_empresa);
                return [
                    'id' => $p->id,
                    'numero' => $p->numeroFormatado(),
                    'quando' => $p->created_at?->format('d/m H:i'),
                    'trecho' => $p->origem.' → '.$p->destino,
                    'viagem' => $p->data_viagem ? \Carbon\Carbon::parse($p->data_viagem)->format('d/m') : '',
                    'lancha' => (string) ($p->embarcacao?->nome ?? ''),
                    'pax' => (int) $p->passageiros_count,
                    'total' => (float) $p->total,
                    'repasse' => (float) $p->repasse_valor,
                    'comissao' => round((float) $p->total - (float) $p->repasse_valor, 2),
                    'para' => $temDono ? 'dono' : 'empresa',
                    'quitado' => $quitado,
                ];
            })->all();

        $painelAgente = $ehAgente ? \App\Http\Controllers\AcertoController::dadosAgente($u, null, $dataViagem) : null;

        return view('passagens.minhas-vendas-agente', compact('ehAgente', 'resumo', 'transportes', 'viagens', 'transporteId', 'viagemSel', 'vendas', 'limite', 'painelAgente'));
    }

    public function minhasVendas(Request $request)
    {
        $u = auth()->user();
        // agente externo: tela simples (vendi / minha comissão / devo repassar), filtrada por transporte e viagem
        if ($u->revendedorDono() !== null && ! $request->boolean('pdf')) return $this->minhasVendasAgente($request, $u);
        [$inicio, $fim, $periodo] = \App\Http\Controllers\CaixaController::periodo($request);

        $canal = $request->input('canal'); // dinheiro|pix_manual|pix_auto|debito|credito
        $limite = 300;
        $base = Passagem::where('user_id', $u->id)->where('cancelada', false)
            ->whereBetween('created_at', [$inicio, $fim])
            ->when($canal, fn ($q) => $q->whereHas('pagamentos', fn ($w) => $w->canal($canal)));

        // totais somados no banco (não trava com período longo)
        $totais = [
            'qtd'         => (clone $base)->count(),
            'passageiros' => (int) \App\Models\PassagemPassageiro::whereHas('passagem', function ($q) use ($u, $inicio, $fim, $canal) {
                $q->where('user_id', $u->id)->where('cancelada', false)->whereBetween('created_at', [$inicio, $fim])
                    ->when($canal, fn ($w) => $w->whereHas('pagamentos', fn ($x) => $x->canal($canal)));
            })->count(),
            'total'       => (float) (clone $base)->sum('total'),
            'devido'      => (float) (clone $base)->sum('devido'),
        ];
        $totais['recebido'] = $totais['total'] - $totais['devido'];

        $vendas = (clone $base)->with(['passageiros', 'operador', 'pagamentos', 'embarcacao:id,nome', 'revendedor:id,name', 'clienteFaturado:id,nome,empresa'])
            ->orderByDesc('id')->limit($limite)->get();

        // o que falta acertar (revendedor -> empresa | vendedor -> revendedor)
        $acerto = null;
        if ($u->ehRevendedor()) {
            $base = Passagem::where('revendedor_id', $u->id)->where('cancelada', false);
            $acerto = [
                'titulo'   => 'Acerto com a empresa',
                'pendente' => (float) (clone $base)->where('acerto_empresa', false)->sum('acerto_empresa_valor'),
                'acertado' => (float) (clone $base)->where('acerto_empresa', true)->sum('acerto_empresa_valor'),
            ];
        } elseif ($u->revendedor_id) {
            $base = Passagem::where('user_id', $u->id)->where('cancelada', false);
            $acerto = [
                'titulo'   => 'Acerto com o revendedor',
                'pendente' => (float) (clone $base)->where('acerto_revendedor', false)->sum('acerto_revendedor_valor'),
                'acertado' => (float) (clone $base)->where('acerto_revendedor', true)->sum('acerto_revendedor_valor'),
            ];
        }

        if ($request->boolean('pdf')) {
            $linhas = $vendas->map(fn ($p) => (object) [
                'data' => $p->created_at,
                'descricao' => ($p->embarcacao?->nome ? '🚤 '.$p->embarcacao->nome.' · ' : '').$p->origem.' → '.$p->destino.($p->comprador_nome ? ' · '.$p->comprador_nome : ''),
                'ref' => '#'.$p->numeroFormatado(),
                'valor' => (float) $p->total,
            ]);
            $tot = [
                ['label' => 'Total vendido', 'valor' => (float) $totais['total'], 'forte' => true],
                ['label' => 'Recebido', 'valor' => (float) $totais['recebido']],
                ['label' => 'A receber', 'valor' => (float) $totais['devido']],
            ];
            if ($acerto) $tot[] = ['label' => 'A repassar ('.$acerto['titulo'].')', 'valor' => (float) $acerto['pendente']];
            return view('relatorio.prestacao', [
                'empresa' => \App\Support\Tenant::empresa(),
                'titulo' => 'Prestação de contas',
                'subtitulo' => ($u->ehRevendedor() ? 'Agente: ' : 'Operador: ').$u->name,
                'periodo' => 'Período: '.\Carbon\Carbon::parse($inicio)->format('d/m/Y').' a '.\Carbon\Carbon::parse($fim)->format('d/m/Y'),
                'linhas' => $linhas,
                'totais' => $tot,
                'assinatura' => $u->name,
                'voltar' => route('passagens.minhas-vendas'),
            ]);
        }

        // Agente externo: acerto (empresa + donos) na MESMA tela do relatório.
        $painelAgente = $u->ehRevendedor() ? \App\Http\Controllers\AcertoController::dadosAgente($u) : null;

        $linhas = \App\Support\ManifestoCaixa::dados($vendas)['linhas'];

        return view('passagens.minhas-vendas', compact('vendas', 'linhas', 'totais', 'acerto', 'inicio', 'fim', 'periodo', 'canal', 'limite', 'painelAgente'));
    }

    /** Exporta as vendas de passagem do período (e forma) em CSV — sem travar a tela. */
    public function minhasVendasExportar(Request $request)
    {
        $u = auth()->user();
        [$inicio, $fim] = \App\Http\Controllers\CaixaController::periodo($request);
        $canal = $request->input('canal');

        $q = Passagem::where('user_id', $u->id)->where('cancelada', false)
            ->whereBetween('created_at', [$inicio, $fim])
            ->when($canal, fn ($w) => $w->whereHas('pagamentos', fn ($x) => $x->canal($canal)))
            ->withCount('passageiros')->with('embarcacao:id,nome')->orderByDesc('id');

        $nome = 'minhas-vendas-'.$inicio->format('Ymd').'-'.$fim->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Data', 'Hora', 'Número', 'Origem', 'Destino', 'Passageiros', 'Barco', 'Status', 'Total', 'Pago', 'Devido'], ';');
            $q->chunk(1000, function ($lote) use ($out) {
                foreach ($lote as $p) {
                    fputcsv($out, [
                        $p->created_at->format('d/m/Y'), $p->created_at->format('H:i'),
                        $p->numeroFormatado(), $p->origem, $p->destino,
                        $p->passageiros_count, $p->embarcacao?->nome, $p->statusTexto(),
                        number_format((float) $p->total, 2, ',', '.'),
                        number_format((float) $p->pago, 2, ',', '.'),
                        number_format((float) $p->devido, 2, ',', '.'),
                    ], ';');
                }
            });
            fclose($out);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Tela de check-in (hub): escaneia o QR do bilhete ou busca pelo numero. */
    public function scanner()
    {
        return view('passagens.scanner');
    }

    /** Resolve o numero digitado -> abre o check-in daquela passagem. */
    public function buscarCheckin(Request $request)
    {
        $n = trim((string) $request->query('numero', ''));
        if ($n === '') {
            return redirect()->route('passagens.scanner')->withErrors(['numero' => 'Informe o número da passagem.']);
        }
        $num = preg_replace('/\D/', '', $n);
        $p = Passagem::where('numero_completo', $n)
            ->when($num !== '', fn ($q) => $q->orWhere('numero', (int) $num))
            ->first();
        if (! $p) {
            return redirect()->route('passagens.scanner')->withErrors(['numero' => "Passagem \"$n\" não encontrada."]);
        }
        return redirect()->route('checkin', $p);
    }

    /** Impressão direta pelo Agente (token): um bilhete por passageiro, mesmo em compra junta. */
    public function imprimirDireto(Request $request, Passagem $passagem)
    {
        abort_unless($passagem->empresa_id === Tenant::id(), 404);
        $estacao = \App\Models\Estacao::resolver();
        if (! $estacao) {
            return response()->json(['ok' => false, 'erro' => 'Nenhuma estação de impressão ativa.'], 422);
        }

        // CAMINHO 1 (idêntico ao navegador): o front rasteriza o bilhete (html2canvas)
        // e manda as imagens; o agente imprime a imagem inteira. Fica igualzinho.
        $imagens = $request->input('imagens');
        if (is_array($imagens) && count($imagens)) {
            $tot = count($imagens);
            $n = 0;
            foreach ($imagens as $i => $img) {
                if (! is_string($img) || $img === '') continue;
                if (str_contains($img, ',')) $img = substr($img, strpos($img, ',') + 1); // tira dataURL
                \App\Models\FilaImpressao::create([
                    'estacao_id' => $estacao->id,
                    'encomenda_id' => null,
                    'tipo' => 'generico',
                    'dados' => [
                        'linhas' => [
                            ['op' => 'img', 'data' => $img, 'w' => 576],
                            ['op' => 'feed', 'n' => 3],
                            ['op' => 'cut'],
                        ],
                        'impressora' => 'comprovante',
                        'ref' => $passagem->numeroFormatado().' ('.($i + 1).'/'.$tot.')',
                    ],
                ]);
                $n++;
            }
            if ($n > 0) {
                return ['ok' => true, 'jobs' => $n, 'estacao' => $estacao->nome, 'modo' => 'img'];
            }
        }

        $passagem->loadMissing(['passageiros', 'embarcacao', 'operador', 'pagamentos']);
        $empresa = Tenant::empresa();
        $pax = $passagem->passageiros->values();
        $tot = $pax->count();
        $checkin = route('checkin', $passagem);
        $brl = fn ($v) => \App\Support\Recibo::brl($v);
        $formas = $passagem->pagamentos->map(fn ($pg) => \App\Models\Pagamento::MEIOS[$pg->meio] ?? $pg->meio)->filter()->unique()->implode(', ');
        $hora = $passagem->embarcacao?->hora_saida ? \Illuminate\Support\Str::substr($passagem->embarcacao->hora_saida, 0, 5) : null;
        $usaCancel = (bool) $empresa->config('usa_cancel_transporte');
        $cancelAntes = $passagem->embarcacao?->cancel_antes_pct ?? $empresa->config('cancel_antes_pct', 10);
        $cancelDepois = $passagem->embarcacao?->cancel_depois_pct ?? $empresa->config('cancel_depois_pct', 30);

        // logos (empresa e barco) em base64, se existirem
        $lerLogo = function (?string $rel): ?string {
            if (! $rel) return null;
            $p = public_path($rel);
            if (! is_file($p)) return null;
            $b = @file_get_contents($p);
            return $b ? base64_encode($b) : null;
        };
        $logoEmp = $lerLogo($empresa->logo ?? null);
        $logoBarco = $lerLogo($passagem->embarcacao->logo ?? null);
        $diaSem = ['Domingo', 'Segunda-feira', 'Terca-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sabado'];
        $n = 0;

        foreach ($pax as $i => $pz) {
            // o LAYOUT do bilhete vive aqui (servidor). O agente só executa as linhas.
            $doc = new \App\Support\Recibo;
            if ($logoEmp) $doc->img($logoEmp);
            $doc->txt(mb_strtoupper($empresa->nomeExibicao()), 'c', true, 2);
            if ($empresa->cnpj) $doc->txt('CNPJ '.$empresa->cnpj, 'c');
            $contato = trim((string) ($empresa->whatsapp ?: $empresa->telefone));
            if ($contato !== '') $doc->txt($contato, 'c');
            $doc->linha('=');
            $doc->txt('BILHETE DE PASSAGEM', 'c', true, 1);
            $doc->linha('=');
            $doc->txt('No '.$passagem->numeroFormatado(), 'c', true, 2);
            if ($pz->poltrona) $doc->txt('POLTRONA '.$pz->poltrona, 'c', true, 2);
            $doc->txt(mb_strtoupper($passagem->origem).'  >  '.mb_strtoupper($passagem->destino), 'c', true, 1);
            $doc->linha();
            $doc->txt('VIAGEM', 'l', true);
            if ($logoBarco) $doc->img($logoBarco);
            if ($passagem->embarcacao?->nome) $doc->lr('Embarcacao', $passagem->embarcacao->nome);
            if ($passagem->data_viagem) $doc->lr('Saida', $passagem->data_viagem->format('d/m/Y').($hora ? ' '.$hora : '').' '.($diaSem[(int) $passagem->data_viagem->dayOfWeek] ?? ''));
            if ($passagem->viagem_id) $doc->lr('No da viagem', (string) $passagem->viagem_id);
            $doc->linha();
            $doc->txt('PASSAGEIRO', 'l', true);
            $doc->txt(mb_strtoupper($pz->nome ?: 'PASSAGEIRO'), 'l', true, 1);
            $papelTipo = trim((string) ($pz->papel ?: $pz->tipo)); if ($papelTipo !== '') $doc->lr('Tipo', mb_strtoupper($papelTipo));
            if ($pz->documento) $doc->lr('Documento', $pz->documento);
            if ($tot > 1) $doc->lr('Bilhete', ($i + 1).'/'.$tot);
            $doc->linha();
            $doc->txt('PAGAMENTO', 'l', true);
            $doc->lr('VALOR TOTAL', $brl($pz->valor_total), true);
            $doc->lr('Situacao', (float) $passagem->devido > 0.009 ? 'EM ABERTO' : 'PAGO');
            if ($formas !== '') $doc->lr('Forma', $formas);
            if ($passagem->operador?->name) $doc->lr('Vendedor', $passagem->operador->name);
            $doc->lr('Compra', $passagem->created_at->format('d/m/Y H:i'));
            $doc->linha();
            $doc->qr($checkin, 8);
            $doc->txt('CHECK-IN - apresente no embarque', 'c');
            if ($empresa->rodape_comprovante) { $doc->linha(); $doc->txt($empresa->rodape_comprovante, 'c'); }
            if ($usaCancel) { $doc->linha(); $doc->txt('Devolucao: antes da saida retem '.$cancelAntes.'%, depois da saida retem '.$cancelDepois.'%.', 'c'); }
            $doc->feed(4)->cortar();

            \App\Models\FilaImpressao::create([
                'estacao_id' => $estacao->id,
                'encomenda_id' => null,
                'tipo' => 'generico',
                'dados' => [
                    'linhas' => $doc->toArray(),
                    'impressora' => 'comprovante',
                    'ref' => $passagem->numeroFormatado().' ('.($i + 1).'/'.$tot.')',
                ],
            ]);
            $n++;
        }

        if ($n === 0) {
            return response()->json(['ok' => false, 'erro' => 'Passagem sem passageiros para imprimir.'], 422);
        }

        return ['ok' => true, 'jobs' => $n, 'estacao' => $estacao->nome];
    }

    public function comprovante(Passagem $passagem)
    {
        abort_unless($passagem->empresa_id === Tenant::id(), 404);
        $passagem->load(['passageiros', 'embarcacao', 'operador', 'camarote', 'pagamentos', 'viagem', 'rota.paradas']);
        try {
            $html = view('passagens.comprovante', ['p' => $passagem, 'empresa' => Tenant::empresa(), 'modo' => request('modo')])->render();
            return response($html);
        } catch (\Throwable $e) {
            report($e);
            $msg = auth()->user()?->admin
                ? 'Erro no comprovante: '.class_basename($e).': '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine()
                : 'Não foi possível gerar o comprovante. Tente novamente ou chame o suporte.';
            return response($msg, 500);
        }
    }

    /** Recibo (A4) de uma passagem — abre no pop-up da lista; imprime, gera PDF ou compartilha no WhatsApp. */
    public function recibo(Passagem $passagem)
    {
        abort_unless($passagem->empresa_id === Tenant::id(), 404);
        try {
            $passagem->load(['passageiros', 'embarcacao', 'operador', 'pagamentos']);
            $empresa = Tenant::empresa();
            $logo = ($empresa->logo && is_file(public_path($empresa->logo))) ? asset($empresa->logo) : asset('img/ajato-logo.png');
            return response(view('docs.recibo', ['p' => $passagem, 'empresa' => $empresa, 'logo' => $logo])->render());
        } catch (\Throwable $e) {
            report($e);
            $msg = auth()->user()?->admin
                ? 'Erro no recibo: '.class_basename($e).': '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine()
                : 'Não foi possível gerar o recibo. Tente novamente ou chame o suporte.';
            return response($msg, 500);
        }
    }

    /** Etiqueta de bagagem do passageiro: N volumes + via do passageiro. */
    public function etiqueta(Passagem $passagem, PassagemPassageiro $pax)
    {
        abort_unless($passagem->empresa_id === Tenant::id(), 404);
        abort_unless((int) $pax->passagem_id === (int) $passagem->id, 404);
        $vol = max(1, min(99, (int) request('volumes', 1)));
        $passagem->load('embarcacao');
        $empresa = Tenant::empresa();
        $mmW = max(10, min(120, (int) $empresa->config('etiqueta_mm_w', 29)));   // largura do papel (cabeçote)
        $mmH = max(20, min(300, (int) $empresa->config('etiqueta_mm_h', 90)));   // comprimento (avanço)
        $girar = (bool) $empresa->config('etiqueta_girar', true);                 // texto na horizontal (gira 90°)
        // No horizontal o conteúdo é diagramado ao longo do comprimento (mm_h) e depois girado p/ caber na largura (mm_w).
        // Horizontal: diagrama ao longo do comprimento (mm_h) e gira; label com tamanho EXATO do papel.
        $labW = $girar ? $mmH : $mmW;   // largura do conteúdo (mm)
        $labH = $girar ? $mmW : $mmH;   // altura do conteúdo (mm)
        // Etiqueta de BAGAGEM pode ser mais comprida que a via. Vazio/0 = automático:
        // em papel contínuo (largura >= 50mm, ex.: 62mm) sai mais comprida; em die-cut (DK-1201 29x90) fica igual à via.
        $bagMmH = (int) $empresa->config('etiqueta_bag_mm_h', 0);
        if ($bagMmH <= 0) { $bagMmH = $mmW >= 50 ? ($mmH * 2) : $mmH; } // papel continuo: bagagem ~2x a via
        $bagMmH = max($mmH, min(400, $bagMmH));
        $labWbag = $girar ? $bagMmH : $mmW;
        $labHbag = $girar ? $mmW : $bagMmH;
        // observação do check-in: se veio no link (impressão na hora) ela vale e é salva; senão usa a já guardada
        $obs = request()->has('obs') ? trim((string) request('obs')) : (string) ($pax->obs_etiqueta ?? '');
        if (request()->has('obs') && $obs !== (string) ($pax->obs_etiqueta ?? '')) {
            $pax->forceFill(['obs_etiqueta' => $obs !== '' ? mb_substr($obs, 0, 200) : null])->save();
        }
        return view('passagens.etiqueta', [
            'p' => $passagem, 'pax' => $pax, 'volumes' => $vol, 'soVia' => request()->boolean('so_via'),
            'soBag' => request()->boolean('so_bag') && ! request()->boolean('so_via'), 'vias' => max(1, min(10, (int) request('vias', 1))),
            'empresa' => $empresa, 'labW' => $labW, 'labH' => $labH, 'labWbag' => $labWbag, 'labHbag' => $labHbag, 'girar' => $girar, 'obs' => $obs,
        ]);
    }

    /** Impressão direta da etiqueta/via: recebe as imagens rasterizadas e enfileira na estação. */
    public function etiquetaDireto(Request $request, Passagem $passagem, PassagemPassageiro $pax)
    {
        abort_unless($passagem->empresa_id === Tenant::id(), 404);
        abort_unless((int) $pax->passagem_id === (int) $passagem->id, 404);
        $estacao = \App\Models\Estacao::resolver();
        if (! $estacao) {
            return response()->json(['ok' => false, 'erro' => 'Nenhuma estação de impressão ativa.'], 422);
        }
        $imagens = $request->input('imagens');
        if (! is_array($imagens) || ! count($imagens)) {
            return response()->json(['ok' => false, 'erro' => 'Sem conteúdo para imprimir.'], 422);
        }
        $mmW = max(10, min(120, (int) Tenant::empresa()->config('etiqueta_mm_w', 29)));
        $wDots = (int) round($mmW / 25.4 * 300); // Brother QL = 300 dpi
        $n = 0;
        foreach ($imagens as $img) {
            if (! is_string($img) || $img === '') continue;
            if (str_contains($img, ',')) $img = substr($img, strpos($img, ',') + 1);
            \App\Models\FilaImpressao::create([
                'estacao_id' => $estacao->id,
                'encomenda_id' => null,
                'tipo' => 'generico',
                'dados' => [
                    'linhas' => [
                        ['op' => 'img', 'data' => $img, 'w' => $wDots],
                        ['op' => 'feed', 'n' => 2],
                        ['op' => 'cut'],
                    ],
                    'impressora' => 'passagem',
                    'ref' => 'Etiqueta '.$passagem->numeroFormatado(),
                ],
            ]);
            $n++;
        }
        return $n > 0
            ? ['ok' => true, 'jobs' => $n, 'estacao' => $estacao->nome]
            : response()->json(['ok' => false, 'erro' => 'Sem conteúdo para imprimir.'], 422);
    }

    /** Tela de check-in (vinda do QR do comprovante) — valida embarque, só operador logado. */
    public function checkin(Passagem $passagem)
    {
        abort_unless($passagem->empresa_id === Tenant::id(), 404);
        $passagem->load(['passageiros', 'embarcacao', 'operador']);
        return view('passagens.checkin', ['p' => $passagem, 'u' => auth()->user()]);
    }

    /** Edita em massa as passagens selecionadas (transporte, data, trecho, forma). */
    public function editarMassa(Request $request)
    {
        abort_unless(auth()->user()->podeEditarPassagem(), 403, 'Sem permissão para editar passagens.');
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'embarcacao_id' => 'nullable|integer',
            'data_viagem' => 'nullable|date',
            'origem' => 'nullable|string|max:120',
            'destino' => 'nullable|string|max:120',
            'forma' => 'nullable|in:avista,areceber,faturado,online',
        ]);

        $campos = [];
        foreach (['embarcacao_id', 'data_viagem', 'origem', 'destino', 'forma'] as $c) {
            if (! empty($data[$c])) $campos[$c] = $data[$c];
        }
        if (empty($campos)) {
            return response()->json(['ok' => false, 'erro' => 'Escolha pelo menos um campo para alterar.'], 422);
        }
        // Trocar transporte: confirma que a embarcacao e da empresa (escopo global cuida disso)
        if (isset($campos['embarcacao_id']) && ! \App\Models\Embarcacao::whereKey($campos['embarcacao_id'])->exists()) {
            return response()->json(['ok' => false, 'erro' => 'Transporte inválido.'], 422);
        }

        // Passagem tem escopo por empresa -> so altera as passagens da empresa atual.
        $n = Passagem::whereIn('id', $data['ids'])->where('cancelada', false)->update($campos);

        return ['ok' => true, 'alteradas' => $n];
    }

    /** Edicao individual COMPLETA: carrega todos os dados da passagem + passageiros pro modal. */
    public function editarDados(Passagem $passagem)
    {
        abort_unless(auth()->user()->podeEditarPassagem(), 403, 'Sem permissao para editar passagens.');
        $passagem->load(['passageiros' => fn ($q) => $q->orderBy('id')]);
        return [
            'ok' => true,
            'id' => $passagem->id,
            'numero' => $passagem->numeroFormatado(),
            'cancelada' => (bool) $passagem->cancelada,
            'embarcacao_id' => $passagem->embarcacao_id ? (string) $passagem->embarcacao_id : '',
            'data_viagem' => $passagem->data_viagem ? \Illuminate\Support\Carbon::parse($passagem->data_viagem)->format('Y-m-d') : '',
            'origem' => (string) $passagem->origem,
            'destino' => (string) $passagem->destino,
            'forma' => (string) ($passagem->forma ?? 'avista'),
            'comprador_nome' => (string) ($passagem->comprador_nome ?? ''),
            'comprador_telefone' => (string) ($passagem->comprador_telefone ?? ''),
            'comprador_documento' => (string) ($passagem->comprador_documento ?? ''),
            'observacao' => (string) ($passagem->observacao ?? ''),
            'passageiros' => $passagem->passageiros->map(fn ($x) => [
                'id' => $x->id,
                'nome' => (string) $x->nome,
                'tipo' => (string) $x->tipo,
                'papel' => (string) ($x->papel ?? ''),
                'documento' => (string) ($x->documento ?? ''),
                'nascimento' => $x->nascimento ? $x->nascimento->format('Y-m-d') : '',
                'sexo' => (string) ($x->sexo ?? ''),
                'telefone' => (string) ($x->telefone ?? ''),
                'poltrona' => (string) ($x->poltrona ?? ''),
                'valor_passagem' => (float) $x->valor_passagem,
                'valor_taxa' => (float) $x->valor_taxa,
                'valor_alimentacao' => (float) $x->valor_alimentacao,
            ])->values(),
        ];
    }

    /** Salva a edicao individual completa (empresa-escopada pelo binding). */
    public function atualizar(Request $request, Passagem $passagem)
    {
        abort_unless(auth()->user()->podeEditarPassagem(), 403, 'Sem permissao para editar passagens.');
        abort_if($passagem->cancelada, 422, 'Passagem cancelada nao pode ser editada.');

        $data = $this->validar($request);

        // poltrona nao repete no mesmo cadastro nem bate com outra venda (ignora a propria)
        $emb = ! empty($data['embarcacao_id']) ? \App\Models\Embarcacao::find($data['embarcacao_id']) : null;
        if ($emb && $emb->usa_poltrona) {
            $assentos = collect($data['passageiros'])->pluck('poltrona')->filter()->reject(fn ($s) => mb_strtoupper(trim((string) $s)) === 'LIVRE')->values();
            abort_if($assentos->duplicates()->isNotEmpty(), 422, 'Ha poltronas repetidas no mesmo cadastro.');
            $ocup = $this->ocupadas($data['embarcacao_id'], $data['data_viagem'] ?? null, $data['viagem_id'] ?? null, $data['rota_id'] ?? null, $data['origem'] ?? null, $data['destino'] ?? null, $passagem->id);
            $conflito = $assentos->intersect($ocup);
            abort_if($conflito->isNotEmpty(), 422, 'Poltrona(s) ja vendida(s): '.$conflito->implode(', '));
        }

        try {
            app(\App\Services\PassagemService::class)->atualizar($passagem, $data, (int) auth()->id());
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        $url = route('passagens.show', ['passagem' => $passagem]);
        if ($request->wantsJson() || $request->header('X-Requested-With') === 'fetch') {
            return response()->json(['ok' => true, 'id' => $passagem->id, 'url' => $url]);
        }
        return redirect($url)->with('ok', 'Passagem '.$passagem->numeroFormatado().' atualizada.');
    }

    /** Abre o FORMULARIO COMPLETO (mesma tela da nova passagem) ja preenchido p/ editar. */
    public function editar(Request $request, Passagem $passagem)
    {
        abort_unless(auth()->user()->podeEditarPassagem(), 403, 'Sem permissao para editar passagens.');
        abort_if($passagem->cancelada, 422, 'Passagem cancelada nao pode ser editada. Reative antes de editar.');

        $dados = $this->dadosFormVenda($request);
        $dados['edicao'] = $this->montarEdicao($passagem);
        $dados['prefill'] = null;
        return view('passagens.create', $dados);
    }

    /** Snapshot no formato _vendaKeys do formulario, a partir de uma passagem existente. */
    protected function montarEdicao(Passagem $passagem): array
    {
        $passagem->loadMissing('passageiros');
        $empresaId = (int) auth()->user()->empresa_id;

        $tipos = \App\Models\TipoPassageiro::where('empresa_id', $empresaId)->get();
        $tipoPorNome = [];
        foreach ($tipos as $t) { $tipoPorNome[mb_strtolower(trim((string) $t->nome))] = $t; }

        $colos = [];
        foreach ($passagem->passageiros as $pp) {
            if (($pp->papel ?? null) === 'colo' && $pp->colo_de_id) { $colos[(int) $pp->colo_de_id] = $pp; }
        }

        $br = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '';
        $iso = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('Y-m-d') : '';

        $paxOut = [];
        foreach ($passagem->passageiros as $pp) {
            if (($pp->papel ?? null) === 'colo') continue;
            $t = $tipoPorNome[mb_strtolower(trim((string) $pp->tipo))] ?? null;
            $colo = $colos[(int) $pp->id] ?? null;
            $paxOut[] = [
                'id' => 'e'.$pp->id,
                'nome' => (string) $pp->nome,
                'tipo' => (string) $pp->tipo,
                'tipo_id' => $t ? $t->id : null,
                'percentual' => (int) ($pp->percentual ?? ($t->percentual ?? 100)),
                'paga_taxa' => $t ? (bool) $t->paga_taxa : ((float) $pp->valor_taxa > 0),
                'modo' => $t->modo ?? 'valor',
                'documento' => (string) ($pp->documento ?? ''),
                'doc_tipo' => (string) ($pp->doc_tipo ?? ''),
                'poltrona' => (string) ($pp->poltrona ?? ''),
                'sexo' => (string) ($pp->sexo ?? ''),
                'telefone' => (string) ($pp->telefone ?? ''),
                'nacionalidade' => $pp->nacionalidade ?: 'Brasileira',
                'nascimento' => $iso($pp->nascimento),
                'nasc_br' => $br($pp->nascimento),
                'foto_rg' => (string) ($pp->foto_rg ?? ''),
                'foto' => (string) ($pp->foto ?? ''),
                'documentos' => is_array($pp->documentos) ? $pp->documentos : [],
                'checkin' => (bool) $pp->checkin,
                'temColo' => (bool) $colo,
                'bebeColo' => [
                    'nome' => $colo ? (string) $colo->nome : '',
                    'documento' => $colo ? (string) ($colo->documento ?? '') : '',
                    'nascimento' => $colo ? $iso($colo->nascimento) : '',
                    'nasc_br' => $colo ? $br($colo->nascimento) : '',
                ],
                '_mais' => (bool) (($pp->documento ?? '') || ($pp->foto ?? '') || ($pp->foto_rg ?? '') || $colo),
                'valor_passagem' => (float) $pp->valor_passagem,
                'valor_taxa' => (float) $pp->valor_taxa,
                'valor_alimentacao' => (float) $pp->valor_alimentacao,
                'valor_integral' => (float) $pp->valor_integral,
            ];
        }

        $pags = \App\Models\Pagamento::where('modulo', 'passagem')->where('origem_id', $passagem->id)
            ->where('tipo_mov', 'entrada')->where('estornado', false)
            ->whereIn('meio', ['dinheiro', 'pix', 'debito', 'credito'])->get();
        $porMeio = [];
        foreach ($pags as $pg) { $porMeio[$pg->meio] = ($porMeio[$pg->meio] ?? 0) + (float) $pg->valor; }
        $meios = array_keys($porMeio);
        $propMaq = $pags->contains(fn ($pg) => (bool) $pg->pago_proprietario);

        $ehAgente = $passagem->revendedor_id
            && (int) $passagem->user_id === (int) $passagem->revendedor_id
            && optional(\App\Models\User::find($passagem->user_id))->is_revendedor;
        $forma = (string) $passagem->forma;
        if ($ehAgente) { $tipoPag = 'agente'; }
        elseif ($forma === 'faturado' || ($forma === 'areceber' && $passagem->cliente_faturado_id)) { $tipoPag = 'faturado'; }
        elseif ($forma === 'online') { $tipoPag = 'online'; }
        else { $tipoPag = 'balcao'; }

        $credParcel = in_array('credito', $meios, true) && (int) $passagem->parcelas > 1;
        $dividir = count($meios) > 1;
        $pag = ['dinheiro' => null, 'pix' => null, 'debito' => null, 'credito' => null];
        if ($dividir) { foreach ($porMeio as $m => $v) { $pag[$m] = round($v, 2); } }
        $metodos = $meios ?: ['dinheiro'];
        if ($tipoPag === 'online') { $metodos = array_values(array_filter($metodos, fn ($m) => $m !== 'dinheiro')) ?: ['pix']; }

        $fatSel = null;
        if ($passagem->cliente_faturado_id && ($cf = \App\Models\ClienteFaturado::find($passagem->cliente_faturado_id))) {
            $fatSel = ['id' => $cf->id, 'nome' => $cf->nome, 'empresa' => $cf->empresa ?? null, 'documento' => $cf->documento ?? null];
        }

        return [
            '_id' => $passagem->id,
            '_numero' => $passagem->numeroFormatado(),
            '_url' => route('passagens.atualizar', ['passagem' => $passagem]),
            'origemSel' => (string) $passagem->origem,
            'destinoSel' => (string) $passagem->destino,
            'embarcacaoSel' => $passagem->embarcacao_id ? (string) $passagem->embarcacao_id : '',
            'rotaId' => $passagem->rota_id ?: null,
            'viagemData' => $iso($passagem->data_viagem),
            'passageiros' => $paxOut,
            'tipoPag' => $tipoPag,
            'metodosAtivos' => array_values($metodos),
            'credParcel' => $credParcel,
            'dividir' => $dividir,
            'pag' => $pag,
            'proprietarioMaq' => (bool) $propMaq,
            'parcelas' => max(1, (int) $passagem->parcelas),
            'desconto' => ((float) $passagem->desconto) ?: null,
            'agenteId' => $ehAgente ? (string) $passagem->revendedor_id : '',
            'agenteAcerto' => false,
            'clienteFaturadoId' => $passagem->cliente_faturado_id ?: null,
            'cobrarDias' => $passagem->cobrar_dias ?: 30,
            'fatSel' => $fatSel,
            'compradorNome' => (string) ($passagem->comprador_nome ?? ''),
            'compradorTel' => (string) ($passagem->comprador_telefone ?? ''),
            'compradorDoc' => (string) ($passagem->comprador_documento ?? ''),
            'observacao' => (string) ($passagem->observacao ?? ''),
            'documentosGerais' => is_array($passagem->documentos_gerais) ? $passagem->documentos_gerais : [],
        ];
    }

    /** Exclui PERMANENTEMENTE a passagem (só quem tem permissão). Empresa-escopado pelo binding. */
    public function excluir(Passagem $passagem)
    {
        abort_unless(auth()->user()->podeExcluirPassagem(), 403, 'Sem permissão para excluir passagens.');
        $num = $passagem->numeroFormatado();
        \Illuminate\Support\Facades\DB::transaction(function () use ($passagem) {
            \App\Models\Pagamento::where('modulo', 'passagem')->where('origem_id', $passagem->id)->delete();
            $passagem->passageiros()->delete();
            $passagem->delete();
        });
        if (request()->expectsJson()) return ['ok' => true];
        return redirect()->route('passagens.index')->with('ok', 'Passagem #'.$num.' excluída permanentemente.');
    }

    public function cancelar(Request $request, Passagem $passagem)
    {
        $data = $request->validate(['motivo' => 'required|string|min:3|max:300'], ['motivo.required' => 'Informe o motivo.']);
        abort_if($passagem->cancelada, 422, 'Passagem já cancelada.');
        $tipoCancel = $request->input('cancel_tipo') === 'cancelamento' ? 'cancelamento' : 'devolucao';

        // Cancelamento PARCIAL: só alguns passageiros (quando há 2+). Se marcar todos, cai no fluxo normal abaixo.
        $paxIds = array_values(array_filter(array_map('intval', (array) $request->input('pax_ids', []))));
        if ($request->input('escopo_cancel') === 'parcial' && $paxIds) {
            $pagantes = $passagem->passageiros()->whereRaw("COALESCE(papel,'') <> 'colo'")->pluck('id')->all();
            $selec = array_values(array_intersect($paxIds, $pagantes));
            if (empty($selec)) return back()->with('erro', 'Selecione ao menos um passageiro válido.');
            if (count($selec) < count($pagantes)) {
                return $this->cancelarPassageirosParcial($passagem, $selec, $data['motivo'], $tipoCancel);
            }
            // selecionou todos -> segue cancelamento total normal
        }

        $empresa = \App\Support\Tenant::empresa();

        if ($empresa && $empresa->config('usa_cancel_transporte')) {
            // Politica por transporte: empresa retem uma % e reembolsa o resto (saida no caixa).
            $emb = $passagem->embarcacao;
            // O operador escolhe manualmente quanto devolver: integral (0%), antes (ex. 10%) ou depois (ex. 30%).
            $escolha = (string) $request->input('retencao', '');
            if ($escolha === 'integral') { $pct = 0; $depois = false; }
            elseif ($escolha === 'depois') { $pct = $emb ? $emb->cancelDepois() : 30; $depois = true; }
            elseif ($escolha === 'antes') { $pct = $emb ? $emb->cancelAntes() : 10; $depois = false; }
            else { $depois = $passagem->jaSaiu(); $pct = $depois ? ($emb ? $emb->cancelDepois() : 30) : ($emb ? $emb->cancelAntes() : 10); }
            $pago = (float) $passagem->pago;
            $retido = round($pago * $pct / 100, 2);
            $reembolso = round($pago - $retido, 2);

            if ($reembolso > 0.009) {
                \App\Models\Pagamento::create([
                    'empresa_id' => $empresa->id, 'modulo' => 'passagem', 'origem_id' => $passagem->id, 'encomenda_id' => null,
                    'meio' => 'dinheiro', 'valor' => $reembolso, 'user_id' => auth()->id(),
                    'momento' => 'baixa', 'tipo_mov' => 'saida', 'categoria' => 'Devolução ao passageiro',
                    'descricao' => 'Devolução '.($depois ? 'apos' : 'antes').' da viagem — devolvido '.(100 - $pct).'%',
                ]);
            }

            // O valor retido NAO fica como receita da empresa.
            $comPct = (int) ($emb->comissao_empresa_pct ?? 0);
            if ($passagem->acerto_dono && ! $passagem->revendedor_id && $emb) {
                // Passagem JA acertada com o dono (balcao): ele ja recebeu o repasse.
                // Ele devolve o repasse INTEIRO -> fica em debito e sera descontado no proximo acerto.
                // (Opcao B: o dono NAO fica com os 30%; o retido fica todo pra empresa.)
                $repasse = round(((float) $passagem->total) * (100 - $comPct) / 100, 2);
                if ($repasse > 0.009) {
                    \App\Models\Pagamento::create([
                        'empresa_id' => $empresa->id, 'modulo' => 'avulso', 'origem_id' => null, 'encomenda_id' => null,
                        'embarcacao_id' => $emb->id, 'meio' => 'dinheiro', 'valor' => $repasse, 'user_id' => auth()->id(),
                        'momento' => 'baixa', 'tipo_mov' => 'entrada', 'categoria' => 'Débito do dono (devolução)',
                        'descricao' => ($tipoCancel === 'cancelamento' ? 'Cancelamento' : 'Devolução').' da passagem '.$passagem->numeroFormatado().' (já acertada) · '.mb_substr((string) (optional($passagem->passageiros->first())->nome ?: $passagem->comprador_nome ?: ''), 0, 60),
                    ]);
                }
            } elseif ($retido > 0.009) {
                if ($depois) {
                    // Depois da saida: os 30% sao do DONO da lancha (lancha ja rodou).
                    // Sai do caixa da empresa (saida) e entra pro dono (entrada marcada no barco, so balcao).
                    \App\Models\Pagamento::create([
                        'empresa_id' => $empresa->id, 'modulo' => 'passagem', 'origem_id' => $passagem->id, 'encomenda_id' => null,
                        'meio' => 'dinheiro', 'valor' => $retido, 'user_id' => auth()->id(),
                        'momento' => 'baixa', 'tipo_mov' => 'saida', 'categoria' => 'Devolução — parte do dono',
                        'descricao' => 'Devolução apos a viagem — '.$pct.'% do dono'.($emb ? ' ('.$emb->nome.')' : ''),
                    ]);
                    if (! $passagem->revendedor_id && $emb) {
                        \App\Models\Pagamento::create([
                            'empresa_id' => $empresa->id, 'modulo' => 'avulso', 'origem_id' => null, 'encomenda_id' => null,
                            'embarcacao_id' => $emb->id,
                            'meio' => 'dinheiro', 'valor' => $retido, 'user_id' => auth()->id(),
                            'momento' => 'baixa', 'tipo_mov' => 'entrada', 'categoria' => 'Devolução (parte do dono)',
                            'descricao' => 'Devolução da passagem '.$passagem->numeroFormatado().' — '.$pct.'%',
                        ]);
                    }
                } else {
                    // Antes da saida: os 10% NAO entram no caixa da empresa — viram caixa local (troco dos funcionarios).
                    \App\Models\Pagamento::create([
                        'empresa_id' => $empresa->id, 'modulo' => 'passagem', 'origem_id' => $passagem->id, 'encomenda_id' => null,
                        'meio' => 'dinheiro', 'valor' => $retido, 'user_id' => auth()->id(),
                        'momento' => 'baixa', 'tipo_mov' => 'saida', 'categoria' => 'Devolução — caixa local',
                        'descricao' => 'Devolução antes da viagem — '.$pct.'% p/ caixa local (troco)',
                    ]);
                }
            }

            $passagem->forceFill([
                'cancelada' => true, 'motivo_cancelamento' => $data['motivo'],
                'cancel_retido' => $retido, 'cancel_reembolso' => $reembolso, 'cancel_fase' => $depois ? 'depois' : 'antes',
                'cancel_tipo' => $tipoCancel,
            ])->save();
            $passagem->recalcular();
            $msg = 'Devolução registrada. Devolvido R$ '.number_format($reembolso, 2, ',', '.').' ('.(100 - $pct).'%).';
            if ($passagem->acerto_dono && ! $passagem->revendedor_id && $emb) {
                $msg .= ' Esta passagem já tinha sido acertada com o dono — ele fica devendo R$ '.number_format(round(((float) $passagem->total) * (100 - (int) ($emb->comissao_empresa_pct ?? 0)) / 100, 2), 2, ',', '.').' à empresa (descontado no próximo acerto).';
            } else {
                $msg .= ' Retido '.$pct.'% (R$ '.number_format($retido, 2, ',', '.').') — '.($depois ? 'parte do dono' : 'caixa local').'.';
            }
            return redirect()->route('passagens.show', ['passagem' => $passagem, 'imprimir_cancel' => 1])->with('ok', $msg);
        }

        // Sem politica: estorna tudo (comportamento padrao).
        $passagem->forceFill(['cancelada' => true, 'motivo_cancelamento' => $data['motivo'], 'cancel_tipo' => $tipoCancel])->save();
        $passagem->pagamentos()->update(['estornado' => true]);
        $passagem->recalcular();
        return redirect()->route('passagens.show', ['passagem' => $passagem, 'imprimir_cancel' => 1])->with('ok', 'Passagem cancelada.');
    }

    /** Se o pago ficou MAIOR que o total (edição baixou valor ou cancelou passageiro), reduz o pagamento registrado pra bater. */
    protected function ajustarPagamentoAoTotal(Passagem $passagem): void
    {
        $passagem->refresh();
        $excesso = round((float) $passagem->pago - (float) $passagem->total, 2);
        if ($excesso <= 0.009) return;
        foreach ($passagem->pagamentos()->where('meio', '!=', 'desconto')->orderByDesc('id')->get() as $pg) {
            if ($excesso <= 0.009) break;
            $corte = min((float) $pg->valor, $excesso);
            $novo = round((float) $pg->valor - $corte, 2);
            if ($novo <= 0.009) $pg->delete(); else $pg->forceFill(['valor' => $novo])->save();
            $excesso = round($excesso - $corte, 2);
        }
        $passagem->recalcular();
    }

    /** Cancela apenas alguns passageiros da passagem: remove, libera poltrona e recalcula. Devolução em dinheiro é manual no caixa. */
    protected function cancelarPassageirosParcial(Passagem $passagem, array $ids, string $motivo, string $tipo = 'devolucao')
    {
        $alvos = $passagem->passageiros()->whereIn('id', $ids)->get();
        if ($alvos->isEmpty()) return back()->with('erro', 'Passageiro(s) não encontrado(s).');
        $nomes = $alvos->pluck('nome')->map(fn ($n) => trim((string) $n))->filter()->implode(', ');
        $valor = (float) $alvos->sum('valor_total');

        \Illuminate\Support\Facades\DB::transaction(function () use ($passagem, $ids, $motivo, $tipo) {
            $marca = ['cancelado' => true, 'cancelado_em' => now(), 'cancelado_motivo' => $motivo, 'cancelado_tipo' => $tipo];
            $passagem->passageiros()->whereIn('colo_de_id', $ids)->update($marca); // bebês de colo ligados
            $passagem->passageiros()->whereIn('id', $ids)->update($marca);
            $passagem->recalcular();
            $this->ajustarPagamentoAoTotal($passagem); // pago desce pra bater (cancelou passageiro)
        });

        $nota = '['.now()->format('d/m/Y H:i').'] Cancelado(s): '.$nomes.' — '.$motivo.' ('.(auth()->user()->name ?? '').'). Valor: R$ '.number_format($valor, 2, ',', '.').'.';
        $obs = trim((string) $passagem->observacao);
        $passagem->forceFill(['observacao' => trim($obs."\n".$nota)])->save();

        $msg = 'Passageiro(s) cancelado(s): '.$nomes.'. Poltrona liberada e valores recalculados.';
        if ($valor > 0.009) $msg .= ' Se houver devolução (R$ '.number_format($valor, 2, ',', '.').'), registre no caixa (saída avulsa).';
        return redirect()->route('passagens.show', $passagem)->with('ok', $msg);
    }

    /** Tela de travar poltronas (não vendáveis) por transporte/viagem. */
    public function bloqueios(Request $request)
    {
        $embarcacoes = Embarcacao::where('ativo', true)->where('usa_poltrona', true)->orderBy('nome')
            ->get(['id', 'nome', 'usa_poltrona', 'poltronas_qtd', 'poltronas_colunas', 'poltronas_lista']);

        $embId = $request->integer('embarcacao_id') ?: ($embarcacoes->first()->id ?? null);
        $dataViagem = $request->input('data_viagem') ?: null;
        $emb = $embId ? $embarcacoes->firstWhere('id', $embId) : null;

        $vendidas = [];
        $bloqueadas = collect();
        $datasDisponiveis = collect();
        if ($emb) {
            $vendidas = Passagem::where('embarcacao_id', $emb->id)->where('cancelada', false)->where('transferida', false)
                ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))
                ->with(['passageiros:id,passagem_id,poltrona,cancelado'])->get()
                ->flatMap(fn ($p) => $p->passageiros->where('cancelado', false)->pluck('poltrona'))
                ->filter()->map(fn ($v) => (string) $v)->unique()->values()->all();

            $bloqueadas = PoltronaBloqueada::where('embarcacao_id', $emb->id)->where('ativo', true)
                ->orderByRaw('data_viagem IS NULL DESC')->orderBy('data_viagem')->orderBy('poltrona')->get();

            // Datas pro dropdown: próximas viagens programadas (dia-da-semana da rota) + datas que já têm venda.
            $hoje = now()->startOfDay();
            $datas = collect();
            $rotas = Rota::where('embarcacao_id', $emb->id)->where('ativo', true)->with('paradas')->get();
            foreach ($rotas as $rota) {
                $partida = $rota->paradas->first();
                if (! $partida || $partida->dia_semana === null) continue;
                $dia = (int) $partida->dia_semana;
                $base = $hoje->copy();
                while ($base->dayOfWeek !== $dia) $base->addDay();
                for ($k = 0; $k < 10; $k++) $datas->push($base->copy()->addWeeks($k)->toDateString());
            }
            // datas já vendidas (futuras e recentes) pra não perder nenhuma viagem real
            Passagem::where('embarcacao_id', $emb->id)->where('cancelada', false)->whereNotNull('data_viagem')
                ->whereBetween('data_viagem', [$hoje->copy()->subMonthNoOverflow()->toDateString(), $hoje->copy()->addMonthsNoOverflow(3)->toDateString()])
                ->select('data_viagem')->distinct()->pluck('data_viagem')
                ->each(fn ($d) => $datas->push(\Illuminate\Support\Carbon::parse($d)->toDateString()));
            // a data filtrada atual sempre aparece (mesmo que fora da janela)
            if ($dataViagem) $datas->push(\Illuminate\Support\Carbon::parse($dataViagem)->toDateString());
            $datasDisponiveis = $datas->unique()->sort()->values();
        }

        $usuarios = \App\Models\User::where('ativo', true)->orderBy('name')->get(['id', 'name', 'is_revendedor']);

        return view('passagens.bloqueios', compact('embarcacoes', 'embId', 'dataViagem', 'emb', 'vendidas', 'bloqueadas', 'datasDisponiveis', 'usuarios'));
    }

    /** Trava uma ou mais poltronas pra não serem vendidas. */
    public function bloquearPoltrona(Request $request)
    {
        $data = $request->validate([
            'embarcacao_id' => 'required|integer',
            'data_viagem' => 'nullable|date',
            'motivo' => 'nullable|string|max:120',
            'poltronas' => 'required|array|min:1',
            'poltronas.*' => 'string|max:10',
            'escopo_tipo' => 'nullable|in:todos,operadores,revendedores,ia,usuario',
            'escopo_id' => 'nullable|integer',
        ]);
        $emb = Embarcacao::findOrFail($data['embarcacao_id']);
        $dataViagem = $data['data_viagem'] ?? null;

        $escopoTipo = $data['escopo_tipo'] ?? 'todos';
        $escopoId = $escopoTipo === 'usuario' ? ($data['escopo_id'] ?? null) : null;
        if ($escopoTipo === 'usuario' && ! $escopoId) $escopoTipo = 'todos'; // escolheu "pessoa" mas não indicou quem -> todos

        $n = 0;
        foreach (array_unique($data['poltronas']) as $pol) {
            $pol = trim((string) $pol);
            if ($pol === '') continue;
            $existe = PoltronaBloqueada::where('embarcacao_id', $emb->id)->where('poltrona', $pol)->where('ativo', true)
                ->where('escopo_tipo', $escopoTipo)
                ->when($escopoTipo === 'usuario', fn ($q) => $q->where('escopo_id', $escopoId))
                ->where(function ($w) use ($dataViagem) {
                    $w->whereNull('data_viagem');
                    if ($dataViagem) $w->orWhereDate('data_viagem', $dataViagem);
                })->exists();
            if ($existe) continue;
            PoltronaBloqueada::create([
                'empresa_id' => Tenant::id(), 'embarcacao_id' => $emb->id, 'poltrona' => $pol,
                'data_viagem' => $dataViagem, 'motivo' => $data['motivo'] ?? null,
                'ativo' => true, 'user_id' => auth()->id(),
                'escopo_tipo' => $escopoTipo, 'escopo_id' => $escopoId,
            ]);
            $n++;
        }

        $rotulo = $dataViagem ? ('a viagem de '.\Illuminate\Support\Carbon::parse($dataViagem)->format('d/m/Y')) : 'TODAS as viagens';
        return back()->with('ok', $n > 0 ? ($n.' poltrona(s) travada(s) para '.$rotulo.'.') : 'Nenhuma poltrona nova (já estavam travadas).');
    }

    /** Libera (remove) uma poltrona travada. */
    public function desbloquearPoltrona(Request $request)
    {
        $data = $request->validate(['id' => 'required|integer']);
        $b = PoltronaBloqueada::findOrFail($data['id']);
        $pol = $b->poltrona;
        $b->delete();
        return back()->with('ok', 'Poltrona '.$pol.' liberada pra venda.');
    }

    /** FASE 1 — Transferência GLOBAL de transporte: move TODOS os passageiros de uma viagem pra outra lancha. */
    public function transferencia(Request $request)
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isSuper(), 403);

        $embarcacoes = Embarcacao::where('ativo', true)->where('usa_poltrona', true)->orderBy('nome')
            ->get(['id', 'nome', 'usa_poltrona', 'poltronas_qtd', 'poltronas_colunas', 'poltronas_lista']);

        $origemId = $request->integer('origem_id') ?: null;
        $destinoId = $request->integer('destino_id') ?: null;
        $dataViagem = $request->input('data_viagem');

        $mapEmb = fn ($e) => $e ? [
            'id' => $e->id, 'nome' => $e->nome,
            'qtd' => (int) $e->poltronas_qtd, 'colunas' => (int) ($e->poltronas_colunas ?: 4),
            'layout' => (string) ($e->poltronas_lista ?? ''),
        ] : null;

        // datas disponiveis da embarcacao de origem (viagens com passageiros)
        $datas = $origemId
            ? Passagem::where('embarcacao_id', $origemId)->where('cancelada', false)->where('transferida', false)
                ->whereNotNull('data_viagem')->whereDate('data_viagem', '>=', now()->subMonthNoOverflow()->toDateString())
                ->select('data_viagem')->distinct()->orderBy('data_viagem')->pluck('data_viagem')
            : collect();

        $dados = null;
        if ($origemId && $dataViagem) {
            $origem = Embarcacao::find($origemId);
            $passagens = Passagem::where('embarcacao_id', $origemId)->where('cancelada', false)->where('transferida', false)
                ->whereDate('data_viagem', $dataViagem)
                ->with(['passageiros:id,passagem_id,nome,poltrona,papel,tipo,cancelado'])
                ->orderBy('id')->get();

            $pax = [];
            foreach ($passagens as $p) {
                foreach ($p->passageiros as $x) {
                    if ($x->cancelado) continue;
                    $pax[] = [
                        'passageiro_id' => $x->id,
                        'passagem_id' => $p->id,
                        'nome' => $x->nome,
                        'tipo' => (string) ($x->papel ?: $x->tipo),
                        'colo' => ($x->papel ?? '') === 'colo',
                        'poltrona' => (string) ($x->poltrona ?? ''),
                    ];
                }
            }

            $destino = $destinoId ? Embarcacao::find($destinoId) : null;
            $ocupDestino = $destino
                ? $this->ocupadas($destino->id, $dataViagem, null)->map(fn ($v) => (string) $v)->values()->all()
                : [];

            $dados = [
                'origem' => $mapEmb($origem),
                'destino' => $destino ? array_merge($mapEmb($destino), ['ocupadas' => $ocupDestino]) : null,
                'passageiros' => $pax,
                'data_viagem' => $dataViagem,
            ];
        }

        return view('passagens.transferencia', compact('embarcacoes', 'origemId', 'destinoId', 'dataViagem', 'dados', 'datas'));
    }

    /** FASE 1 — processa a transferência global: atualiza embarcação + poltrona NO LUGAR (sem cancelar/recriar bilhete). */
    public function transferenciaProcessar(Request $request)
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isSuper(), 403);

        $data = $request->validate([
            'origem_id' => 'required|integer',
            'destino_id' => 'required|integer|different:origem_id',
            'data_viagem' => 'required|date',
            'mapa' => 'required|array|min:1',
            'mapa.*.passageiro_id' => 'required|integer',
            'mapa.*.poltrona' => 'nullable|string|max:10',
            'valor_destino' => 'nullable|in:0,1',
        ]);
        // As VENDAS (o valor) acompanham os passageiros pro transporte de destino? Padrão: NÃO (ficam com a origem).
        $valorVaiJunto = (string) ($data['valor_destino'] ?? '0') === '1';
        $origemEmb = Embarcacao::findOrFail($data['origem_id']);
        $ficouNaOrigem = 0.0;

        $destino = Embarcacao::findOrFail($data['destino_id']);

        // valida conflitos: poltronas novas únicas + não ocupadas por OUTRAS viagens no destino
        $ocupDestino = $this->ocupadas($destino->id, $data['data_viagem'], null)->map(fn ($v) => (string) $v)->all();
        $novas = [];
        foreach ($data['mapa'] as $m) {
            $pol = trim((string) ($m['poltrona'] ?? ''));
            if ($pol === '') continue; // colo / sem poltrona
            if (in_array($pol, $novas, true)) abort(422, "Poltrona repetida na transferência: $pol");
            if (in_array($pol, $ocupDestino, true)) abort(422, "A poltrona $pol já está ocupada no transporte de destino.");
            $novas[] = $pol;
        }

        $poltronaPorPax = collect($data['mapa'])->keyBy('passageiro_id');
        $paxIds = $poltronaPorPax->keys()->all();

        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $destino, $poltronaPorPax, $paxIds, $valorVaiJunto, $origemEmb, &$ficouNaOrigem) {
            $passagens = Passagem::where('embarcacao_id', $data['origem_id'])
                ->whereDate('data_viagem', $data['data_viagem'])
                ->where('cancelada', false)->where('transferida', false)
                ->whereHas('passageiros', fn ($q) => $q->whereIn('id', $paxIds))
                ->with('passageiros')->get();

            foreach ($passagens as $p) {
                $p->forceFill([
                    'embarcacao_id' => $destino->id,
                    'viagem_id' => null,
                ])->save();
                foreach ($p->passageiros as $x) {
                    if ($poltronaPorPax->has($x->id)) {
                        $nova = trim((string) ($poltronaPorPax[$x->id]['poltrona'] ?? ''));
                        // guarda de onde veio (sai na via do passageiro no check-in). Se já foi transferido antes, mantém a origem ORIGINAL.
                        $hist = $x->transf_de_lancha ? [] : ['transf_de_lancha' => $origemEmb->nome, 'transf_de_poltrona' => $x->poltrona, 'transf_de_bilhete' => $p->numeroFormatado()];
                        $x->forceFill(['poltrona' => $nova !== '' ? $nova : null, 'transf_em' => now()] + $hist)->save();
                    }
                }
            }

            // Vendas FICAM com o transporte de origem: o passageiro viaja na lancha nova, mas o dinheiro continua do dono antigo.
            // Lança o ajuste na PRESTAÇÃO dos dois transportes (não mexe no caixa da agência): sai do destino, entra na origem.
            if (! $valorVaiJunto) {
                $base = (float) $passagens->filter(fn ($p) => empty($p->revendedor_id) && ! $p->acerto_dono)->sum(fn ($p) => (float) $p->total);
                if ($base > 0.009) {
                    $parteDest = round($base * (100 - (int) $destino->comissao_empresa_pct) / 100, 2);
                    $parteOrig = round($base * (100 - (int) $origemEmb->comissao_empresa_pct) / 100, 2);
                    $comum = [
                        'empresa_id' => \App\Support\Tenant::id(), 'modulo' => 'avulso', 'origem_id' => null, 'encomenda_id' => null,
                        'meio' => 'dinheiro', 'user_id' => auth()->id(), 'momento' => 'cadastro', 'categoria' => 'Transferência de transporte',
                        'data_viagem' => $data['data_viagem'], 'acerto_conta' => false, 'acerto_pct' => 0,
                        'conferido' => true, 'conferido_em' => now(), 'conferido_por' => auth()->id(),
                    ];
                    if ($parteDest > 0) \App\Models\Pagamento::create($comum + ['tipo_mov' => 'saida', 'valor' => $parteDest, 'embarcacao_id' => $destino->id,
                        'descricao' => 'Passageiros vieram de '.$origemEmb->nome.' — vendas ficaram com o transporte de origem']);
                    if ($parteOrig > 0) \App\Models\Pagamento::create($comum + ['tipo_mov' => 'entrada', 'valor' => $parteOrig, 'embarcacao_id' => $origemEmb->id,
                        'descricao' => 'Passageiros foram para '.$destino->nome.' — vendas continuam deste transporte']);
                    $ficouNaOrigem = $base;
                }
            }
        });

        return redirect()->route('passagens.transferencia', [
            'origem_id' => $data['destino_id'], 'destino_id' => null, 'data_viagem' => $data['data_viagem'],
        ])->with('ok', 'Transferência concluída: passageiros movidos para '.$destino->nome.'. Check-in, manifesto e etiquetas já usam a lancha e poltrona novas. '
            .($valorVaiJunto ? 'As vendas foram junto para '.$destino->nome.'.' : ($ficouNaOrigem > 0 ? 'As vendas (R$ '.number_format($ficouNaOrigem, 2, ',', '.').') continuam com '.$origemEmb->nome.' — o ajuste foi lançado na prestação dos dois transportes.' : 'As vendas continuam com '.$origemEmb->nome.' (não havia valor de balcão em aberto pra ajustar).')));
    }

    /** Transfere a passagem pra outro transporte: cria nova (paga) no destino e lanca a despesa no transporte de origem. */
    public function transferir(Request $request, Passagem $passagem)
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isSuper(), 403);
        abort_if($passagem->cancelada || $passagem->transferida, 422, 'Passagem já cancelada ou transferida.');
        $empresa = \App\Support\Tenant::empresa();
        abort_unless($empresa && $empresa->config('usa_cancel_transporte'), 422, 'Transferência de transporte não está habilitada.');
        $data = $request->validate([
            'embarcacao_id' => 'required|integer|exists:embarcacoes,id',
            'data_viagem' => 'nullable|date',
            'motivo' => 'nullable|string|max:300',
            'poltronas' => 'nullable|array',
        ]);
        $poltronasIn = collect((array) $request->input('poltronas', []))->map(fn ($v) => strtoupper(trim((string) $v)))->all();

        // Evita duplicar poltrona no transporte destino (usa as poltronas escolhidas no formulario).
        $destino = Embarcacao::find($data['embarcacao_id']);
        if ($destino && $destino->usa_poltrona) {
            $seats = $passagem->passageiros->map(fn ($px) => (string) ($poltronasIn[$px->id] ?? $px->poltrona ?? ''))->filter()->values();
            $dups = $seats->duplicates()->values();
            if ($dups->isNotEmpty()) {
                return back()->with('erro', 'Poltrona(s) repetida(s) na transferência: '.$dups->implode(', ').'.');
            }
            if ($seats->isNotEmpty()) {
                $dv = $data['data_viagem'] ?? $passagem->data_viagem;
                $ocup = $this->ocupadas($destino->id, $dv, null, $passagem->rota_id, $passagem->origem, $passagem->destino);
                $conf = $seats->intersect($ocup)->values();
                if ($conf->isNotEmpty()) {
                    return back()->with('erro', 'Poltrona(s) '.$conf->implode(', ').' já vendida(s) no '.$destino->nome.' nesse trecho. Escolha poltronas livres.');
                }
            }
        }

        $op = auth()->user();
        $valor = (float) $passagem->pago;

        $nova = \Illuminate\Support\Facades\DB::transaction(function () use ($passagem, $data, $empresa, $op, $valor, $poltronasIn, $destino) {
            $seq = $op->proximoSeq();
            $pref = $op->prefixo ?: ('OP'.$op->id);
            $nova = $passagem->replicate([
                'uuid', 'numero', 'numero_completo', 'embarcou', 'embarcou_em', 'embarcou_por',
                'cancelada', 'transferida', 'transferida_para_id', 'motivo_cancelamento',
                'acerto_empresa', 'acerto_empresa_em', 'acerto_empresa_por',
                'cancel_retido', 'cancel_reembolso', 'cancel_fase',
            ]);
            $nova->fill([
                'embarcacao_id' => $data['embarcacao_id'],
                'data_viagem' => $data['data_viagem'] ?? $passagem->data_viagem,
                'numero' => $seq,
                'numero_completo' => $pref.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'user_id' => $op->id,
                'origem_transferencia_id' => $passagem->id,
            ]);
            $nova->save();

            foreach ($passagem->passageiros as $pax) {
                $np = $pax->replicate(['checkin', 'embarcou']);
                $np->passagem_id = $nova->id;
                $np->checkin = false;
                // de onde veio: lancha, poltrona e bilhete anteriores (sai na via do passageiro no check-in)
                if (! $np->transf_de_lancha) {
                    $np->transf_de_lancha = optional($passagem->embarcacao)->nome;
                    $np->transf_de_poltrona = $pax->poltrona;
                    $np->transf_de_bilhete = $passagem->numeroFormatado();
                }
                $np->transf_em = now();
                if ($destino && $destino->usa_poltrona && array_key_exists($pax->id, $poltronasIn)) {
                    $np->poltrona = $poltronasIn[$pax->id] ?: null;
                }
                $np->save();
            }
            $nova->recalcular();

            // entrada (transferencia recebida) no transporte destino
            \App\Models\Pagamento::create([
                'empresa_id' => $empresa->id, 'modulo' => 'passagem', 'origem_id' => $nova->id, 'encomenda_id' => null,
                'meio' => 'dinheiro', 'valor' => $valor, 'user_id' => $op->id, 'momento' => 'baixa', 'tipo_mov' => 'entrada',
                'categoria' => 'Transferência recebida', 'descricao' => 'Transferido da passagem '.$passagem->numeroFormatado(),
            ]);
            $nova->recalcular();

            // despesa de transferencia no transporte de origem (fecha o valor)
            \App\Models\Pagamento::create([
                'empresa_id' => $empresa->id, 'modulo' => 'passagem', 'origem_id' => $passagem->id, 'encomenda_id' => null,
                'meio' => 'dinheiro', 'valor' => $valor, 'user_id' => $op->id, 'momento' => 'baixa', 'tipo_mov' => 'saida',
                'categoria' => 'Transferência de transporte', 'descricao' => 'Transferido para a passagem '.$nova->numeroFormatado(),
            ]);

            // marca a original como transferida (sai das listas; dinheiro equilibrado)
            $passagem->forceFill([
                'cancelada' => true, 'transferida' => true, 'transferida_para_id' => $nova->id,
                'motivo_cancelamento' => $data['motivo'] ?? 'Transferência de transporte',
            ])->save();

            return $nova;
        });

        return redirect()->route('passagens.show', $nova)->with('ok', 'Transferida. Nova passagem: '.$nova->numeroFormatado());
    }

    /** Troca a DATA da viagem (remarca) no mesmo transporte. Nao mexe no dinheiro. */
    public function trocarViagem(Request $request, Passagem $passagem)
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isSuper(), 403);
        abort_if($passagem->cancelada || $passagem->transferida, 422, 'Passagem cancelada ou transferida.');
        $data = $request->validate([
            'data_viagem' => 'required|date',
            'motivo' => 'nullable|string|max:300',
            'poltronas' => 'nullable|array',
        ]);
        $poltronasIn = collect((array) $request->input('poltronas', []))->map(fn ($v) => strtoupper(trim((string) $v)))->all();
        $antiga = $passagem->data_viagem ? \Carbon\Carbon::parse($passagem->data_viagem)->format('d/m/Y') : '—';
        $nova = \Carbon\Carbon::parse($data['data_viagem']);
        $viagemId = $passagem->viagem_id;
        if ($passagem->rota_id && ($rota = \App\Models\Rota::find($passagem->rota_id))) {
            $viagemId = $rota->viagemPara($nova)->id;
        }

        // Evita duplicar poltrona na nova data (mesmo transporte).
        $embP = $passagem->embarcacao;
        if ($embP && $embP->usa_poltrona) {
            $seats = $passagem->passageiros->map(fn ($px) => (string) ($poltronasIn[$px->id] ?? $px->poltrona ?? ''))->filter()->values();
            $dups = $seats->duplicates()->values();
            if ($dups->isNotEmpty()) {
                return back()->with('erro', 'Poltrona(s) repetida(s): '.$dups->implode(', ').'.');
            }
            if ($seats->isNotEmpty()) {
                $ocup = $this->ocupadas($embP->id, $nova->toDateString(), $viagemId, $passagem->rota_id, $passagem->origem, $passagem->destino, $passagem->id);
                $conf = $seats->intersect($ocup)->values();
                if ($conf->isNotEmpty()) {
                    return back()->with('erro', 'Poltrona(s) '.$conf->implode(', ').' já ocupada(s) em '.$nova->format('d/m/Y').'. Escolha outra poltrona livre.');
                }
            }
        }

        $obs = trim(($passagem->observacao ? $passagem->observacao."\n" : '').'Viagem remarcada de '.$antiga.' para '.$nova->format('d/m/Y').($data['motivo'] ? ' ('.$data['motivo'].')' : ''));
        $passagem->forceFill(['data_viagem' => $nova->toDateString(), 'viagem_id' => $viagemId, 'observacao' => $obs])->save();
        if ($embP && $embP->usa_poltrona && $poltronasIn) {
            foreach ($passagem->passageiros as $pax) {
                if (array_key_exists($pax->id, $poltronasIn)) {
                    $pax->poltrona = $poltronasIn[$pax->id] ?: null;
                    $pax->save();
                }
            }
        }
        return redirect()->route('passagens.show', $passagem)->with('ok', 'Viagem remarcada para '.$nova->format('d/m/Y').'.');
    }
}
