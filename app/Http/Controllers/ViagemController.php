<?php

namespace App\Http\Controllers;

use App\Models\Viagem;
use Illuminate\Http\Request;

class ViagemController extends Controller
{
    public function index(Request $request)
    {
        $viagens = Viagem::with(['rota', 'embarcacao'])
            ->withCount('encomendas')
            ->withSum('encomendas as total_valor', 'total')
            ->withSum('encomendas as total_recebido', 'pago')
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('data_partida')->orderByDesc('id')
            ->paginate(40)->withQueryString();

        return view('viagens.index', ['viagens' => $viagens, 'status' => Viagem::STATUS, 'filtroStatus' => $request->input('status')]);
    }

    public function status(Request $request, Viagem $viagem)
    {
        $data = $request->validate(['status' => 'required|in:planejada,em_andamento,concluida,cancelada']);
        $viagem->update($data);
        return back()->with('ok', 'Status da viagem atualizado.');
    }
}
