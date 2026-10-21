<?php

namespace App\Http\Controllers;

use App\Models\Encomenda;
use App\Models\Evento;
use App\Models\Pagamento;
use App\Models\User;
use App\Models\Viagem;
use Illuminate\Http\Request;

class CaixaController extends Controller
{
    public function index(Request $request)
    {
        [$inicio, $fim, $periodo] = $this->periodo($request);
        // escopo: quem não vê "de todos" fica travado no próprio
        $verTodos = auth()->user()->caixaTodos();
        $userId = $verTodos ? $request->input('user_id') : auth()->id();
        $viagemId = $request->input('viagem_id');
        $modulo = $request->input('modulo');
        $viagens = \App\Support\Tenant::empresa()->temRecurso('viagens')
            ? Viagem::orderByDesc('data_partida')->limit(60)->get() : collect();
        // quando filtra por viagem, ignora o período (viagem é o recorte)
        $encIds = $viagemId ? Encomenda::where('viagem_id', $viagemId)->pluck('id') : null;

        $base = Pagamento::where('estornado', false)
            ->where('meio', '!=', 'desconto')
            ->when($encIds === null, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($encIds !== null, fn ($q) => $q->whereIn('encomenda_id', $encIds))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($modulo, fn ($q) => $q->where('modulo', $modulo));

        $porMeio = (clone $base)->selectRaw('meio, SUM(valor) valor, COUNT(*) qtd')->groupBy('meio')->get()->keyBy('meio');
        $porOperador = (clone $base)->selectRaw('user_id, meio, SUM(valor) valor')->groupBy('user_id', 'meio')->get()
            ->groupBy('user_id')->map(fn ($rows) => [
                'total' => $rows->sum('valor'),
                'meios' => $rows->pluck('valor', 'meio'),
            ]);
        $porModulo = (clone $base)->selectRaw('modulo, SUM(valor) valor, COUNT(*) qtd')->groupBy('modulo')->get()->keyBy('modulo');

        $usuarios = User::where('empresa_id', auth()->user()->empresa_id)->orderBy('name')->get()->keyBy('id');

        $descontos = Pagamento::where('estornado', false)->where('meio', 'desconto')
            ->when($encIds === null, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($encIds !== null, fn ($q) => $q->whereIn('encomenda_id', $encIds))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))->when($modulo, fn ($q) => $q->where('modulo', $modulo))->sum('valor');

        $entregas = Evento::where('tipo', 'entrega')
            ->when($encIds === null, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($encIds !== null, fn ($q) => $q->whereIn('encomenda_id', $encIds))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))->count();
        $cadastros = Encomenda::when($encIds === null, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))->count();

        $movimentos = (clone $base)->with(['user', 'encomenda'])->orderByDesc('id')->paginate(100)->withQueryString();

        return view('caixa.index', compact('porMeio', 'porModulo', 'porOperador', 'usuarios', 'descontos', 'entregas', 'cadastros', 'movimentos', 'inicio', 'fim', 'periodo', 'userId', 'viagens', 'viagemId', 'verTodos', 'modulo'))
            ->with(['meios' => Pagamento::MEIOS, 'total' => $porMeio->sum('valor')]);
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
