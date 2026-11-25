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

        // Faturados cujo positivo do dono JÁ foi varrido por uma prestação fechada -> não dá pra desfazer a baixa aqui.
        $lockedIds = $pagas->isEmpty() ? [] : Pagamento::where('modulo', 'avulso')->where('categoria', 'Recebimento faturado')
            ->whereIn('origem_id', $pagas->pluck('id'))->where('estornado', false)->whereNotNull('prestacao_id')
            ->pluck('origem_id')->map(fn ($x) => (int) $x)->all();

        return view('financeiro.contas-receber', compact('grupos', 'pagas', 'clientes', 'clienteId', 'busca', 'totalGeral', 'lockedIds'));
    }

    /** Baixa (recebimento) de uma ou várias passagens faturadas de uma vez.
     *  A empresa fica com $pct% (padrão 10%); o resto é do dono do transporte e entra
     *  como POSITIVO identificado ("Recebimento faturado") na próxima prestação dele. */
    public function baixar(Request $request)
    {
        $this->gate();
        $data = $request->validate([
            'passagem_ids' => 'required|array|min:1',
            'passagem_ids.*' => 'integer',
            'meio' => 'required|in:dinheiro,pix,debito,credito',
            'pct' => 'nullable|numeric|min:0|max:100',
        ]);
        $pct = isset($data['pct']) ? round((float) $data['pct'], 2) : 10.0;

        $ps = Passagem::whereIn('id', $data['passagem_ids'])
            ->where('forma', 'faturado')->where('cancelada', false)->where('transferida', false)
            ->with(['clienteFaturado:id,nome,empresa'])->get();

        $n = 0;
        $soma = 0.0;
        $empresaTot = 0.0;
        $donoTot = 0.0;
        foreach ($ps as $p) {
            $dev = round((float) $p->devido, 2);
            if ($dev <= 0.009) continue;

            // 1) registra o recebimento (dinheiro entra no caixa / zera o devido)
            app(PassagemService::class)->baixa($p, [$data['meio'] => $dev], (int) auth()->id());

            // 2) parte do dono vira POSITIVO identificado na próxima prestação do transporte
            $parteDono = round($dev * (100 - $pct) / 100, 2);
            if ($parteDono > 0.009 && $p->embarcacao_id) {
                $cli = optional($p->clienteFaturado)->nome ?: 'cliente faturado';
                $via = $p->data_viagem ? \Illuminate\Support\Carbon::parse($p->data_viagem)->format('d/m/Y') : '—';
                $compra = $p->created_at ? $p->created_at->format('d/m/Y') : '—';
                $label = 'pagamento de '.$cli.' · bilhete '.$p->numeroFormatado().' · compra '.$compra.' · viagem '.$via.' · empresa '.rtrim(rtrim(number_format($pct, 2, ',', '.'), '0'), ',').'%';
                Pagamento::create([
                    'empresa_id' => \App\Support\Tenant::id(),
                    'modulo' => 'avulso',
                    'origem_id' => $p->id,
                    'encomenda_id' => null,
                    'meio' => $data['meio'],
                    'valor' => $parteDono,
                    'user_id' => (int) auth()->id(),
                    'momento' => 'cadastro',
                    'tipo_mov' => 'entrada',
                    'categoria' => 'Recebimento faturado',
                    'descricao' => $label,
                    'embarcacao_id' => $p->embarcacao_id,
                    'data_viagem' => $p->data_viagem,
                    'acerto_conta' => true,
                    'acerto_pct' => 0,
                ]);
            }
            $n++;
            $soma += $dev;
            $empresaTot += round($dev * $pct / 100, 2);
            $donoTot += $parteDono;
        }

        if ($n === 0) return back()->with('erro', 'Nenhuma passagem pendente pra baixar (talvez já estejam pagas).');

        return back()->with('ok', $n.' faturado(s) recebido(s) · R$ '.number_format($soma, 2, ',', '.')
            .' · empresa R$ '.number_format($empresaTot, 2, ',', '.')
            .' · dono R$ '.number_format($donoTot, 2, ',', '.').' (entra como positivo na próxima prestação do transporte).');
    }

    /** Desfaz a baixa de uma passagem faturada (volta pra contas a receber) e estorna o
     *  "Recebimento faturado" (parte do dono) lançado na prestação — se ainda não fechou. */
    public function desfazerBaixa(Request $request, Passagem $passagem)
    {
        $this->gate();
        abort_if($passagem->forma !== 'faturado', 422, 'Só faturado.');
        abort_if($passagem->acerto_dono || $passagem->prestacao_id, 422, 'Essa passagem já entrou numa prestação fechada. Desfaça a prestação primeiro, depois a baixa.');

        // Se o positivo do dono já foi varrido por uma prestação fechada, não dá pra desfazer aqui.
        $avReceb = Pagamento::where('modulo', 'avulso')->where('categoria', 'Recebimento faturado')
            ->where('origem_id', $passagem->id)->where('estornado', false);
        abort_if((clone $avReceb)->whereNotNull('prestacao_id')->exists(), 422, 'O valor do dono desse faturado já entrou numa prestação fechada do transporte. Desfaça aquela prestação primeiro, depois a baixa.');

        $qtd = Pagamento::where('modulo', 'passagem')->where('origem_id', $passagem->id)
            ->where('momento', 'baixa')->where('estornado', false)->update(['estornado' => true]);
        // estorna o positivo do dono (parte da prestação) junto
        (clone $avReceb)->whereNull('prestacao_id')->update(['estornado' => true]);

        if ($qtd > 0) $passagem->recalcular();

        return back()->with('ok', $qtd > 0 ? 'Baixa desfeita — a passagem voltou pra contas a receber (o positivo do dono na prestação também foi estornado).' : 'Não havia baixa pra desfazer nessa passagem.');
    }
}
