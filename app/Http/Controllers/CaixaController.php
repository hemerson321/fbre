<?php

namespace App\Http\Controllers;

use App\Models\Encomenda;
use App\Models\Evento;
use App\Models\Pagamento;
use App\Models\User;
use Illuminate\Http\Request;

class CaixaController extends Controller
{
    public function index(Request $request)
    {
        [$inicio, $fim, $periodo] = $this->periodo($request);
        $userId = $request->input('user_id');

        $base = Pagamento::where('estornado', false)
            ->where('meio', '!=', 'desconto')
            ->whereBetween('created_at', [$inicio, $fim])
            ->when($userId, fn ($q) => $q->where('user_id', $userId));

        $porMeio = (clone $base)->selectRaw('meio, SUM(valor) valor, COUNT(*) qtd')->groupBy('meio')->get()->keyBy('meio');
        $porOperador = (clone $base)->selectRaw('user_id, meio, SUM(valor) valor')->groupBy('user_id', 'meio')->get()
            ->groupBy('user_id')->map(fn ($rows) => [
                'total' => $rows->sum('valor'),
                'meios' => $rows->pluck('valor', 'meio'),
            ]);
        $usuarios = User::where('empresa_id', auth()->user()->empresa_id)->orderBy('name')->get()->keyBy('id');

        $descontos = Pagamento::where('estornado', false)->where('meio', 'desconto')
            ->whereBetween('created_at', [$inicio, $fim])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))->sum('valor');

        $entregas = Evento::where('tipo', 'entrega')->whereBetween('created_at', [$inicio, $fim])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))->count();
        $cadastros = Encomenda::whereBetween('created_at', [$inicio, $fim])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))->count();

        $movimentos = (clone $base)->with(['user', 'encomenda'])->orderByDesc('id')->paginate(100)->withQueryString();

        return view('caixa.index', compact('porMeio', 'porOperador', 'usuarios', 'descontos', 'entregas', 'cadastros', 'movimentos', 'inicio', 'fim', 'periodo', 'userId'))
            + ['meios' => Pagamento::MEIOS, 'total' => $porMeio->sum('valor')];
    }

    /** Resolve o período: hoje | semana | mes | personalizado */
    public static function periodo(Request $request): array
    {
        $p = $request->input('periodo', 'hoje');
        return match ($p) {
            'semana' => [now()->startOfWeek(), now()->endOfWeek(), 'semana'],
            'mes' => [now()->startOfMonth(), now()->endOfMonth(), 'mes'],
            'personalizado' => [
                \Carbon\Carbon::parse($request->input('inicio', now()->toDateString()).' '.$request->input('hora_inicio', '00:00')),
                \Carbon\Carbon::parse($request->input('fim', now()->toDateString()).' '.$request->input('hora_fim', '23:59')),
                'personalizado',
            ],
            default => [now()->startOfDay(), now()->endOfDay(), 'hoje'],
        };
    }
}
