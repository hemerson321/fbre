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
        $podeTransporte = auth()->user()->podeFiltrarTransporte();
        $transporteId = $podeTransporte ? $request->input('transporte_id') : null;
        $transportes = \App\Models\Embarcacao::orderBy('nome')->get();

        // período longo (> 31 dias) abre resumido por padrão pra não travar
        $longo = $inicio->diffInDays($fim) > 31;
        $pedido = $request->input('modo');
        $modo = $pedido === 'resumido' ? 'resumido' : ($pedido === 'analitico' ? 'analitico' : ($longo ? 'resumido' : 'analitico'));

        $encQ = Encomenda::when(! $todos, fn ($q) => $q->where('user_id', $uid))->whereBetween('created_at', [$inicio, $fim])->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId));
        $pagQ = Pagamento::when(! $todos, fn ($q) => $q->where('user_id', $uid))->where('estornado', false)
            ->when($canal, fn ($q) => $q->canal($canal))->whereBetween('created_at', [$inicio, $fim])
            ->when($transporteId, fn ($q) => $q->where(function ($w) use ($transporteId) { $w->where(fn ($x) => $x->where('modulo', 'passagem')->whereIn('origem_id', \App\Models\Passagem::where('embarcacao_id', $transporteId)->select('id')))->orWhere(fn ($x) => $x->where('modulo', 'encomenda')->whereIn('encomenda_id', \App\Models\Encomenda::where('embarcacao_id', $transporteId)->select('id'))); }));
        $evQ = Evento::when(! $todos, fn ($q) => $q->where('user_id', $uid))->where('tipo', 'entrega')->whereBetween('created_at', [$inicio, $fim])->when($transporteId, fn ($q) => $q->whereIn('encomenda_id', \App\Models\Encomenda::where('embarcacao_id', $transporteId)->select('id')));

        // Totais somados NO BANCO (rápido mesmo com milhões de linhas)
        $totais = [
            'cadastros'       => (clone $encQ)->count(),
            'cadastros_valor' => (float) (clone $encQ)->sum('total'),
            'recebido_qtd'    => (clone $pagQ)->where('meio', '!=', 'desconto')->count(),
            'entregas'        => (clone $evQ)->count(),
        ];
        $porMeio = (clone $pagQ)->where('meio', '!=', 'desconto')
            ->selectRaw(Pagamento::canalSql().' as canal, SUM(valor) v')->groupBy('canal')->pluck('v', 'canal');

        // Listas só no analítico, limitadas (o resto é via Exportar)
        $limite = self::LIMITE;
        $cadastros    = $modo === 'analitico' ? (clone $encQ)->with('embarcacao')->orderByDesc('id')->limit($limite)->get() : collect();
        $recebimentos = $modo === 'analitico' ? (clone $pagQ)->with('encomenda')->orderByDesc('id')->limit($limite)->get() : collect();
        $entregas     = $modo === 'analitico' ? (clone $evQ)->with('encomenda')->orderByDesc('id')->limit($limite)->get() : collect();

        // Acerto do agente externo (revendedor): quanto vendeu, comissao dele e quanto passar pra empresa
        $acerto = null;
        $uu = auth()->user();
        if ($uu->is_revendedor) {
            $passQ = Passagem::where('revendedor_id', $uu->id)->where('cancelada', false)->whereBetween('created_at', [$inicio, $fim]);
            $bruto = (float) (clone $passQ)->sum('total');
            $repasseTotal = (float) (clone $passQ)->sum('acerto_empresa_valor');
            $aPassar = (float) (clone $passQ)->where('acerto_empresa', false)->sum('acerto_empresa_valor');
            $empAcerto = \App\Support\Tenant::empresa();
            $acertoApi = $uu->acerto_pix_api_id ? \App\Models\PagamentoApi::find($uu->acerto_pix_api_id) : null;
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
                'total_devido' => round((float) Passagem::where('revendedor_id', $uu->id)->where('cancelada', false)->where('acerto_empresa', false)->sum('acerto_empresa_valor'), 2),
            ];
        }

        $temEncomenda = $uu->temModulo('encomenda');
        return view('caixa.meu', compact('cadastros', 'recebimentos', 'entregas', 'porMeio', 'totais', 'inicio', 'fim', 'periodo', 'todos', 'modo', 'canal', 'limite', 'transportes', 'transporteId', 'podeTransporte'))
            ->with(['meios' => Pagamento::CANAIS, 'acerto' => $acerto, 'temEncomenda' => $temEncomenda]);
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
