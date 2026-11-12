<?php

namespace App\Http\Controllers;

use App\Models\Encomenda;
use App\Models\Evento;
use App\Models\Pagamento;
use App\Models\Passagem;
use Illuminate\Http\Request;

/** Relatório do próprio operador (só vê o que ele mesmo fez). */
class RelatorioController extends Controller
{
    /** Quantas linhas por lista no modo analítico (acima disso, usar Exportar CSV). */
    private const LIMITE = 300;

    public function meu(Request $request)
    {
        [$inicio, $fim, $periodo] = CaixaController::periodo($request);
        $uid = auth()->id();
        $todos = auth()->user()->relatorioTodos();   // vê de todos ou só o próprio
        $canal = $request->input('canal'); // dinheiro|pix_manual|pix_auto|debito|credito
        $podeTransporte = auth()->user()->podeFiltrarTransporte() || auth()->user()->is_revendedor;
        $transporteId = $podeTransporte ? $request->input('transporte_id') : null;
        $transportes = \App\Models\Embarcacao::orderBy('nome')->get();
        $viagemId = $request->input('viagem_id') ?: null;
        $viagens = \App\Support\Tenant::empresa()?->temRecurso('viagens')
            ? \App\Models\Viagem::with('rota')->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
                ->where(fn ($q) => $q->whereBetween('data_partida', [now()->subMonthsNoOverflow(3)->startOfDay(), now()->addMonthsNoOverflow(3)->endOfDay()])->orWhereNull('data_partida'))
                ->orderByDesc('data_partida')->limit(300)->get() : collect();
        $fViagemPag = fn ($q) => $q->where(function ($w) use ($viagemId) {
            $w->where(fn ($x) => $x->where('modulo', 'passagem')->whereIn('origem_id', \App\Models\Passagem::where('viagem_id', $viagemId)->select('id')))
              ->orWhere(fn ($x) => $x->where('modulo', 'encomenda')->whereIn('encomenda_id', \App\Models\Encomenda::where('viagem_id', $viagemId)->select('id')));
        });

        $dataViagem = $request->input('data_viagem') ?: null;
        $datasViagem = \App\Models\Passagem::where('cancelada', false)->whereNotNull('data_viagem')
            ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
            ->whereBetween('data_viagem', [now()->subMonthsNoOverflow(3)->toDateString(), now()->addMonthsNoOverflow(3)->toDateString()])
            ->select('data_viagem')->distinct()->orderByDesc('data_viagem')->limit(200)->pluck('data_viagem');
        $fDataViagemPag = fn ($q) => $q->where('modulo', 'passagem')->whereIn('origem_id', \App\Models\Passagem::whereDate('data_viagem', $dataViagem)->select('id'));

        // período longo (> 31 dias) abre resumido por padrão pra não travar
        $longo = $inicio->diffInDays($fim) > 31;
        $pedido = $request->input('modo');
        $modo = $pedido === 'resumido' ? 'resumido' : ($pedido === 'analitico' ? 'analitico' : ($longo ? 'resumido' : 'analitico'));

        $encQ = Encomenda::when(! $todos, fn ($q) => $q->where('user_id', $uid))->whereBetween('created_at', [$inicio, $fim])->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId));
        $pagQ = Pagamento::when(! $todos, fn ($q) => $q->where('user_id', $uid))->where('estornado', false)
            ->where(fn ($q) => $q->whereNull('momento')->orWhere('momento', '!=', 'baixa'))
            ->when($canal, fn ($q) => $q->canal($canal))->whereBetween('created_at', [$inicio, $fim])
            ->when($transporteId, fn ($q) => $q->where(function ($w) use ($transporteId) { $w->where(fn ($x) => $x->where('modulo', 'passagem')->whereIn('origem_id', \App\Models\Passagem::where('embarcacao_id', $transporteId)->select('id')))->orWhere(fn ($x) => $x->where('modulo', 'encomenda')->whereIn('encomenda_id', \App\Models\Encomenda::where('embarcacao_id', $transporteId)->select('id'))); }))->when($viagemId, $fViagemPag)->when($dataViagem, $fDataViagemPag);
        $evQ = Evento::when(! $todos, fn ($q) => $q->where('user_id', $uid))->where('tipo', 'entrega')->whereBetween('created_at', [$inicio, $fim])->when($transporteId, fn ($q) => $q->whereIn('encomenda_id', \App\Models\Encomenda::where('embarcacao_id', $transporteId)->select('id')))->when($viagemId, fn ($q) => $q->whereIn('encomenda_id', \App\Models\Encomenda::where('viagem_id', $viagemId)->select('id')));

        $cancelados = \App\Models\Passagem::where('cancelada', true)
            ->when(! $todos, fn ($q) => $q->where('user_id', $uid))
            ->whereBetween('created_at', [$inicio, $fim])
            ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(total),0) as valor')->first();

        // Totais somados NO BANCO (rápido mesmo com milhões de linhas)
        $totais = [
            'cadastros'       => (clone $encQ)->count(),
            'cadastros_valor' => (float) (clone $encQ)->sum('total'),
            'recebido_qtd'    => (clone $pagQ)->where('meio', '!=', 'desconto')->count(),
            'entregas'        => (clone $evQ)->count(),
        ];
        $porMeio = (clone $pagQ)->where('meio', '!=', 'desconto')
            ->selectRaw(Pagamento::canalSql().' as canal_sql, SUM(valor) v')->groupBy('canal_sql')->pluck('v', 'canal_sql');

        // Listas só no analítico, limitadas (o resto é via Exportar)
        $limite = self::LIMITE;
        $cadastros    = $modo === 'analitico' ? (clone $encQ)->with('embarcacao')->orderByDesc('id')->limit($limite)->get() : collect();
        $recebimentos = $modo === 'analitico' ? (clone $pagQ)->with(['user', 'encomenda.embarcacao', 'passagemOrigem.embarcacao', 'passagemOrigem.passageiros:id,passagem_id,poltrona,nome'])->orderByDesc('id')->limit($limite)->get() : collect();
        $entregas     = $modo === 'analitico' ? (clone $evQ)->with('encomenda')->orderByDesc('id')->limit($limite)->get() : collect();

        // Acerto do agente externo (revendedor): quanto vendeu, comissao dele e quanto passar pra empresa
        $acerto = null;
        $pendentes = collect();
        $debitosDono = collect();
        $uu = auth()->user();
        if ($uu->is_revendedor) {
            $passQ = Passagem::where('revendedor_id', $uu->id)->where('cancelada', false)->whereBetween('created_at', [$inicio, $fim])->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId));
            $bruto = (float) (clone $passQ)->sum('total');
            $repasseTotal = (float) (clone $passQ)->sum('acerto_empresa_valor');
            $aPassar = (float) (clone $passQ)->where('acerto_empresa', false)->whereNotIn('embarcacao_id', \App\Models\Embarcacao::whereNotNull('dono_id')->select('id'))->sum('acerto_empresa_valor');
            $empAcerto = \App\Support\Tenant::empresa();
            $acertoApi = $uu->acerto_pix_api_id ? \App\Models\PagamentoApi::find($uu->acerto_pix_api_id) : null;
            $_pct = max(0, min(100, (int) ($uu->comissao_pct ?? 0)));
            $repasseExpr = (($uu->modo_acerto ?? 'cheio') === 'comissao') ? 'ROUND(total * (100 - '.$_pct.') / 100, 2)' : 'total';
            $repassePend = (float) Passagem::where('revendedor_id', $uu->id)->where('cancelada', false)->where('acerto_empresa', false)->whereNotIn('embarcacao_id', \App\Models\Embarcacao::whereNotNull('dono_id')->select('id'))
                ->selectRaw('COALESCE(SUM(COALESCE(acerto_empresa_valor, '.$repasseExpr.')),0) as v')->value('v');
            $avEnt = (float) Pagamento::where('user_id', $uu->id)->where('estornado', false)->where('modulo', 'avulso')->where('tipo_mov', 'entrada')->where('conferido', false)->where('acerto_conta', true)->selectRaw('COALESCE(SUM(valor * (100 - COALESCE(acerto_pct,0)) / 100),0) as v')->value('v');
            $avSai = (float) Pagamento::where('user_id', $uu->id)->where('estornado', false)->where('modulo', 'avulso')->where('tipo_mov', 'saida')->where('conferido', false)->where('acerto_conta', true)->sum('valor');
            $acerto = [
                'qtd' => (clone $passQ)->count(),
                'bruto' => $bruto,
                'a_passar' => round($aPassar, 2),
                'comissao' => round($bruto - $repasseTotal, 2),
                'pct' => (int) ($uu->comissao_pct ?? 0),
                'modo' => $uu->modo_acerto ?? 'cheio',
                'pix_modo' => $acertoApi ? ($acertoApi->ehEstatico() ? 'estatico' : 'dinamico') : 'nenhum',
                'emp_pix' => ($acertoApi && $acertoApi->ehEstatico()) ? $acertoApi->pixChave() : null,
                'emp_nome' => (($acertoApi && $acertoApi->ehEstatico()) ? $acertoApi->pixRecebedor() : null) ?: $empAcerto?->nomeExibicao(),
                'emp_cidade' => (($acertoApi && $acertoApi->ehEstatico()) ? $acertoApi->pixCidade() : null) ?: 'CIDADE',
                'pode_dinamico' => (bool) ($acertoApi && ! $acertoApi->ehEstatico()),
                'repasse_passagem' => round($repassePend, 2),
                'av_entrada' => round($avEnt, 2),
                'av_saida' => round($avSai, 2),
                'total_devido' => round($repassePend + $avEnt - $avSai, 2),
                'acertado_hoje' => round((float) Passagem::where('revendedor_id', $uu->id)->where('cancelada', false)->whereNotIn('embarcacao_id', \App\Models\Embarcacao::whereNotNull('dono_id')->select('id'))->where('acerto_empresa', true)->whereDate('acerto_empresa_em', today())->sum('acerto_empresa_valor'), 2),
            ];
            // débitos em aberto (não acertados) — TODAS as datas, independe do período
            $pendentes = Passagem::where('revendedor_id', $uu->id)->where('cancelada', false)->where('acerto_empresa', false)->whereNotIn('embarcacao_id', \App\Models\Embarcacao::whereNotNull('dono_id')->select('id'))
                ->orderByDesc('id')->limit(200)
                ->selectRaw('id, numero, numero_completo, created_at, origem, destino, COALESCE(acerto_empresa_valor, '.$repasseExpr.') as valor_aberto')
                ->get();
            // Debito com os DONOS das lanchas (barcos com dono_id): o agente paga direto o dono.
            $debitosDono = Passagem::where('revendedor_id', $uu->id)->where('cancelada', false)->where('transferida', false)->where('acerto_dono', false)
                ->whereIn('embarcacao_id', \App\Models\Embarcacao::whereNotNull('dono_id')->select('id'))
                ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
                ->selectRaw('embarcacao_id, COUNT(*) as qtd, COALESCE(SUM(acerto_empresa_valor),0) as valor')
                ->groupBy('embarcacao_id')->get()
                ->map(function ($r) {
                    $emb = \App\Models\Embarcacao::find($r->embarcacao_id);
                    return (object) [
                        'nome' => $emb?->nome ?? ('Barco #'.$r->embarcacao_id),
                        'dono' => optional($emb?->dono)->name ?? $emb?->dono_nome,
                        'qtd' => (int) $r->qtd,
                        'valor' => (float) $r->valor,
                    ];
                })->filter(fn ($x) => $x->valor > 0)->sortByDesc('valor')->values();
        }

        $_modAtivos = \App\Support\Tenant::empresa()?->config('modulos_ativos');
        $_encOn = empty($_modAtivos) || in_array('encomenda', (array) $_modAtivos, true);
        $temEncomenda = $uu->temModulo('encomenda') && $_encOn;
        $podeAvulso = $uu->podeModulo('avulso');
        $categorias = \App\Models\AvulsoCategoria::where('ativo', true)->orderBy('ordem')->orderBy('nome')->get();
        if ($request->boolean('pdf')) {
            $recs = (clone $pagQ)->with(['passagemOrigem.embarcacao', 'encomenda.embarcacao'])->orderByDesc('id')->limit(2000)->get();
            $linhas = $recs->map(function ($p) {
                $pg = $p->passagemOrigem; $en = $p->encomenda;
                $quem = $pg ? ($pg->comprador_nome ?: '') : ($en?->destinatario_nome ?: '');
                $transp = $pg?->embarcacao?->nome ?? $en?->embarcacao?->nome;
                return (object) [
                    'data' => $p->created_at,
                    'descricao' => trim(($transp ? '🚤 '.$transp.' · ' : '').($quem ?: 'Recebimento')),
                    'ref' => '#'.($pg?->numeroFormatado() ?? $en?->numeroFormatado() ?? ''),
                    'valor' => (float) $p->valor,
                ];
            });
            $canais = Pagamento::CANAIS;
            $totais = [['label' => 'Total recebido', 'valor' => (float) $porMeio->sum(), 'forte' => true]];
            foreach ($porMeio as $k => $v) $totais[] = ['label' => ($canais[$k] ?? $k), 'valor' => (float) $v];
            $totais[] = ['label' => 'Cancelados ('.(int) ($cancelados->qtd ?? 0).')', 'valor' => (float) ($cancelados->valor ?? 0)];
            return view('relatorio.prestacao', [
                'empresa' => \App\Support\Tenant::empresa(),
                'titulo' => 'Prestação de contas',
                'subtitulo' => $todos ? 'Relatório geral' : (($uu->ehRevendedor() ? 'Agente: ' : 'Operador: ').$uu->name),
                'periodo' => 'Período: '.\Carbon\Carbon::parse($inicio)->format('d/m/Y').' a '.\Carbon\Carbon::parse($fim)->format('d/m/Y'),
                'linhas' => $linhas,
                'totais' => $totais,
                'assinatura' => $uu->name,
                'voltar' => route('relatorio.meu'),
            ]);
        }

        return view('caixa.meu', compact('cadastros', 'recebimentos', 'entregas', 'porMeio', 'totais', 'inicio', 'fim', 'periodo', 'todos', 'modo', 'canal', 'limite', 'transportes', 'transporteId', 'podeTransporte', 'viagens', 'viagemId', 'dataViagem', 'datasViagem'))
            ->with(['meios' => Pagamento::CANAIS, 'acerto' => $acerto, 'pendentes' => $pendentes, 'debitosDono' => $debitosDono, 'temEncomenda' => $temEncomenda, 'podeAvulso' => $podeAvulso, 'categorias' => $categorias, 'cancelados' => $cancelados]);
    }

    /** Exporta os recebimentos do período (e filtro de forma) em CSV — sem travar a tela. */
    public function exportar(Request $request)
    {
        [$inicio, $fim] = CaixaController::periodo($request);
        $uid = auth()->id();
        $todos = auth()->user()->relatorioTodos();
        $canal = $request->input('canal');

        $q = Pagamento::when(! $todos, fn ($w) => $w->where('user_id', $uid))->where('estornado', false)
            ->where('meio', '!=', 'desconto')->when($canal, fn ($w) => $w->canal($canal))
            ->whereBetween('created_at', [$inicio, $fim])->with(['user', 'encomenda'])->orderByDesc('id');

        $nome = 'recebimentos-'.$inicio->format('Ymd').'-'.$fim->format('Ymd').'.csv';
        $canais = Pagamento::CANAIS;

        return response()->streamDownload(function () use ($q, $canais) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF"); // BOM pro Excel abrir acentos certo
            fputcsv($out, ['Data', 'Hora', 'Módulo', 'Número', 'Operador', 'Forma', 'Momento', 'Valor'], ';');
            $q->chunk(1000, function ($lote) use ($out, $canais) {
                foreach ($lote as $p) {
                    $rf = $p->resumoFonte();
                    fputcsv($out, [
                        $p->created_at->format('d/m/Y'),
                        $p->created_at->format('H:i'),
                        \App\Support\Modulos::rotulo($p->modulo ?: 'encomenda'),
                        $rf['numero'] ?? '',
                        $p->user?->name,
                        $canais[$p->canal] ?? ($p->meio),
                        $p->momento === 'cadastro' ? 'no cadastro' : 'na baixa',
                        number_format((float) $p->valor, 2, ',', '.'),
                    ], ';');
                }
            });
            fclose($out);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
