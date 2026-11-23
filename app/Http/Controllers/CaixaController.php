<?php

namespace App\Http\Controllers;

use App\Models\Encomenda;
use App\Models\Evento;
use App\Models\Pagamento;
use App\Models\User;
use App\Models\Viagem;
use App\Models\AvulsoCategoria;
use App\Support\Tenant;
use Illuminate\Http\Request;

class CaixaController extends Controller
{

    /** "Pulso" leve: hash da atividade recente (vendas/baixas) pra telas atualizarem sozinhas sem pesar. */
    public function pulse()
    {
        $desde = now()->subDays(3);
        $pg = \App\Models\Passagem::where('updated_at', '>=', $desde)
            ->selectRaw("COUNT(*) c, COALESCE(MAX(updated_at), '') m")->first();
        $py = \App\Models\Pagamento::where('created_at', '>=', $desde)
            ->selectRaw("COUNT(*) c, COALESCE(MAX(created_at), '') m, COALESCE(SUM(valor), 0) v")->first();
        return response()->json(['hash' => md5(($pg->c ?? 0).'|'.($pg->m ?? '').'|'.($py->c ?? 0).'|'.($py->m ?? '').'|'.($py->v ?? 0))]);
    }

    /** Contadores dos badges do menu (atendimento aguardando, pré-cadastros) em tempo real. */
    public function badges()
    {
        $atend = 0; $pre = 0;
        try { $atend = \App\Models\Conversa::where('status', 'aguardando')->count(); } catch (\Throwable $e) {}
        try { $pre = \App\Models\PreCadastro::where('status', 'pendente')->count(); } catch (\Throwable $e) {}
        return response()->json(['atendimento' => $atend, 'precadastro' => $pre]);
    }
    /** RELATÓRIO = mesma dashboard do caixa (mesmo layout), escopada pra relatório. */
    public function relatorio(Request $request)
    {
        $request->merge(['__relatorio' => true]);
        return $this->index($request);
    }

