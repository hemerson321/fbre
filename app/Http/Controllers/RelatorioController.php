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

        $cadastros = Encomenda::where('user_id', $uid)->whereBetween('created_at', [$inicio, $fim])
            ->with('embarcacao')->orderByDesc('id')->get();

        $recebimentos = Pagamento::where('user_id', $uid)->where('estornado', false)
            ->whereBetween('created_at', [$inicio, $fim])->with('encomenda')->orderByDesc('id')->get();

        $entregas = Evento::where('user_id', $uid)->where('tipo', 'entrega')
            ->whereBetween('created_at', [$inicio, $fim])->with('encomenda')->orderByDesc('id')->get();

        $porMeio = $recebimentos->where('meio', '!=', 'desconto')->groupBy('meio')->map->sum('valor');

        return view('caixa.meu', compact('cadastros', 'recebimentos', 'entregas', 'porMeio', 'inicio', 'fim', 'periodo'))
            ->with(['meios' => Pagamento::MEIOS]);
    }
}
