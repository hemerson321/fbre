<?php

namespace App\Http\Controllers;

use App\Models\Encomenda;
use App\Models\Evento;
use App\Models\Pagamento;
use Illuminate\Http\Request;

/** Relatório do próprio operador (só vê o que ele mesmo fez). */
class RelatorioController extends Controller
{
    public function meu(Request $request)
    {
        [$inicio, $fim, $periodo] = CaixaController::periodo($request);
        $uid = auth()->id();
        $todos = auth()->user()->relatorioTodos();   // vê de todos ou só o próprio

        $cadastros = Encomenda::when(! $todos, fn ($q) => $q->where('user_id', $uid))
            ->whereBetween('created_at', [$inicio, $fim])->with('embarcacao')->orderByDesc('id')->get();

        $canal = $request->input('canal'); // dinheiro|pix_manual|pix_auto|debito|credito

        $recebimentos = Pagamento::when(! $todos, fn ($q) => $q->where('user_id', $uid))->where('estornado', false)
            ->when($canal, fn ($q) => $q->canal($canal))
            ->whereBetween('created_at', [$inicio, $fim])->with('encomenda')->orderByDesc('id')->get();

        $entregas = Evento::when(! $todos, fn ($q) => $q->where('user_id', $uid))->where('tipo', 'entrega')
            ->whereBetween('created_at', [$inicio, $fim])->with('encomenda')->orderByDesc('id')->get();

        $porMeio = $recebimentos->where('meio', '!=', 'desconto')->groupBy('canal')->map->sum('valor');
        $modo = $request->input('modo') === 'resumido' ? 'resumido' : 'analitico';

        return view('caixa.meu', compact('cadastros', 'recebimentos', 'entregas', 'porMeio', 'inicio', 'fim', 'periodo', 'todos', 'modo', 'canal'))
            ->with(['meios' => Pagamento::CANAIS]);
    }
}