    public function index(Request $request)
    {
        [$inicio, $fim, $periodo] = $this->periodo($request);
        // escopo: quem não vê "de todos" fica travado no próprio
        $ehRelatorio = $request->boolean('__relatorio');
        $verTodos = $ehRelatorio ? auth()->user()->relatorioTodos() : auth()->user()->caixaTodos();
        $userId = $verTodos ? $request->input('user_id') : auth()->id();
        $viagemId = $request->input('viagem_id');
        $modulo = $request->input('modulo');
        $canal = $request->input('canal'); // dinheiro|pix_manual|pix_auto|debito|credito
        $podeTransporte = auth()->user()->podeFiltrarTransporte();
        $transporteId = $podeTransporte ? $request->input('transporte_id') : null;
        $transportes = \App\Models\Embarcacao::orderBy('nome')->get();
        $dataViagem = $podeTransporte ? ($request->input('data_viagem') ?: null) : null;
        $datasViagem = $transporteId
            ? \App\Models\Passagem::where('embarcacao_id', $transporteId)->where('cancelada', false)->whereNotNull('data_viagem')
                ->whereBetween('data_viagem', [now()->subMonthsNoOverflow(3)->toDateString(), now()->addMonthsNoOverflow(3)->toDateString()])
                ->select('data_viagem')->distinct()->orderByDesc('data_viagem')->pluck('data_viagem')
            : collect();
        $filtraDataViagem = fn ($q) => $q->where('modulo', 'passagem')->whereIn('origem_id', \App\Models\Passagem::whereDate('data_viagem', $dataViagem)->select('id'));
        $filtraTransp = fn ($q) => $q->where(function ($w) use ($transporteId) {
            $w->where(fn ($x) => $x->where('modulo', 'passagem')->whereIn('origem_id', \App\Models\Passagem::where('embarcacao_id', $transporteId)->select('id')))
              ->orWhere(fn ($x) => $x->where('modulo', 'encomenda')->whereIn('encomenda_id', \App\Models\Encomenda::where('embarcacao_id', $transporteId)->select('id')));
        });
        $viagens = \App\Support\Tenant::empresa()->temRecurso('viagens')
            ? Viagem::orderByDesc('data_partida')->limit(60)->get() : collect();
        // quando filtra por viagem, ignora o período (viagem é o recorte)
        $encIds = $viagemId ? Encomenda::where('viagem_id', $viagemId)->pluck('id') : null;
        // venda de agente de repasse (revendedor) NAO entra no caixa ate o acerto; o repasse vira entrada na baixa
        $revendaIds = \App\Models\Passagem::whereNotNull('revendedor_id')->select('id');
        $semRevenda = function ($q) use ($revendaIds) {
            $q->where(fn ($w) => $w->where('modulo', '!=', 'passagem')->orWhereNotIn('origem_id', $revendaIds));
        };

        // ===== Repasse de dono: a venda de agente acertada vira entrada marcada no barco (embarcacao_id).
        // O caixa mostra TODO o balcao/guiche (qualquer barco) + os repasses dos barcos do PROPRIO dono.
        // Repasses de agentes de OUTROS donos (outro embarcacao_id) nao entram no caixa dele. =====
        $meusBarcos = auth()->user()->ehDonoTransporte()
            ? \App\Models\Embarcacao::where('dono_id', auth()->id())->pluck('id')->map(fn ($i) => (int) $i)->all()
            : [];
        $filtraRepasseDono = function ($q) use ($meusBarcos) {
            $q->where(function ($w) use ($meusBarcos) {
                $w->whereNull('embarcacao_id');
                if (! empty($meusBarcos)) $w->orWhereIn('embarcacao_id', $meusBarcos);
            });
        };
        // O ledger de débito do dono (devolução/abatido) não é dinheiro de caixa — fica fora das somas.
        $semDebitoLedger = function ($q) {
            $q->where(fn ($w) => $w->whereNull('categoria')->orWhereNotIn('categoria', ['Débito do dono (devolução)', 'Débito do dono — abatido']));
        };

        $base = Pagamento::where('estornado', false)
            ->where('meio', '!=', 'desconto')
            ->when($encIds === null && ! $dataViagem, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($encIds !== null, fn ($q) => $q->whereIn('encomenda_id', $encIds))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($modulo, fn ($q) => $q->where('modulo', $modulo))
            ->when($canal, fn ($q) => $this->filtrarCanal($q, $canal))
            ->when($transporteId, $filtraTransp)
            ->where($filtraRepasseDono)
            ->where($semDebitoLedger)
            ->when($dataViagem, $filtraDataViagem)
            ->where('tipo_mov', '!=', 'saida')
            ->when(! $ehRelatorio, fn ($q) => $q->where($semRevenda));

        $canalSql = Pagamento::canalSql();
        $porMeio = (clone $base)->selectRaw("$canalSql as canal_sql, SUM(valor) valor, COUNT(*) qtd")->groupBy('canal_sql')->get()->keyBy('canal_sql');
        $porOperador = (clone $base)->selectRaw("user_id, $canalSql as canal_sql, SUM(valor) valor")->groupBy('user_id', 'canal_sql')->get()
            ->groupBy('user_id')->map(fn ($rows) => [
                'total' => $rows->sum('valor'),
                'meios' => $rows->pluck('valor', 'canal_sql'),
            ]);
        $porModulo = (clone $base)->selectRaw('modulo, SUM(valor) valor, COUNT(*) qtd')->groupBy('modulo')->get()->keyBy('modulo');

        // ===== POR LANCHA (transporte): soma do caixa por embarcação, mapeado em PHP (evita join ambíguo). =====
        $pgsL = (clone $base)->selectRaw("modulo, origem_id, encomenda_id, $canalSql as canal_sql, valor")->get();
        $passIdsL = $pgsL->where('modulo', 'passagem')->pluck('origem_id')->filter()->unique()->all();
        $encIdsL = $pgsL->where('modulo', 'encomenda')->pluck('encomenda_id')->filter()->unique()->all();
        $embPass = $passIdsL ? \App\Models\Passagem::whereIn('id', $passIdsL)->pluck('embarcacao_id', 'id') : collect();
        $embEnc = $encIdsL ? \App\Models\Encomenda::whereIn('id', $encIdsL)->pluck('embarcacao_id', 'id') : collect();
        $porLancha = [];
        foreach ($pgsL as $pg) {
            $emb = $pg->modulo === 'passagem'
                ? ($embPass[$pg->origem_id] ?? null)
                : ($pg->modulo === 'encomenda' ? ($embEnc[$pg->encomenda_id] ?? null) : null);
            $k = (int) ($emb ?: 0);
            if (! isset($porLancha[$k])) $porLancha[$k] = ['total' => 0.0, 'meios' => []];
            $porLancha[$k]['total'] += (float) $pg->valor;
            $porLancha[$k]['meios'][$pg->canal_sql] = ($porLancha[$k]['meios'][$pg->canal_sql] ?? 0) + (float) $pg->valor;
        }
        uasort($porLancha, fn ($a, $b) => $b['total'] <=> $a['total']);

        $usuarios = User::where('empresa_id', Tenant::id())->orderBy('name')->get()->keyBy('id');

        $descontos = Pagamento::where('estornado', false)->where('meio', 'desconto')
            ->when($encIds === null && ! $dataViagem, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($encIds !== null, fn ($q) => $q->whereIn('encomenda_id', $encIds))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))->when($modulo, fn ($q) => $q->where('modulo', $modulo))->when($transporteId, $filtraTransp)->where($filtraRepasseDono)->when($dataViagem, $filtraDataViagem)->when(! $ehRelatorio, fn ($q) => $q->where($semRevenda))->sum('valor');

        $entregas = Evento::where('tipo', 'entrega')
            ->when($encIds === null && ! $dataViagem, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($encIds !== null, fn ($q) => $q->whereIn('encomenda_id', $encIds))
            ->when($transporteId, fn ($q) => $q->whereIn('encomenda_id', \App\Models\Encomenda::where('embarcacao_id', $transporteId)->select('id')))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))->count();
        $cadastros = Encomenda::when($encIds === null, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))->count();

        $saidas = Pagamento::where('estornado', false)->where('tipo_mov', 'saida')
            ->when($encIds === null && ! $dataViagem, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($encIds !== null, fn ($q) => $q->whereIn('origem_id', $encIds))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($modulo, fn ($q) => $q->where('modulo', $modulo))->when($transporteId, $filtraTransp)->where($filtraRepasseDono)->when($dataViagem, $filtraDataViagem)->sum('valor');

        // Cancelados do periodo (passagens canceladas) — mesmo recorte do caixa.
        $cancelados = \App\Models\Passagem::where('cancelada', true)
            ->when($encIds === null && ! $dataViagem, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
            ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(total),0) as valor')->first();
        $categorias = AvulsoCategoria::where('ativo', true)->orderBy('ordem')->orderBy('nome')->get();
        $podeAvulso = auth()->user()->podeModulo('avulso');

        $movimentos = (clone $base)->with(['user', 'encomenda'])->orderByDesc('id')->paginate(100)->withQueryString();
        // dados das passagens dos lançamentos desta página (cor por tipo + trecho)
        $passIdsMov = collect($movimentos->items())->where('modulo', 'passagem')->pluck('origem_id')->filter()->unique()->all();
        $passMov = $passIdsMov
            ? \App\Models\Passagem::whereIn('id', $passIdsMov)->with(['passageiros:id,passagem_id,papel,tipo,cancelado'])->get(['id', 'origem', 'destino', 'forma', 'cancelada'])->keyBy('id')
            : collect();

        // Manifesto (modelo prestacao). CAIXA: sem agente externo. RELATORIO: TODAS as passagens do filtro (com agente externo).
        $relWith = ['passageiros', 'operador', 'pagamentos', 'revendedor:id,name', 'clienteFaturado:id,nome,empresa'];
        if ($ehRelatorio) {
            $passCx = \App\Models\Passagem::where('cancelada', false)
                ->when($encIds === null && ! $dataViagem, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
                ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
                ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
                ->with($relWith)->orderByDesc('id')->get();
        } else {
            $passIdsCx = (clone $base)->where('modulo', 'passagem')->distinct()->pluck('origem_id')->filter()->all();
            $passCx = $passIdsCx
                ? \App\Models\Passagem::whereIn('id', $passIdsCx)->whereNull('revendedor_id')->with($relWith)->orderByDesc('id')->get()
                : collect();
        }
        $linhasCaixa = \App\Support\ManifestoCaixa::dados($passCx)['linhas'];

        // 3 categorias de venda (Balcão · Proprietário · Agente interior), cada uma por forma de pagamento.
        $novaCat = fn () => ['qtd' => 0, 'total' => 0.0, 'dinheiro' => 0.0, 'pix' => 0.0, 'debito' => 0.0, 'credito' => 0.0, 'areceber' => 0.0];
        $porCategoria = ['balcao' => $novaCat(), 'proprietario' => $novaCat(), 'agente' => $novaCat()];
        foreach ($passCx->where('cancelada', false) as $p) {
            $cat = $p->revendedor_id
                ? 'agente'
                : ($p->pagamentos->contains(fn ($pg) => (bool) ($pg->pago_proprietario ?? false)) ? 'proprietario' : 'balcao');
            $porCategoria[$cat]['qtd']++;
            $porCategoria[$cat]['total'] += (float) $p->total;
            $porCategoria[$cat]['areceber'] += (float) $p->devido; // parcelado / em aberto
            foreach ($p->pagamentos as $pg) {
                if ($pg->meio === 'desconto' || (float) $pg->valor <= 0) continue;
                $k = $pg->meio === 'pix' ? 'pix' : $pg->meio;
                if (isset($porCategoria[$cat][$k])) $porCategoria[$cat][$k] += (float) $pg->valor;
            }
        }

        // VENDAS (BRUTO): valor total vendido no filtro, independente de ter entrado no caixa (ex.: venda de agente ainda nao repassada)
        $filtroData = function ($q) use ($inicio, $fim, $userId, $transporteId, $dataViagem) {
            return $q->when(! $dataViagem, fn ($x) => $x->whereBetween('created_at', [$inicio, $fim]))
                ->when($userId, fn ($x) => $x->where('user_id', $userId))
                ->when($transporteId, fn ($x) => $x->where('embarcacao_id', $transporteId));
        };
        $aplicaVB = function ($q) use ($viagemId, $userId, $transporteId, $filtroData) {
            if ($viagemId) {
                return $q->where('viagem_id', $viagemId)
                    ->when($userId, fn ($x) => $x->where('user_id', $userId))
                    ->when($transporteId, fn ($x) => $x->where('embarcacao_id', $transporteId));
            }
            return $filtroData($q);
        };
        $vendasBruto = 0.0;
        if (! $modulo || $modulo === 'passagem') $vendasBruto += (float) $aplicaVB(\App\Models\Passagem::where('cancelada', false)->when(! $ehRelatorio, fn ($x) => $x->whereNull('revendedor_id'))->when($dataViagem, fn ($x) => $x->whereDate('data_viagem', $dataViagem)))->sum('total');
        if ((! $modulo || $modulo === 'encomenda') && ! $dataViagem) $vendasBruto += (float) $aplicaVB(Encomenda::query())->sum('total');
        if (! $modulo || $modulo === 'frete') $vendasBruto += (float) $filtroData(\App\Models\Frete::query())->sum('total');

        // Acerto com donos (balcao): o admin paga cada dono a parte do balcao do barco dele.
        // So aparece para quem confere o caixa (caixaTodos) e nunca o(s) proprio(s) barco(s) do viewer.
        $donosBalcao = collect();
        if (auth()->user()->caixaTodos()) {
            $meusIds = $meusBarcos ?: [];
            $donosBalcao = \App\Models\Embarcacao::whereNotNull('dono_id')->whereNotIn('id', $meusIds)
                ->when($transporteId, fn ($q) => $q->where('id', $transporteId))
                ->orderBy('nome')->get()
                ->map(function ($e) use ($dataViagem) {
                    $pct = (int) $e->comissao_empresa_pct;
                    $p = \App\Models\Passagem::where('embarcacao_id', $e->id)->whereNull('revendedor_id')->where('cancelada', false)->where('transferida', false)->where('acerto_dono', false)
                        ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))
                        ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(total),0) as bruto, COALESCE(SUM(devido),0) as devido, COALESCE(SUM(total * (100 - '.$pct.') / 100),0) as v')->first();
                    $ag = \App\Models\Passagem::where('embarcacao_id', $e->id)->whereNotNull('revendedor_id')->where('cancelada', false)->where('transferida', false)->where('acerto_dono', false)
                        ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))
                        ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(total),0) as bruto, COALESCE(SUM(acerto_empresa_valor),0) as repasse')->first();
                    $deb = round(
                        (float) \App\Models\Pagamento::where('embarcacao_id', $e->id)->where('estornado', false)->where('categoria', 'Débito do dono (devolução)')->sum('valor')
                        - (float) \App\Models\Pagamento::where('embarcacao_id', $e->id)->where('estornado', false)->where('categoria', 'Débito do dono — abatido')->sum('valor'), 2);
                    $deb = max(0.0, $deb);
                    $deb = round($deb + (float) $p->devido, 2); // em aberto é débito do proprietário
                    $comissao = round((float) $p->bruto - (float) $p->v, 2);
                    $passIdsDono = \App\Models\Passagem::where('embarcacao_id', $e->id)->whereNull('revendedor_id')->where('cancelada', false)->where('transferida', false)->where('acerto_dono', false)
                        ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))->pluck('id');
                    $cartao = round((float) \App\Models\Pagamento::where('modulo', 'passagem')->whereIn('origem_id', $passIdsDono)->where('estornado', false)->where('meio', 'credito')->sum('valor'), 2);
                    $credProp = round((float) \App\Models\Pagamento::where('modulo', 'passagem')->whereIn('origem_id', $passIdsDono)->where('estornado', false)->where('pago_proprietario', true)->sum('valor'), 2);
                    $despesas = round($comissao + $deb + $cartao + $credProp, 2);
                    return (object) ['emb' => $e, 'qtd' => (int) $p->qtd, 'bruto' => (float) $p->bruto, 'comissao' => $comissao, 'valor' => (float) $p->v, 'debito' => $deb, 'cartao' => $cartao, 'credProp' => $credProp, 'despesas' => $despesas, 'liquidoPrest' => round((float) $p->bruto - $despesas, 2), 'liquido' => round((float) $p->v - $deb, 2), 'dono' => optional($e->dono)->name ?? $e->dono_nome,
                        'qtdAg' => (int) $ag->qtd, 'brutoAg' => (float) $ag->bruto, 'repasseAg' => (float) $ag->repasse];
                })->filter(fn ($x) => $x->valor > 0 || $x->debito > 0 || $x->qtdAg > 0)->values();
        }

        // Prestação GERAL do filtro: soma as passagens + avulsos do(s) transporte(s) do filtro.
        $prestacaoTransporte = null;
        $comissaoAgencia = 0.0; $ownerShareG = 0.0;
        // TODAS as passagens do filtro: comissão da agência no CAIXA (não zera quando o dono é acertado).
        $pqAll = \App\Models\Passagem::where('cancelada', false)->where('transferida', false)
            ->when(! $ehRelatorio && $request->input('foco') !== 'prestacoes', fn ($q) => $q->whereNull('revendedor_id')) // só o CAIXA exclui agente externo; Relatório e Prestação mostram
            ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem), fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]));
        $passAll = (clone $pqAll)->get(['id', 'embarcacao_id', 'total']);
        if ($passAll->count() > 0) {
            $embIdsA = $passAll->pluck('embarcacao_id')->filter()->unique()->values();
            $pctsA = \App\Models\Embarcacao::whereIn('id', $embIdsA)->pluck('comissao_empresa_pct', 'id');
            $comissaoAgencia = round((float) $passAll->sum(fn ($p) => (float) $p->total * (int) ($pctsA[$p->embarcacao_id] ?? 0) / 100), 2);
            $ownerShareG = round((float) $passAll->sum(fn ($p) => (float) $p->total * (100 - (int) ($pctsA[$p->embarcacao_id] ?? 0)) / 100), 2);
        }
        // PENDENTE (acerto_dono=false): é o que a PRESTAÇÃO mostra — zera depois do fechamento.
        $pqG = (clone $pqAll)->where('acerto_dono', false);
        $idsComDono = null;
        if ($request->input('foco') === 'prestacoes') {
            $idsComDono = \App\Models\Embarcacao::whereNotNull('dono_id')->whereNotIn('id', $meusBarcos ?: [])->pluck('id');
            $pqG->whereIn('embarcacao_id', $idsComDono);
        }
        $passG = (clone $pqG)->get(['id', 'embarcacao_id', 'total']);
        // Entradas/saídas AVULSAS amarradas ao transporte: entram na PRESTAÇÃO (não no caixa da agência).
        // Só avulso LANÇADO pelo operador (momento=cadastro). Pagamento ao dono, repasse de agente e ledger de débito
        // também são modulo=avulso com embarcacao_id, mas são movimentos do sistema (momento=baixa) e não entram aqui.
        $avBase = Pagamento::where('modulo', 'avulso')->where('estornado', false)->whereNotNull('embarcacao_id')
            ->where('momento', 'cadastro')->whereNull('prestacao_id')
            ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
            ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem), fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]));
        if ($idsComDono !== null) $avBase->whereIn('embarcacao_id', $idsComDono);
        $avEntrada = round((float) (clone $avBase)->where('tipo_mov', 'entrada')->sum('valor'), 2);
        $avSaida = round((float) (clone $avBase)->where('tipo_mov', 'saida')->sum('valor'), 2);
        // Lista de avulsos da página: Prestação = os do transporte/viagem; Caixa = os da agência; Relatório não tem.
        $avulsosLista = collect();
        if ($request->input('foco') === 'prestacoes') {
            $avulsosLista = (clone $avBase)->with(['user:id,name', 'embarcacao:id,nome'])->orderByDesc('id')->limit(300)->get();
        } elseif (! $ehRelatorio) {
            $avulsosLista = Pagamento::where('modulo', 'avulso')->where('estornado', false)->where('momento', 'cadastro')->whereNull('embarcacao_id')
                ->whereBetween('created_at', [$inicio, $fim])
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->with(['user:id,name'])->orderByDesc('id')->limit(300)->get();
        }
        if ($passG->count() > 0 || $avEntrada > 0 || $avSaida > 0) {
            $brutoG = 0.0; $comissaoG = 0.0; $debitoG = 0.0; $cartaoG = 0.0; $credPropG = 0.0;
            $nomeG = 'Todos os transportes'; $pctG = null;
            if ($passG->count() > 0) {
                $embIdsG = $passG->pluck('embarcacao_id')->filter()->unique()->values();
                $pcts = \App\Models\Embarcacao::whereIn('id', $embIdsG)->pluck('comissao_empresa_pct', 'id');
                $brutoG = round((float) $passG->sum('total'), 2);
                $comissaoG = round((float) $passG->sum(fn ($p) => (float) $p->total * (int) ($pcts[$p->embarcacao_id] ?? 0) / 100), 2);
                $idsG = $passG->pluck('id');
                $cartaoG = round((float) \App\Models\Pagamento::where('modulo', 'passagem')->whereIn('origem_id', $idsG)->where('estornado', false)->where('meio', 'credito')->sum('valor'), 2);
                $credPropG = round((float) \App\Models\Pagamento::where('modulo', 'passagem')->whereIn('origem_id', $idsG)->where('estornado', false)->where('pago_proprietario', true)->sum('valor'), 2);
                $debitoG = round(
                    (float) \App\Models\Pagamento::whereIn('embarcacao_id', $embIdsG)->where('estornado', false)->where('categoria', 'Débito do dono (devolução)')->sum('valor')
                    - (float) \App\Models\Pagamento::whereIn('embarcacao_id', $embIdsG)->where('estornado', false)->where('categoria', 'Débito do dono — abatido')->sum('valor'), 2);
                $debitoG = max(0.0, $debitoG);
                $debitoG = round($debitoG + (float) (clone $pqG)->sum('devido'), 2); // em aberto é débito do proprietário
                $nomeG = $embIdsG->count() === 1 ? (optional(\App\Models\Embarcacao::find($embIdsG->first()))->nome ?? 'Transporte') : 'Todos os transportes';
                $pctG = $embIdsG->count() === 1 ? (int) ($pcts[$embIdsG->first()] ?? 0) : null;
            } elseif ($transporteId) {
                $embX = \App\Models\Embarcacao::find($transporteId);
                $nomeG = optional($embX)->nome ?? 'Transporte';
                $pctG = (int) optional($embX)->comissao_empresa_pct;
            }
            $despesasG = round($comissaoG + $debitoG + $cartaoG + $credPropG, 2);
            $prestacaoTransporte = (object) [
                'nome' => $nomeG, 'pct' => $pctG, 'qtd' => $passG->count(),
                'bruto' => $brutoG, 'comissao' => $comissaoG, 'debito' => $debitoG,
                'cartao' => $cartaoG, 'credProp' => $credPropG,
                'avEntrada' => $avEntrada, 'avSaida' => $avSaida,
                'despesas' => $despesasG,
                'liquido' => round($brutoG - $despesasG + $avEntrada - $avSaida, 2),
            ];
        }

        // Líquido da AGÊNCIA = entradas - parte dos donos (passagens) - saídas próprias. Comissão é o que fica pra agência.
        $entradasVal = (float) $porMeio->sum('valor');
        $liquidoAgencia = round($entradasVal - $ownerShareG - (float) $saidas, 2);

        // Desfazer acerto direto no relatório (por transporte + viagem). Serve p/ acertos antigos.
        $acertoDesfazivel = 0; $podeDesfazerTransporte = false;
        if ($transporteId && auth()->user()->caixaTodos()) {
            $acertoDesfazivel = \App\Models\Passagem::where('embarcacao_id', $transporteId)->where('cancelada', false)->where('acerto_dono', true)
                ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
                ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))->count();
            $podeDesfazerTransporte = $acertoDesfazivel > 0 && ((bool) Tenant::empresa()->config('desfazer_acerto') || auth()->user()->isAdmin() || auth()->user()->isSuper());
        }

        // Acertos recentes (balcão + agente) que podem ser DESFEITOS — só quando a empresa libera.
        $permiteDesfazer = (bool) Tenant::empresa()->config('desfazer_acerto');
        $acertosRecentes = collect();
        if ($permiteDesfazer && auth()->user()->caixaTodos()) {
            $acertosRecentes = Pagamento::whereNotNull('acerto_lote')->where('estornado', false)
                ->whereNull('prestacao_id') // acertos de prestação têm card próprio (histórico)
                ->whereIn('categoria', ['Pagamento ao dono (balcão)', 'Repasse do agente'])
                ->when($inicio, fn ($x) => $x->where('created_at', '>=', $inicio))
                ->when($fim, fn ($x) => $x->where('created_at', '<=', $fim))
                ->with(['embarcacao', 'user'])->orderByDesc('id')->limit(500)->get()
                ->groupBy('acerto_lote')->map(function ($g) {
                    $f = $g->first();
                    $agente = $f->categoria === 'Repasse do agente';
                    return (object) [
                        'lote' => $f->acerto_lote,
                        'emb' => $f->embarcacao,
                        'tipo' => $agente ? 'agente' : 'balcão',
                        'valor' => (float) $g->where('categoria', '!=', 'Débito do dono — abatido')->sum('valor'),
                        'quando' => $f->created_at,
                        'quem' => optional($f->user)->name,
                    ];
                })->values();
        }

        // Histórico de prestações fechadas (card separado das vendas atuais) — mais recentes primeiro.
        $prestacoesFechadas = collect();
        if (auth()->user()->caixaTodos()) {
            $prestacoesFechadas = \App\Models\Prestacao::ativas()
                ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
                ->with('embarcacao')->orderByDesc('fechada_em')->orderByDesc('id')->limit(100)->get();
            // Data(s) de viagem de cada prestação: a gravada no fechamento ou, se fechou "todas pendentes", as datas das passagens.
            $datasPorPrest = \App\Models\Passagem::whereIn('prestacao_id', $prestacoesFechadas->pluck('id'))->whereNotNull('data_viagem')
                ->select('prestacao_id', 'data_viagem')->distinct()->orderBy('data_viagem')->get()->groupBy('prestacao_id');
            foreach ($prestacoesFechadas as $pr) {
                $ds = $pr->data_viagem
                    ? collect([$pr->data_viagem])
                    : ($datasPorPrest[$pr->id] ?? collect())->pluck('data_viagem');
                $pr->viagens_txt = $ds->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m/Y'))->unique()->implode(', ') ?: '—';
            }
        }

        if ($request->boolean('pdf')) {
            $movs = (clone $base)->with(['user', 'encomenda'])->orderByDesc('id')->limit(2000)->get();
            $canais = Pagamento::CANAIS;
            $passIdsP = $movs->where('modulo', 'passagem')->pluck('origem_id')->filter()->unique()->all();
            $formasP = $passIdsP ? \App\Models\Passagem::whereIn('id', $passIdsP)->pluck('forma', 'id') : collect();
            $linhas = $movs->map(fn ($p) => (object) [
                'data' => $p->created_at,
                'descricao' => trim(($p->user?->name ? $p->user->name.' · ' : '').($p->categoria ?: ucfirst((string) $p->modulo))),
                'ref' => $p->encomenda?->numeroFormatado() ? '#'.$p->encomenda->numeroFormatado() : ($canais[$p->canal] ?? ''),
                'valor' => (float) $p->valor,
                'municipio' => $p->modulo === 'passagem' && (($formasP[$p->origem_id] ?? null) === 'faturado'),
            ]);
            $ent = (float) $porMeio->sum('valor');
            $tot = [
                ['label' => 'Entradas', 'valor' => $ent, 'forte' => true],
                ['label' => 'Saídas', 'valor' => (float) $saidas],
                ['label' => 'Líquido', 'valor' => $ent - (float) $saidas, 'forte' => true],
                ['label' => 'Vendas (bruto)', 'valor' => (float) $vendasBruto],
                ['label' => 'Cancelados ('.(int) ($cancelados->qtd ?? 0).')', 'valor' => (float) ($cancelados->valor ?? 0)],
            ];
            return view('relatorio.prestacao', [
                'empresa' => Tenant::empresa(),
                'titulo' => 'Prestação de contas — Caixa',
                'subtitulo' => $verTodos ? 'Caixa geral da empresa' : ('Caixa · '.auth()->user()->name),
                'periodo' => 'Período: '.\Carbon\Carbon::parse($inicio)->format('d/m/Y').' a '.\Carbon\Carbon::parse($fim)->format('d/m/Y'),
                'linhas' => $linhas,
                'totais' => $tot,
                'assinatura' => auth()->user()->name,
                'voltar' => route('caixa.index'),
                'canceladosLista' => \App\Models\Passagem::where('cancelada', true)
                    ->when($encIds === null && ! $dataViagem, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
                    ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
                    ->when($userId, fn ($q) => $q->where('user_id', $userId))
                    ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
                    ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))
                    ->orderByDesc('id')->limit(300)->get()
                    ->map(fn ($p) => (object) ['data' => $p->created_at, 'numero' => $p->numeroFormatado(), 'trecho' => $p->origem.' → '.$p->destino, 'comprador' => $p->comprador_nome, 'motivo' => $p->motivo_cancelamento, 'valor' => (float) $p->total]),
            ]);
        }

        return view('caixa.index', compact('porMeio', 'porModulo', 'porOperador', 'porLancha', 'usuarios', 'descontos', 'entregas', 'cadastros', 'movimentos', 'passMov', 'linhasCaixa', 'inicio', 'fim', 'periodo', 'userId', 'viagens', 'viagemId', 'verTodos', 'modulo', 'saidas', 'categorias', 'podeAvulso', 'canal', 'transportes', 'cancelados', 'transporteId', 'podeTransporte', 'datasViagem', 'dataViagem', 'donosBalcao', 'acertosRecentes', 'permiteDesfazer', 'prestacaoTransporte', 'prestacoesFechadas', 'comissaoAgencia', 'liquidoAgencia', 'acertoDesfazivel', 'podeDesfazerTransporte', 'avulsosLista', 'porCategoria'))
            ->with(['meios' => Pagamento::CANAIS, 'entradas' => $porMeio->sum('valor'), 'total' => $porMeio->sum('valor') - $saidas, 'vendasBruto' => $vendasBruto, 'ehRelatorio' => $ehRelatorio]);
    }

    /** Aplica o filtro por canal (PIX dividido em manual/automático). */
    protected function filtrarCanal($q, string $canal)
    {
        return match ($canal) {
            'pix_manual' => $q->where('meio', 'pix')->where(fn ($w) => $w->where('pix_auto', false)->orWhereNull('pix_auto')),
            'pix_auto' => $q->where('meio', 'pix')->where('pix_auto', true),
            'dinheiro', 'debito', 'credito' => $q->where('meio', $canal),
            default => $q,
        };
    }

    /** Registra uma entrada ou saída avulsa no caixa. */
    public function avulso(Request $request)
    {
        abort_unless($request->user()->podeModulo('avulso'), 403, 'Sem permissão para lançar avulso.');
        $data = $request->validate([
            'tipo_mov' => 'required|in:entrada,saida',
            'categoria' => 'nullable|string|max:60',
            'valor' => 'required|numeric|min:0.01',
            'meio' => 'required|in:dinheiro,pix,debito,credito',
            'descricao' => 'nullable|string|max:200',
            'embarcacao_id' => 'nullable|integer',
            'data_viagem' => 'nullable|date',
        ]);
        // Avulso amarrado a um transporte: pertence à PRESTAÇÃO daquele transporte/viagem, NÃO ao caixa da agência.
        $embId = ! empty($data['embarcacao_id'])
            ? (int) optional(\App\Models\Embarcacao::where('id', $data['embarcacao_id'])->where('empresa_id', Tenant::id())->first())->id
            : null;
        $dataViagemAv = $embId ? ($data['data_viagem'] ?? null) : null;
        $cat = ! empty($data['categoria']) ? AvulsoCategoria::where('nome', $data['categoria'])->first() : null;
        Pagamento::create([
            'empresa_id' => Tenant::id(),
            'modulo' => 'avulso',
            'origem_id' => null,
            'encomenda_id' => null,
            'meio' => $data['meio'],
            'valor' => $data['valor'],
            'user_id' => auth()->id(),
            'momento' => 'cadastro',
            'tipo_mov' => $data['tipo_mov'],
            'categoria' => $data['categoria'] ?? null,
            'descricao' => $data['descricao'] ?? null,
            'embarcacao_id' => $embId,
            'data_viagem' => $dataViagemAv,
            'acerto_conta' => $cat ? (bool) $cat->conta_acerto : true,
            'acerto_pct' => $cat ? (int) $cat->comissao_pct : 0,
        ]);
        return back()->with('ok', ($data['tipo_mov'] === 'entrada' ? 'Entrada' : 'Saída') . ' avulsa registrada.');
    }

    /** Resolve o período: hoje | semana | mes | personalizado */
    public static function periodo(Request $request): array
    {
        $p = $request->input('periodo', 'hoje');
        return match ($p) {
            'semana' => [now()->startOfWeek(), now()->endOfWeek(), 'semana'],
            'mes' => [now()->startOfMonth(), now()->endOfMonth(), 'mes'],
            'personalizado' => [
                \Carbon\Carbon::parse($request->input('inicio', now()->toDateString()).' '.$request->input('hora_inicio', '00:00')),
                \Carbon\Carbon::parse($request->input('fim', now()->toDateString()).' '.$request->input('hora_fim', '23:59')),
                'personalizado',
            ],
            default => [now()->startOfDay(), now()->endOfDay(), 'hoje'],
        };
    }
}
