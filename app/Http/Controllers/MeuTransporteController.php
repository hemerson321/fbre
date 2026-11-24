<?php

namespace App\Http\Controllers;

use App\Models\Embarcacao;
use App\Models\Pagamento;
use App\Models\Passagem;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;

/**
 * Area do DONO do transporte. O dono (usuario ligado em embarcacoes.dono_id)
 * entra aqui e ve/da baixa nas vendas da lancha dele:
 *  - Balcao: a empresa deve ao dono (total menos a comissao da empresa) -> baixa gera SAIDA no caixa da empresa.
 *  - Agentes: o agente paga DIRETO o dono (repasse) -> baixa so marca, nao mexe no caixa da empresa.
 */
class MeuTransporteController extends Controller
{
    /** Lanchas que este usuario pode ver aqui. */
    protected function transportesDoUsuario(): \Illuminate\Support\Collection
    {
        $u = auth()->user();
        if ($u->ehDonoTransporte()) return Embarcacao::where('dono_id', $u->id)->orderBy('nome')->get();
        if ($u->isAdmin() || $u->isSuper()) return Embarcacao::whereNotNull('dono_id')->orderBy('nome')->get();
        return collect();
    }

    public function index(Request $request)
    {
        $meus = $this->transportesDoUsuario();
        abort_if($meus->isEmpty(), 403);

        $transporteId = (int) ($request->input('transporte_id') ?: $meus->first()->id);
        $emb = $meus->firstWhere('id', $transporteId) ?: $meus->first();
        $transporteId = (int) $emb->id;
        $pct = (int) $emb->comissao_empresa_pct;

        $dataViagem = $request->input('data_viagem') ?: null;
        $viagemId = $request->input('viagem_id') ?: null;

        $temViagens = (bool) Tenant::empresa()?->temRecurso('viagens');
        $viagens = $temViagens
            ? \App\Models\Viagem::with('rota')->where('embarcacao_id', $emb->id)
                ->where(fn ($q) => $q->whereBetween('data_partida', [now()->subMonthsNoOverflow(3)->startOfDay(), now()->addMonthsNoOverflow(3)->endOfDay()])->orWhereNull('data_partida'))
                ->orderByDesc('data_partida')->limit(300)->get()
            : collect();
        $datasViagem = Passagem::where('embarcacao_id', $emb->id)->where('cancelada', false)->whereNotNull('data_viagem')
            ->whereBetween('data_viagem', [now()->subMonthsNoOverflow(3)->toDateString(), now()->addMonthsNoOverflow(3)->toDateString()])
            ->select('data_viagem')->distinct()->orderByDesc('data_viagem')->pluck('data_viagem');

        // ===== A RECEBER (pendente) — independe do periodo; so data de viagem / viagem filtram =====
        $donoExpr = 'CASE WHEN revendedor_id IS NOT NULL THEN COALESCE(acerto_empresa_valor,0) ELSE total * (100 - '.$pct.') / 100 END';
        $pend = fn () => Passagem::where('embarcacao_id', $emb->id)->where('cancelada', false)->where('transferida', false)->where('acerto_dono', false)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem));

        $balcaoQtd   = (int) ($pend())->whereNull('revendedor_id')->count();
        $balcaoBruto = (float) ($pend())->whereNull('revendedor_id')->sum('total');
        $balcaoDono  = (float) ($pend())->whereNull('revendedor_id')->selectRaw('COALESCE(SUM(total * (100 - '.$pct.') / 100),0) as v')->value('v');

        $agRows = ($pend())->whereNotNull('revendedor_id')
            ->selectRaw('revendedor_id, COUNT(*) as qtd, COALESCE(SUM(total),0) as bruto, COALESCE(SUM(acerto_empresa_valor),0) as dono')
            ->groupBy('revendedor_id')->get();
        $agNomes = User::whereIn('id', $agRows->pluck('revendedor_id'))->pluck('name', 'id');
        $agentesList = $agRows->map(fn ($r) => (object) [
            'id'    => (int) $r->revendedor_id,
            'nome'  => $agNomes[$r->revendedor_id] ?? ('Agente #'.$r->revendedor_id),
            'qtd'   => (int) $r->qtd,
            'bruto' => (float) $r->bruto,
            'dono'  => (float) $r->dono,
        ])->sortByDesc('dono')->values();
        $agentesDono = round((float) $agentesList->sum('dono'), 2);
        $agentesQtd  = (int) $agentesList->sum('qtd');

        // Saldo devedor do dono (devoluções de passagens já acertadas) — abatido no próximo acerto.
        $saldoDevedor = round(
            (float) Pagamento::where('embarcacao_id', $emb->id)->where('estornado', false)->where('categoria', 'Débito do dono (devolução)')->sum('valor')
            - (float) Pagamento::where('embarcacao_id', $emb->id)->where('estornado', false)->where('categoria', 'Débito do dono — abatido')->sum('valor'), 2);
        if ($saldoDevedor < 0) $saldoDevedor = 0.0;

        $aReceber = round($balcaoDono + $agentesDono - $saldoDevedor, 2);

        // ===== JA RECEBIDO (acerto_dono = true) — todas as datas / filtro de viagem =====
        $rec = Passagem::where('embarcacao_id', $emb->id)->where('cancelada', false)->where('acerto_dono', true)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem));
        $recebido = (float) (clone $rec)->selectRaw('COALESCE(SUM('.$donoExpr.'),0) as v')->value('v');
        $recebidoQtd = (int) (clone $rec)->count();

        // ===== RESUMO DE VENDAS — todas as datas / filtro de viagem =====
        $vend = Passagem::where('embarcacao_id', $emb->id)->where('cancelada', false)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem));
        $vendasBruto = (float) (clone $vend)->sum('total');
        $vendasQtd   = (int) (clone $vend)->count();
        $vendasDono  = (float) (clone $vend)->selectRaw('COALESCE(SUM('.$donoExpr.'),0) as v')->value('v');
        $vendasComissao = round($vendasBruto - $vendasDono, 2);

        $aba = $request->input('aba') === 'vendas' ? 'vendas' : 'acertos';
        $vendas = Passagem::where('embarcacao_id', $emb->id)->where('cancelada', false)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))
            ->with(['operador', 'revendedor'])->orderByDesc('id')->limit(300)->get();

        // Prestações de contas já fechadas desta lancha (inclui as feitas antes de existir o login do proprietário).
        $prestacoesDono = \App\Models\Prestacao::ativas()->where('embarcacao_id', $emb->id)
            ->when($dataViagem, fn ($q) => $q->where(fn ($w) => $w->whereDate('data_viagem', $dataViagem)
                ->orWhereIn('id', Passagem::whereDate('data_viagem', $dataViagem)->whereNotNull('prestacao_id')->select('prestacao_id'))))
            ->orderByDesc('fechada_em')->orderByDesc('id')->limit(100)->get();
        $dpp = Passagem::whereIn('prestacao_id', $prestacoesDono->pluck('id'))->whereNotNull('data_viagem')
            ->select('prestacao_id', 'data_viagem')->distinct()->orderBy('data_viagem')->get()->groupBy('prestacao_id');
        foreach ($prestacoesDono as $pr) {
            $ds = $pr->data_viagem ? collect([$pr->data_viagem]) : ($dpp[$pr->id] ?? collect())->pluck('data_viagem');
            $pr->viagens_txt = $ds->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m/Y'))->unique()->implode(', ') ?: '—';
        }

        return view('meu-transporte.index', compact(
            'meus', 'emb', 'transporteId', 'pct', 'dataViagem', 'viagemId',
            'temViagens', 'viagens', 'datasViagem',
            'balcaoQtd', 'balcaoBruto', 'balcaoDono', 'agentesList', 'agentesDono', 'agentesQtd',
            'aba', 'vendas', 'aReceber', 'recebido', 'recebidoQtd', 'saldoDevedor',
            'vendasBruto', 'vendasQtd', 'vendasDono', 'vendasComissao', 'prestacoesDono'
        ));
    }

    /** Dono recebe do BALCAO: empresa paga o dono -> marca passagens + SAIDA no caixa da empresa. */
    public function baixaBalcao(Request $request, Embarcacao $embarcacao)
    {
        $this->autorizar($embarcacao);
        $pct = (int) $embarcacao->comissao_empresa_pct;
        $dataViagem = $request->input('data_viagem') ?: null;
        $viagemId = $request->input('viagem_id') ?: null;
        $q = Passagem::where('embarcacao_id', $embarcacao->id)->where('cancelada', false)->where('transferida', false)->where('acerto_dono', false)->whereNull('revendedor_id')
            ->when($viagemId, fn ($x) => $x->where('viagem_id', $viagemId))
            ->when($dataViagem, fn ($x) => $x->whereDate('data_viagem', $dataViagem));
        $valor = (float) (clone $q)->selectRaw('COALESCE(SUM(total * (100 - '.$pct.') / 100),0) as v')->value('v');
        $lote = (string) \Illuminate\Support\Str::uuid();
        $n = $q->update(['acerto_dono' => true, 'acerto_dono_em' => now(), 'acerto_dono_por' => auth()->id(), 'acerto_lote' => $lote]);

        // Abate o saldo devedor do dono (devoluções de passagens já acertadas).
        $deb = round(
            (float) Pagamento::where('embarcacao_id', $embarcacao->id)->where('estornado', false)->where('categoria', 'Débito do dono (devolução)')->sum('valor')
            - (float) Pagamento::where('embarcacao_id', $embarcacao->id)->where('estornado', false)->where('categoria', 'Débito do dono — abatido')->sum('valor'), 2);
        $deb = max(0.0, $deb);
        $abate = round(min($valor, $deb), 2);
        $pagar = round($valor - $abate, 2);

        if ($pagar > 0) {
            Pagamento::create([
                'empresa_id' => Tenant::id(), 'modulo' => 'avulso', 'origem_id' => null, 'encomenda_id' => null,
                'meio' => 'dinheiro', 'valor' => $pagar, 'user_id' => auth()->id(),
                'momento' => 'baixa', 'tipo_mov' => 'saida',
                'categoria' => 'Pagamento ao dono (balcão)', 'descricao' => 'Repasse do balcão ao dono - '.$embarcacao->nome.($abate > 0 ? ' (já abatido R$ '.number_format($abate, 2, ',', '.').' de devolução)' : ''),
                'conferido' => true, 'conferido_em' => now(), 'conferido_por' => auth()->id(), 'acerto_conta' => false, 'acerto_lote' => $lote,
            ]);
        }
        if ($abate > 0) {
            Pagamento::create([
                'empresa_id' => Tenant::id(), 'modulo' => 'avulso', 'origem_id' => null, 'encomenda_id' => null,
                'embarcacao_id' => $embarcacao->id, 'meio' => 'dinheiro', 'valor' => $abate, 'user_id' => auth()->id(),
                'momento' => 'baixa', 'tipo_mov' => 'entrada', 'categoria' => 'Débito do dono — abatido',
                'descricao' => 'Débito de devolução abatido no acerto - '.$embarcacao->nome, 'acerto_lote' => $lote,
            ]);
        }

        $msg = 'Baixa do balcão: '.$n.' passagem(ns) · R$ '.number_format($pagar, 2, ',', '.').' pago ao dono.';
        if ($abate > 0) $msg .= ' Abatido R$ '.number_format($abate, 2, ',', '.').' de devolução.';
        return back()->with('ok', $msg);
    }

    /** Dono recebe do AGENTE: o agente pagou direto -> so marca as passagens (nao mexe no caixa da empresa). */
    public function baixaAgente(Request $request, Embarcacao $embarcacao, User $agente)
    {
        $this->autorizar($embarcacao);
        $dataViagem = $request->input('data_viagem') ?: null;
        $viagemId = $request->input('viagem_id') ?: null;
        $q = Passagem::where('embarcacao_id', $embarcacao->id)->where('revendedor_id', $agente->id)->where('cancelada', false)->where('transferida', false)->where('acerto_dono', false)
            ->when($viagemId, fn ($x) => $x->where('viagem_id', $viagemId))
            ->when($dataViagem, fn ($x) => $x->whereDate('data_viagem', $dataViagem));
        $valor = (float) (clone $q)->sum('acerto_empresa_valor');
        $lote = (string) \Illuminate\Support\Str::uuid();
        $n = $q->update([
            'acerto_dono' => true, 'acerto_dono_em' => now(), 'acerto_dono_por' => auth()->id(),
            'acerto_empresa' => true, 'acerto_empresa_em' => now(), 'acerto_empresa_por' => auth()->id(),
            'acerto_lote' => $lote,
        ]);
        // Entrada no caixa DO TRANSPORTE (o dono recebeu o repasse do agente). Marcada no barco
        // para aparecer no "Caixa do meu transporte"; nao entra no caixa geral da empresa.
        if ($valor > 0 && $n > 0) {
            Pagamento::create([
                'empresa_id' => Tenant::id(), 'modulo' => 'avulso', 'origem_id' => null, 'encomenda_id' => null,
                'embarcacao_id' => $embarcacao->id,
                'meio' => 'dinheiro', 'valor' => $valor, 'user_id' => auth()->id(),
                'momento' => 'baixa', 'tipo_mov' => 'entrada',
                'categoria' => 'Repasse do agente', 'descricao' => 'Repasse do agente '.$agente->name.' - '.$embarcacao->nome,
                'conferido' => true, 'conferido_em' => now(), 'conferido_por' => auth()->id(), 'acerto_conta' => false, 'acerto_lote' => $lote,
            ]);
        }
        return back()->with('ok', 'Baixa do agente '.$agente->name.': '.$n.' passagem(ns) · R$ '.number_format($valor, 2, ',', '.').' recebido direto do agente.');
    }

    /** Desfaz um acerto (balcão ou agente) pelo lote: volta as passagens pra "em aberto" e estorna o pagamento. Controlado pela config da empresa. */
    public function desfazerAcerto(Request $request)
    {
        $empresa = Tenant::empresa();
        abort_unless((bool) $empresa->config('desfazer_acerto'), 403, 'Desfazer acerto está desativado nesta empresa.');

        $lote = trim((string) $request->input('lote'));
        abort_if($lote === '', 422, 'Lote do acerto não informado.');

        $passagens = Passagem::where('acerto_lote', $lote)->get();
        if ($passagens->isEmpty()) {
            return back()->with('erro', 'Esse acerto não foi encontrado (talvez já tenha sido desfeito).');
        }

        // Permissão: quem confere o caixa, ou o proprietário da lancha do acerto.
        $embId = (int) $passagens->first()->embarcacao_id;
        if (! auth()->user()->caixaTodos()) {
            $this->autorizar(Embarcacao::findOrFail($embId));
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($lote) {
            Passagem::where('acerto_lote', $lote)->update([
                'acerto_dono' => false, 'acerto_dono_em' => null, 'acerto_dono_por' => null,
                'acerto_empresa' => false, 'acerto_empresa_em' => null, 'acerto_empresa_por' => null,
                'acerto_lote' => null,
            ]);
            Pagamento::where('acerto_lote', $lote)->where('estornado', false)->update(['estornado' => true]);
        });

        return back()->with('ok', 'Acerto desfeito: '.$passagens->count().' passagem(ns) voltaram para "em aberto" e o pagamento foi estornado.');
    }

    /** Prestacao de contas (tela ou PDF) da lancha do dono. Reaproveita o layout de acertos/dono. */
    public function relatorio(Request $request, Embarcacao $embarcacao)
    {
        $this->autorizar($embarcacao);
        $viagemId = $request->input('viagem_id') ?: null;
        $dataViagem = $request->input('data_viagem') ?: null;
        $pct = (int) $embarcacao->comissao_empresa_pct;
        $base = Passagem::where('embarcacao_id', $embarcacao->id)->where('cancelada', false)->where('transferida', false)->where('acerto_dono', false)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem));
        $passagens = (clone $base)->with('operador')->orderByDesc('id')->limit(1000)->get();
        foreach ($passagens as $p) {
            $p->dono_valor = $p->revendedor_id ? (float) $p->acerto_empresa_valor : round((float) $p->total * (100 - $pct) / 100, 2);
            $p->canal = $p->revendedor_id ? 'agente' : 'balcao';
        }
        $balcao  = $passagens->where('canal', 'balcao')->values();
        $agentes = $passagens->where('canal', 'agente')->values();
        $brutoBalcao  = round((float) $balcao->sum('total'), 2);
        $donoBalcao   = round((float) $balcao->sum('dono_valor'), 2);
        $brutoAgentes = round((float) $agentes->sum('total'), 2);
        $donoAgentes  = round((float) $agentes->sum('dono_valor'), 2);
        $bruto = round($brutoBalcao + $brutoAgentes, 2);
        $dono = round($donoBalcao + $donoAgentes, 2);
        $comissao = round($bruto - $dono, 2);

        // --- Prestação de contas (modelo do proprietário) ---
        $passIds = $passagens->pluck('id')->all();
        $pgBase = Pagamento::where('modulo', 'passagem')->whereIn('origem_id', $passIds)->where('estornado', false);
        $cartaoCredito = round((float) (clone $pgBase)->where('meio', 'credito')->where(fn ($w) => $w->whereNull('pago_proprietario')->orWhere('pago_proprietario', false))->sum('valor'), 2); // só maquineta da empresa
        $creditoProp   = round((float) (clone $pgBase)->where('pago_proprietario', true)->sum('valor'), 2);
        // Débito do proprietário (devoluções já acertadas, ainda não abatidas)
        $debitoProp = round(
            (float) Pagamento::where('embarcacao_id', $embarcacao->id)->where('estornado', false)->where('categoria', 'Débito do dono (devolução)')->sum('valor')
            - (float) Pagamento::where('embarcacao_id', $embarcacao->id)->where('estornado', false)->where('categoria', 'Débito do dono — abatido')->sum('valor'), 2);
        $debitoProp = max(0.0, $debitoProp);
        $debitoProp = round($debitoProp + (float) $passagens->sum('devido'), 2); // em aberto (não coletado) é débito do proprietário
        // Despesas = comissão + débito + cartão + crédito do proprietário (entradas já adiantadas)
        $despesas = round($comissao + $debitoProp + $cartaoCredito + $creditoProp, 2);
        $liquido = round($bruto - $despesas, 2);

        $dados = compact(
            'embarcacao', 'passagens', 'balcao', 'agentes',
            'brutoBalcao', 'donoBalcao', 'brutoAgentes', 'donoAgentes',
            'bruto', 'dono', 'comissao', 'pct', 'viagemId', 'dataViagem',
            'cartaoCredito', 'creditoProp', 'debitoProp', 'despesas', 'liquido'
        );
        if ($request->boolean('pdf')) {
            return view('acertos.dono-pdf', $dados + ['empresa' => Tenant::empresa()]);
        }
        return view('acertos.dono', $dados);
    }

    protected function autorizar(Embarcacao $embarcacao): void
    {
        $u = auth()->user();
        abort_unless($embarcacao->empresa_id === Tenant::id(), 404);
        abort_unless(($embarcacao->dono_id && (int) $embarcacao->dono_id === (int) $u->id) || $u->isAdmin() || $u->isSuper(), 403);
    }
}
