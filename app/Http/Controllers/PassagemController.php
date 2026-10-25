<?php

namespace App\Http\Controllers;

use App\Models\Camarote;
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
            'soAvista' => $u->revendedorDono() !== null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        if (auth()->user()->revendedorDono() !== null) { $data['forma'] = 'avista'; }
        $this->checarPoltronas($data);
        $p = app(\App\Services\PassagemService::class)->criar($data, auth()->id(), $request->input('uuid'));
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

        $rotas = Tenant::empresa()->temRecurso('viagens') ? Rota::paraSelecao() : collect();
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

    /** Lista de passageiros (manifesto) do filtro atual — inclui ocupantes de camarote. */
    public function manifesto(Request $request)
    {
        $f = $this->filtros($request);
        $passagens = Passagem::filtrar($f)->with(['passageiros', 'camarote', 'embarcacao'])
            ->orderBy('data_viagem')->orderBy('origem')->orderBy('id')->get();

        $linhas = collect();
        foreach ($passagens as $p) {
            foreach ($p->passageiros as $pax) {
                $linhas->push((object) [
                    'nome' => $pax->nome,
                    'documento' => $pax->documento,
                    'nacionalidade' => $pax->nacionalidade ?: 'Brasil',
                    'nascimento' => $pax->nascimento,
                    'destino' => $p->destino,
                    'numero' => $p->numeroFormatado(),
                    'camarote' => $p->tipo === 'camarote' ? ($p->camarote?->nome ?: 'Camarote') : null,
                ]);
            }
        }

        // cabeçalho da lista (igual ao modelo da Marinha)
        $embs = $passagens->map(fn ($p) => $p->embarcacao?->nome)->filter()->unique()->values();
        $origens = $passagens->pluck('origem')->filter()->unique()->values();
        $destinos = $passagens->pluck('destino')->filter()->unique()->values();
        $datas = $passagens->pluck('data_viagem')->filter()->unique()->values();
        $hora = optional($passagens->first()?->embarcacao)->nome ? null : null;

        $cab = [
            'embarcacao' => $embs->count() === 1 ? $embs->first() : ($embs->count() > 1 ? 'Vários' : '—'),
            'porto_part' => $origens->count() === 1 ? $origens->first() : ($origens->count() > 1 ? 'Vários' : '—'),
            'porto_cheg' => $destinos->count() === 1 ? $destinos->first() : ($destinos->count() > 1 ? 'Vários' : '—'),
            'data' => $datas->count() === 1 ? \Carbon\Carbon::parse($datas->first())->format('d/m/Y') : (($f['inicio'] ?? '') && ($f['fim'] ?? '') ? ($f['inicio'].' a '.$f['fim']) : '—'),
        ];

        return view('passagens.manifesto', [
            'linhas' => $linhas,
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
            'forma' => 'required|in:avista,areceber,faturado',
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
            'imprimir' => 'nullable|in:navegador,direto,nao',
        ]);

        $cam = Camarote::where('id', $data['camarote_id'])->firstOrFail();
        abort_unless($cam->empresa_id === Tenant::id(), 404);
        abort_if($data['origem'] === $data['destino'], 422, 'Origem e destino não podem ser iguais.');
        if (auth()->user()->revendedorDono() !== null) $data['forma'] = 'avista';

        $base = $cam->precoPara($data['origem'], $data['destino']);
        abort_if($base === null, 422, 'Não há preço cadastrado para esse trecho neste camarote.');
        $cap = max((int) $cam->capacidade, 1);

        // não vender o mesmo camarote em trecho que se sobrepõe na mesma viagem
        $conf = $this->camaroteConflito($cam->id, $data['rota_id'] ?? null, $data['data_viagem'] ?? null, $data['viagem_id'] ?? null, $data['origem'], $data['destino']);
        if ($conf) {
            $num = $conf->numero_completo ?: $conf->numero;
            abort(422, "Esse camarote ({$cam->nome}) já está ocupado/reservado nessa viagem no trecho {$conf->origem} → {$conf->destino} (Nº {$num}".($conf->comprador_nome ? ', '.$conf->comprador_nome : '').").");
        }

        $passageiros = [];
        foreach (array_values($data['ocupantes']) as $i => $oc) {
            if ($i === 0) { $papel = 'titular'; $valor = (float) $base; $obs = 'Camarote '.$cam->nome; }
            elseif ($i < $cap) { $papel = 'incluido'; $valor = 0.0; $obs = 'Incluído no camarote'; }
            else { $papel = 'adicional'; $valor = round((float) ($oc['adicional'] ?? $cam->valor_adicional), 2); $obs = 'Adicional'; }
            $passageiros[] = [
                'nome' => $oc['nome'], 'tipo' => 'Camarote', 'papel' => $papel, 'percentual' => 100,
                'documento' => $oc['documento'] ?? null, 'nascimento' => $oc['nascimento'] ?? null,
                'telefone' => $oc['telefone'] ?? null, 'checkin' => (bool) ($oc['checkin'] ?? true),
                'valor_passagem' => $valor, 'valor_taxa' => 0, 'valor_alimentacao' => 0, 'valor_integral' => $valor,
                'observacao' => $obs,
            ];
        }

        $payload = [
            'tipo' => 'camarote', 'camarote_id' => $cam->id,
            'origem' => $data['origem'], 'destino' => $data['destino'],
            'rota_id' => $data['rota_id'] ?? null, 'viagem_id' => $data['viagem_id'] ?? null,
            'data_viagem' => $data['data_viagem'] ?? null,
            'comprador_nome' => $data['comprador_nome'] ?? null,
            'comprador_telefone' => $data['comprador_telefone'] ?? null,
            'comprador_documento' => $data['comprador_documento'] ?? null,
            'forma' => $data['forma'], 'observacao' => $data['observacao'] ?? null,
            'desconto' => $data['desconto'] ?? 0,
            'passageiros' => $passageiros,
            'pagamentos' => $data['pagamentos'] ?? [],
        ];

        $p = app(\App\Services\PassagemService::class)->criar($payload, auth()->id(), $request->input('uuid'));
        session(['ultima_origem_pass' => $data['origem'], 'ultima_suite_cam' => $cam->id]);
        $imprimir = $request->input('imprimir', 'navegador');
        return redirect()->route('passagens.show', ['passagem' => $p, 'print' => $imprimir === 'navegador' ? 1 : 0])
            ->with('ok', "Camarote {$cam->nome} · passagem {$p->numeroFormatado()} salva.");
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
        $passagem->passageiros()->update(['checkin' => true]); // confirmar embarque marca o check-in dos passageiros
        return redirect()->route('passagens.show', $passagem)->with('ok', 'Embarque confirmado.');
    }

    /** Comprovante 80mm (impressão/PDF) com QR de check-in. */
    /** Minhas vendas (revendedor/vendedor): resumo do que vendeu no periodo + o que falta acertar. */
    public function minhasVendas(Request $request)
    {
        $u = auth()->user();
        [$inicio, $fim, $periodo] = \App\Http\Controllers\CaixaController::periodo($request);

        $vendas = Passagem::where('user_id', $u->id)->where('cancelada', false)
            ->whereBetween('created_at', [$inicio, $fim])
            ->with(['passageiros:id,passagem_id', 'embarcacao:id,nome'])
            ->orderByDesc('id')->get();

        $totais = [
            'qtd'         => $vendas->count(),
            'passageiros' => (int) $vendas->sum(fn ($p) => $p->passageiros->count()),
            'total'       => (float) $vendas->sum(fn ($p) => (float) $p->total),
            'recebido'    => (float) $vendas->sum(fn ($p) => (float) $p->total - (float) $p->devido),
            'devido'      => (float) $vendas->sum(fn ($p) => (float) $p->devido),
        ];

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

        return view('passagens.minhas-vendas', compact('vendas', 'totais', 'acerto', 'inicio', 'fim', 'periodo'));
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

    public function comprovante(Passagem $passagem)
    {
        abort_unless($passagem->empresa_id === Tenant::id(), 404);
        $passagem->load(['passageiros', 'embarcacao', 'operador']);
        return view('passagens.comprovante', ['p' => $passagem, 'empresa' => Tenant::empresa(), 'modo' => request('modo')]);
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
