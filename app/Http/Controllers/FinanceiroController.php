<?php

namespace App\Http\Controllers;

use App\Models\ClienteFaturado;
use App\Models\Pagamento;
use App\Models\Passagem;
use App\Services\PassagemService;
use Illuminate\Http\Request;

class FinanceiroController extends Controller
{
    /** Acesso ao Financeiro: admin/super ou quem tem a permissão perm_financeiro. */
    protected function gate(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isAdmin() || $u->isSuper() || (bool) $u->perm_financeiro), 403, 'Sem acesso ao Financeiro.');
    }

    /** Lista de faturados a receber (pendentes) + os pagos recentemente (pra desfazer baixa). */
    public function contasReceber(Request $request)
    {
        $this->gate();
        $clienteId = $request->input('cliente_id') ?: null;
        $busca = trim((string) $request->input('busca', ''));

        $base = fn () => Passagem::where('forma', 'faturado')->where('cancelada', false)->where('transferida', false)
            ->whereNotNull('cliente_faturado_id')
            ->when($clienteId, fn ($x) => $x->where('cliente_faturado_id', $clienteId))
            ->with(['clienteFaturado:id,nome,empresa,documento', 'embarcacao:id,nome', 'passageiros:id,passagem_id,nome', 'pagamentos']);

        // A RECEBER = faturado com saldo devido > 0
        $pend = $base()->orderBy('data_viagem')->get()->filter(fn ($p) => (float) $p->devido > 0.009)->values();

        // agrupado por cliente (pra pagar tudo de um cliente de uma vez)
        $grupos = $pend->groupBy('cliente_faturado_id')->map(function ($ps) {
            $cli = $ps->first()->clienteFaturado;
            return (object) [
                'cliente' => $cli,
                'id' => $cli?->id,
                'nome' => $cli?->nome ?? 'Cliente',
                'empresa' => $cli?->empresa,
                'qtd' => $ps->count(),
                'total' => round($ps->sum(fn ($p) => (float) $p->devido), 2),
                'passagens' => $ps->sortBy('data_viagem')->values(),
            ];
        })->sortByDesc('total')->values();

        if ($busca !== '') {
            $b = mb_strtolower($busca);
            $grupos = $grupos->filter(fn ($g) => str_contains(mb_strtolower((string) $g->nome), $b) || str_contains(mb_strtolower((string) $g->empresa), $b))->values();
        }

        // PAGOS: faturado quitado (devido<=0) que teve baixa -> aparece na lista pra poder DESFAZER o pagamento
        $pagas = $base()->orderByDesc('updated_at')->limit(200)->get()
            ->filter(fn ($p) => (float) $p->devido <= 0.009
                && $p->pagamentos->where('estornado', false)->where('momento', 'baixa')->where('meio', '!=', 'desconto')->sum('valor') > 0.009)
            ->sortByDesc(fn ($p) => optional($p->pagamentos->where('momento', 'baixa')->where('estornado', false)->last())->created_at)
            ->values();

        $clientes = ClienteFaturado::orderBy('nome')->get(['id', 'nome']);
        $totalGeral = round($grupos->sum('total'), 2);

        return view('financeiro.contas-receber', compact('grupos', 'pagas', 'clientes', 'clienteId', 'busca', 'totalGeral'));
    }

    /** Baixa (recebimento) de uma ou várias passagens faturadas de uma vez. */
    public function baixar(Request $request)
    {
        $this->gate();
        $data = $request->validate([
            'passagem_ids' => 'required|array|min:1',
            'passagem_ids.*' => 'integer',
            'meio' => 'required|in:dinheiro,pix,debito,credito',
        ]);

        $ps = Passagem::whereIn('id', $data['passagem_ids'])
            ->where('forma', 'faturado')->where('cancelada', false)->where('transferida', false)->get();

        $n = 0;
        $soma = 0.0;
        foreach ($ps as $p) {
            $dev = round((float) $p->devido, 2);
            if ($dev <= 0.009) continue;
            app(PassagemService::class)->baixa($p, [$data['meio'] => $dev], (int) auth()->id());
            $n++;
            $soma += $dev;
        }

        if ($n === 0) return back()->with('erro', 'Nenhuma passagem pendente pra baixar (talvez já estejam pagas).');

        return back()->with('ok', $n.' passagem(ns) recebida(s) · R$ '.number_format($soma, 2, ',', '.').'. Entra como positivo na próxima prestação do transporte.');
    }

    /** Desfaz a baixa de uma passagem faturada (volta pra contas a receber). */
    public function desfazerBaixa(Request $request, Passagem $passagem)
    {
        $this->gate();
        abort_if($passagem->forma !== 'faturado', 422, 'Só faturado.');
        abort_if($passagem->acerto_dono || $passagem->prestacao_id, 422, 'Essa passagem já entrou numa prestação fechada. Desfaça a prestação primeiro, depois a baixa.');

        $qtd = Pagamento::where('modulo', 'passagem')->where('origem_id', $passagem->id)
            ->where('momento', 'baixa')->where('estornado', false)->update(['estornado' => true]);

        if ($qtd > 0) $passagem->recalcular();

        return back()->with('ok', $qtd > 0 ? 'Baixa desfeita — a passagem voltou pra contas a receber.' : 'Não havia baixa pra desfazer nessa passagem.');
    }
}
