<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CaixaController;
use App\Http\Controllers\Controller;
use App\Models\Encomenda;
use App\Models\Frete;
use App\Models\Pagamento;
use App\Models\Passagem;
use App\Models\PassagemPassageiro;
use Illuminate\Http\Request;

/**
 * Dashboard gerencial: visão de todos os módulos — recebimentos, cadastros,
 * quem mais viaja, quem mais envia e quem deve há mais tempo.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->filled('periodo')) $request->merge(['periodo' => 'mes']);
        [$inicio, $fim, $periodo] = CaixaController::periodo($request);

        // ---------------- Caixa do período ----------------
        $pagBase = Pagamento::where('estornado', false)->whereBetween('created_at', [$inicio, $fim]);
        $entradas = (float) (clone $pagBase)->where('meio', '!=', 'desconto')->where('tipo_mov', '!=', 'saida')->sum('valor');
        $saidas = (float) (clone $pagBase)->where('tipo_mov', 'saida')->sum('valor');
        $liquido = $entradas - $saidas;

        $canalSql = Pagamento::canalSql();
        $porCanal = (clone $pagBase)->where('meio', '!=', 'desconto')->where('tipo_mov', '!=', 'saida')
            ->selectRaw("$canalSql as canal, SUM(valor) valor")->groupBy('canal')->pluck('valor', 'canal');
        $porModulo = (clone $pagBase)->where('meio', '!=', 'desconto')->where('tipo_mov', '!=', 'saida')
            ->selectRaw('modulo, SUM(valor) valor, COUNT(*) qtd')->groupBy('modulo')->get()->keyBy('modulo');

        // ---------------- Cadastros do período ----------------
        $ini = $inicio; $f = $fim;
        $cad = [
            'encomenda' => Encomenda::whereBetween('created_at', [$ini, $f])->where('cancelada', false)->count(),
            'passagem'  => Passagem::whereBetween('created_at', [$ini, $f])->where('cancelada', false)->where('tipo', '!=', 'camarote')->count(),
            'camarote'  => Passagem::whereBetween('created_at', [$ini, $f])->where('cancelada', false)->where('tipo', 'camarote')->count(),
            'frete'     => Frete::whereBetween('created_at', [$ini, $f])->where('cancelada', false)->count(),
        ];

        // ---------------- A receber em aberto (tudo, não cancelado) ----------------
        $aReceber = (float) Encomenda::where('cancelada', false)->where('devido', '>', 0.009)->sum('devido')
            + (float) Passagem::where('cancelada', false)->where('devido', '>', 0.009)->sum('devido')
            + (float) Frete::where('cancelada', false)->where('devido', '>', 0.009)->sum('devido');

        // ---------------- Quem mais viaja (passageiros no período) ----------------
        $topPassageiros = PassagemPassageiro::whereHas('passagem', fn ($q) => $q->where('cancelada', false)->whereBetween('created_at', [$ini, $f]))
            ->selectRaw("TRIM(nome) nome, COUNT(*) qtd, SUM(valor_total) valor")
            ->groupByRaw('TRIM(nome)')->havingRaw("TRIM(nome) <> ''")
            ->orderByDesc('qtd')->limit(10)->get();

        // ---------------- Quem mais envia (encomendas + fretes no período) ----------------
        $envEnc = Encomenda::where('cancelada', false)->whereBetween('created_at', [$ini, $f])
            ->selectRaw("TRIM(remetente_nome) nome, COUNT(*) qtd, SUM(total) valor")->groupByRaw('TRIM(remetente_nome)')->get();
        $envFre = Frete::where('cancelada', false)->whereBetween('created_at', [$ini, $f])
            ->selectRaw("TRIM(remetente_nome) nome, COUNT(*) qtd, SUM(total) valor")->groupByRaw('TRIM(remetente_nome)')->get();
        $topRemetentes = $envEnc->concat($envFre)
            ->filter(fn ($r) => trim((string) $r->nome) !== '')
            ->groupBy('nome')
            ->map(fn ($g, $nome) => (object) ['nome' => $nome, 'qtd' => $g->sum('qtd'), 'valor' => $g->sum('valor')])
            ->sortByDesc('qtd')->take(10)->values();

        // ---------------- Devedores há mais tempo (todos os módulos, em aberto) ----------------
        $dev = collect();
        Encomenda::where('cancelada', false)->where('devido', '>', 0.009)->orderBy('created_at')->limit(40)->get()
            ->each(fn ($e) => $dev->push((object) ['modulo' => 'encomenda', 'numero' => $e->numeroFormatado(), 'cliente' => $e->remetente_nome ?: $e->destinatario_nome, 'devido' => (float) $e->devido, 'data' => $e->created_at, 'url' => route('encomendas.show', $e)]));
        Passagem::where('cancelada', false)->where('devido', '>', 0.009)->orderBy('created_at')->limit(40)->get()
            ->each(fn ($p) => $dev->push((object) ['modulo' => $p->tipo === 'camarote' ? 'camarote' : 'passagem', 'numero' => $p->numeroFormatado(), 'cliente' => $p->comprador_nome ?: '—', 'devido' => (float) $p->devido, 'data' => $p->created_at, 'url' => route('passagens.show', $p)]));
        Frete::where('cancelada', false)->where('devido', '>', 0.009)->orderBy('created_at')->limit(40)->get()
            ->each(fn ($fr) => $dev->push((object) ['modulo' => 'frete', 'numero' => $fr->numeroFormatado(), 'cliente' => $fr->remetente_nome ?: '—', 'devido' => (float) $fr->devido, 'data' => $fr->created_at, 'url' => route('fretes.show', $fr)]));
        $devedores = $dev->sortBy('data')->take(15)->values();
        $totalDevedores = $dev->count();

        // ---------------- Top rotas do período ----------------
        $topRotas = collect();
        foreach ([[Encomenda::class, 'encomenda'], [Passagem::class, 'passagem'], [Frete::class, 'frete']] as [$model, $mod]) {
            $model::where('cancelada', false)->whereBetween('created_at', [$ini, $f])
                ->selectRaw("CONCAT(origem,' → ',destino) rota, COUNT(*) qtd")->groupBy('rota')->get()
                ->each(fn ($r) => $topRotas->push($r));
        }
        $topRotas = $topRotas->groupBy('rota')->map(fn ($g, $rota) => (object) ['rota' => $rota, 'qtd' => $g->sum('qtd')])
            ->sortByDesc('qtd')->take(8)->values();

        return view('admin.dashboard', compact(
            'inicio', 'fim', 'periodo', 'entradas', 'saidas', 'liquido', 'porCanal', 'porModulo',
            'cad', 'aReceber', 'topPassageiros', 'topRemetentes', 'devedores', 'totalDevedores', 'topRotas'
        ))->with(['canais' => Pagamento::CANAIS]);
    }
}
