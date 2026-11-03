<?php

namespace App\Http\Controllers;

use App\Models\Embarcacao;
use App\Models\Encomenda;
use App\Models\Evento;
use App\Models\Pagamento;
use App\Models\Passagem;
use App\Models\User;
use App\Models\Viagem;
use App\Models\AvulsoCategoria;
use App\Support\Tenant;
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
        $canal = $request->input('canal'); // dinheiro|pix_manual|pix_auto|debito|credito
        $embarcacaoId = $request->input('embarcacao_id');
        $viagens = \App\Support\Tenant::empresa()->temRecurso('viagens')
            ? Viagem::orderByDesc('data_partida')->limit(60)->get() : collect();
        $embarcacoes = Embarcacao::where('ativo', true)->orderBy('nome')->get(['id', 'nome']);
        // quando filtra por viagem, ignora o período (viagem é o recorte)
        $encIds = $viagemId ? Encomenda::where('viagem_id', $viagemId)->pluck('id') : null;

        // Filtro por Transporte (embarcação): escopa os pagamentos pela fonte (encomenda/passagem).
        $encEmb = $embarcacaoId ? Encomenda::where('embarcacao_id', $embarcacaoId)->pluck('id') : collect();
        $pasEmb = $embarcacaoId ? Passagem::where('embarcacao_id', $embarcacaoId)->pluck('id') : collect();
        $scopeEmb = function ($q) use ($embarcacaoId, $encEmb, $pasEmb) {
            if (! $embarcacaoId) return;
            $q->where(function ($w) use ($encEmb, $pasEmb) {
                $w->where(fn ($x) => $x->where('modulo', 'encomenda')->whereIn('encomenda_id', $encEmb))
                  ->orWhere(fn ($x) => $x->whereIn('modulo', ['passagem', 'camarote'])->whereIn('origem_id', $pasEmb));
            });
        };

        $base = Pagamento::where('estornado', false)
            ->where('meio', '!=', 'desconto')
            ->when($encIds === null, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($encIds !== null, fn ($q) => $q->whereIn('encomenda_id', $encIds))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($modulo, fn ($q) => $q->where('modulo', $modulo))
            ->when($canal, fn ($q) => $this->filtrarCanal($q, $canal))
            ->when($embarcacaoId, $scopeEmb)
            ->where('tipo_mov', '!=', 'saida');

        $canalSql = Pagamento::canalSql();
        $porMeio = (clone $base)->selectRaw("$canalSql as canal, SUM(valor) valor, COUNT(*) qtd")->groupBy('canal')->get()->keyBy('canal');
        $porOperador = (clone $base)->selectRaw("user_id, $canalSql as canal, SUM(valor) valor")->groupBy('user_id', 'canal')->get()
            ->groupBy('user_id')->map(fn ($rows) => [
                'total' => $rows->sum('valor'),
                'meios' => $rows->pluck('valor', 'canal'),
            ]);
        $porModulo = (clone $base)->selectRaw('modulo, SUM(valor) valor, COUNT(*) qtd')->groupBy('modulo')->get()->keyBy('modulo');

        // EM ABERTO (a receber) por operador: soma o "devido" das vendas do recorte.
        $emAberto = collect();
        $somarAberto = function ($query) use (&$emAberto) {
            foreach ($query->where('devido', '>', 0.009)->groupBy('user_id')->selectRaw('user_id, SUM(devido) as v')->get() as $r) {
                if ($r->user_id) $emAberto[$r->user_id] = round(($emAberto[$r->user_id] ?? 0) + (float) $r->v, 2);
            }
        };
        if ((! $modulo || $modulo === 'passagem') && $encIds === null) {
            $somarAberto(Passagem::whereBetween('created_at', [$inicio, $fim])->where('cancelada', false)
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->when($embarcacaoId, fn ($q) => $q->where('embarcacao_id', $embarcacaoId)));
        }
        if (! $modulo || $modulo === 'encomenda') {
            $somarAberto(Encomenda::when($encIds === null, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
                ->when($encIds !== null, fn ($q) => $q->whereIn('id', $encIds))
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->when($embarcacaoId, fn ($q) => $q->where('embarcacao_id', $embarcacaoId)));
        }

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

        $saidas = Pagamento::where('estornado', false)->where('tipo_mov', 'saida')
            ->when($encIds === null, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($encIds !== null, fn ($q) => $q->whereIn('origem_id', $encIds))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($modulo, fn ($q) => $q->where('modulo', $modulo))->sum('valor');

        $categorias = AvulsoCategoria::where('ativo', true)->orderBy('ordem')->orderBy('nome')->get();
        $podeAvulso = auth()->user()->podeModulo('avulso');

        $movimentos = (clone $base)->with(['user', 'encomenda'])->orderByDesc('id')->paginate(100)->withQueryString();

        return view('caixa.index', compact('porMeio', 'porModulo', 'porOperador', 'usuarios', 'descontos', 'entregas', 'cadastros', 'movimentos', 'inicio', 'fim', 'periodo', 'userId', 'viagens', 'viagemId', 'verTodos', 'modulo', 'saidas', 'categorias', 'podeAvulso', 'canal', 'embarcacoes', 'embarcacaoId', 'emAberto'))
            ->with(['meios' => Pagamento::CANAIS, 'entradas' => $porMeio->sum('valor'), 'total' => $porMeio->sum('valor') - $saidas]);
    }

    /** Aplica o filtro por canal (PIX dividido em manual/automático). */
    protected function filtrarCanal($q, string $canal)
    {
        return match ($canal) {
            'pix_manual' => $q->where('meio', 'pix')->where(fn ($w) => $w->where('pix_auto', false)->orWhereNull('pix_auto')),
            'pix_auto' => $q->where('meio', 'pix')->where('pix_auto', true),
            'dinheiro', 'debito', 'credito' => $q->where('meio', $canal),
            default => $q,
        };
    }

    /** Registra uma entrada ou saída avulsa no caixa. */
    public function avulso(Request $request)
    {
        abort_unless($request->user()->podeModulo('avulso'), 403, 'Sem permissão para lançar avulso.');
        $data = $request->validate([
            'tipo_mov' => 'required|in:entrada,saida',
            'categoria' => 'nullable|string|max:60',
            'valor' => 'required|numeric|min:0.01',
            'meio' => 'required|in:dinheiro,pix,debito,credito',
            'descricao' => 'nullable|string|max:200',
        ]);
        Pagamento::create([
            'empresa_id' => Tenant::id(),
            'modulo' => 'avulso',
            'origem_id' => null,
            'encomenda_id' => null,
            'meio' => $data['meio'],
            'valor' => $data['valor'],
            'user_id' => auth()->id(),
            'momento' => 'cadastro',
            'tipo_mov' => $data['tipo_mov'],
            'categoria' => $data['categoria'] ?? null,
            'descricao' => $data['descricao'] ?? null,
        ]);
        return back()->with('ok', ($data['tipo_mov'] === 'entrada' ? 'Entrada' : 'Saída') . ' avulsa registrada.');
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
