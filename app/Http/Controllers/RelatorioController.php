<?php

namespace App\Http\Controllers;

use App\Models\Encomenda;
use App\Models\Evento;
use App\Models\Pagamento;
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

        // período longo (> 31 dias) abre resumido por padrão pra não travar
        $longo = $inicio->diffInDays($fim) > 31;
        $pedido = $request->input('modo');
        $modo = $pedido === 'resumido' ? 'resumido' : ($pedido === 'analitico' ? 'analitico' : ($longo ? 'resumido' : 'analitico'));

        $encQ = Encomenda::when(! $todos, fn ($q) => $q->where('user_id', $uid))->whereBetween('created_at', [$inicio, $fim]);
        $pagQ = Pagamento::when(! $todos, fn ($q) => $q->where('user_id', $uid))->where('estornado', false)
            ->when($canal, fn ($q) => $q->canal($canal))->whereBetween('created_at', [$inicio, $fim]);
        $evQ = Evento::when(! $todos, fn ($q) => $q->where('user_id', $uid))->where('tipo', 'entrega')->whereBetween('created_at', [$inicio, $fim]);

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

        return view('caixa.meu', compact('cadastros', 'recebimentos', 'entregas', 'porMeio', 'totais', 'inicio', 'fim', 'periodo', 'todos', 'modo', 'canal', 'limite'))
            ->with(['meios' => Pagamento::CANAIS]);
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
