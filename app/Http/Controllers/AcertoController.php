<?php

namespace App\Http\Controllers;

use App\Models\Encomenda;
use App\Models\Pagamento;
use App\Models\Passagem;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Acerto: empresa ↔ revendedores (admin) e revendedor ↔ filiados (revendedor).
 */
class AcertoController extends Controller
{
    // ---------- ADMIN: acerto da empresa (operadores de balcão + agentes externos) ----------
    public function index(Request $request)
    {
        abort_unless(auth()->user()->podeAcerto(), 403);
        [$inicio, $fim, $periodo] = CaixaController::periodo($request);

        $transporteId = $request->input('transporte_id') ?: null;
        $viagemId = $request->input('viagem_id') ?: null;
        $opFiltro = $request->input('user_id') ?: null;

        $transportes = \App\Models\Embarcacao::orderBy('nome')->get();
        $viagens = Tenant::empresa()?->temRecurso('viagens')
            ? \App\Models\Viagem::orderByDesc('data_partida')->limit(60)->get() : collect();
        $pessoas = User::where('empresa_id', Tenant::id())->orderBy('name')->get();

        $ft = $this->filtraTransp($transporteId);
        $fv = $this->filtraViagem($viagemId);

        // Agentes externos (revendedores): devem repassar das passagens vendidas.
        $linhas = User::where('is_revendedor', true)
            ->when($opFiltro, fn ($q) => $q->where('id', $opFiltro))
            ->orderBy('name')->get()->map(function ($r) use ($transporteId, $viagemId) {
                $base = Passagem::where('revendedor_id', $r->id)->where('cancelada', false)
                    ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
                    ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId));
                $pend = (clone $base)->where('acerto_empresa', false);
                return [
                    'rev' => $r,
                    'qtd' => (clone $pend)->count(),
                    'total' => (float) (clone $pend)->sum('acerto_empresa_valor'),
                    'acertado' => (float) (clone $base)->where('acerto_empresa', true)->sum('acerto_empresa_valor'),
                ];
            });

        // Operadores de balcão (não revendedores): conferir/validar o caixa do período.
        $ops = User::where('empresa_id', Tenant::id())->where('is_revendedor', false)
            ->when($opFiltro, fn ($q) => $q->where('id', $opFiltro))
            ->orderBy('name')->get()
            ->map(function ($o) use ($inicio, $fim, $transporteId, $viagemId, $ft, $fv) {
                $base = Pagamento::where('user_id', $o->id)->where('estornado', false)
                    ->where('tipo_mov', '!=', 'saida')->where('meio', '!=', 'desconto')
                    ->whereBetween('created_at', [$inicio, $fim])
                    ->when($transporteId, $ft)->when($viagemId, $fv);
                $pend = (clone $base)->where('conferido', false);
                return [
                    'op' => $o,
                    'qtd' => (clone $pend)->count(),
                    'dinheiro' => (float) (clone $pend)->where('meio', 'dinheiro')->sum('valor'),
                    'pix' => (float) (clone $pend)->where('meio', 'pix')->sum('valor'),
                    'total' => (float) (clone $pend)->sum('valor'),
                    'conferido' => (float) (clone $base)->where('conferido', true)->sum('valor'),
                ];
            })->filter(fn ($l) => $l['qtd'] > 0 || $l['conferido'] > 0)->values();

        return view('acertos.index', compact(
            'linhas', 'ops', 'inicio', 'fim', 'periodo',
            'transportes', 'viagens', 'pessoas', 'transporteId', 'viagemId', 'opFiltro'
        ));
    }

    public function baixaEmpresa(Request $request, User $revendedor)
    {
        abort_unless(auth()->user()->podeAcerto(), 403);
        abort_unless($revendedor->empresa_id === Tenant::id(), 404);
        $transporteId = $request->input('transporte_id') ?: null;
        $viagemId = $request->input('viagem_id') ?: null;
        $n = Passagem::where('revendedor_id', $revendedor->id)->where('cancelada', false)->where('acerto_empresa', false)
            ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->update(['acerto_empresa' => true, 'acerto_empresa_em' => now(), 'acerto_empresa_por' => auth()->id()]);
        return back()->with('ok', 'Acerto de '.$revendedor->name.' baixado ('.$n.' passagens).');
    }

    /** ADMIN confere/valida o caixa de um operador de balcão no período + filtros mostrados. */
    public function baixaOperador(Request $request, User $operador)
    {
        abort_unless(auth()->user()->podeAcerto(), 403);
        abort_unless($operador->empresa_id === Tenant::id(), 404);
        [$inicio, $fim] = CaixaController::periodo($request);
        $transporteId = $request->input('transporte_id') ?: null;
        $viagemId = $request->input('viagem_id') ?: null;
        $n = Pagamento::where('user_id', $operador->id)->where('estornado', false)
            ->where('tipo_mov', '!=', 'saida')->where('conferido', false)
            ->whereBetween('created_at', [$inicio, $fim])
            ->when($transporteId, $this->filtraTransp($transporteId))
            ->when($viagemId, $this->filtraViagem($viagemId))
            ->update(['conferido' => true, 'conferido_em' => now(), 'conferido_por' => auth()->id()]);
        return back()->with('ok', 'Caixa de '.$operador->name.' conferido ('.$n.' lançamentos).');
    }

    /** ADMIN confere o caixa de TODOS os operadores no período + filtros mostrados. */
    public function baixaOperadoresTodos(Request $request)
    {
        abort_unless(auth()->user()->podeAcerto(), 403);
        [$inicio, $fim] = CaixaController::periodo($request);
        $transporteId = $request->input('transporte_id') ?: null;
        $viagemId = $request->input('viagem_id') ?: null;
        $opIds = User::where('empresa_id', Tenant::id())->where('is_revendedor', false)
            ->when($request->input('user_id'), fn ($q) => $q->where('id', $request->input('user_id')))->select('id');
        $n = Pagamento::where('estornado', false)->where('tipo_mov', '!=', 'saida')->where('conferido', false)
            ->whereIn('user_id', $opIds)->whereBetween('created_at', [$inicio, $fim])
            ->when($transporteId, $this->filtraTransp($transporteId))
            ->when($viagemId, $this->filtraViagem($viagemId))
            ->update(['conferido' => true, 'conferido_em' => now(), 'conferido_por' => auth()->id()]);
        return back()->with('ok', 'Caixa de todos os operadores conferido ('.$n.' lançamentos).');
    }

    /** ADMIN dá baixa no acerto de TODOS os agentes externos (com filtros transporte/viagem). */
    public function baixaAgentesTodos(Request $request)
    {
        abort_unless(auth()->user()->podeAcerto(), 403);
        $transporteId = $request->input('transporte_id') ?: null;
        $viagemId = $request->input('viagem_id') ?: null;
        $revIds = User::where('empresa_id', Tenant::id())->where('is_revendedor', true)
            ->when($request->input('user_id'), fn ($q) => $q->where('id', $request->input('user_id')))->pluck('id');
        $n = Passagem::whereIn('revendedor_id', $revIds)->where('cancelada', false)->where('acerto_empresa', false)
            ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->update(['acerto_empresa' => true, 'acerto_empresa_em' => now(), 'acerto_empresa_por' => auth()->id()]);
        return back()->with('ok', 'Baixa feita em todos os agentes ('.$n.' passagens).');
    }

    /** Pagamento (passagem/encomenda) ligado a uma embarcação. */
    protected function filtraTransp(?int $t): \Closure
    {
        return function ($q) use ($t) {
            $q->where(function ($w) use ($t) {
                $w->where(fn ($x) => $x->where('modulo', 'passagem')->whereIn('origem_id', Passagem::where('embarcacao_id', $t)->select('id')))
                  ->orWhere(fn ($x) => $x->where('modulo', 'encomenda')->whereIn('encomenda_id', Encomenda::where('embarcacao_id', $t)->select('id')));
            });
        };
    }

    /** Pagamento (passagem/encomenda) ligado a uma viagem. */
    protected function filtraViagem($v): \Closure
    {
        return function ($q) use ($v) {
            $q->where(function ($w) use ($v) {
                $w->where(fn ($x) => $x->where('modulo', 'passagem')->whereIn('origem_id', Passagem::where('viagem_id', $v)->select('id')))
                  ->orWhere(fn ($x) => $x->where('modulo', 'encomenda')->whereIn('encomenda_id', Encomenda::where('viagem_id', $v)->select('id')));
            });
        };
    }

    // ---------- REVENDEDOR: meus filiados + acerto com eles ----------
    public function meus()
    {
        $u = auth()->user();
        abort_unless($u->ehRevendedor(), 403);
        $filiados = User::where('revendedor_id', $u->id)->orderBy('name')->get();
        $linhas = $filiados->map(function ($f) {
            $base = Passagem::where('user_id', $f->id)->where('cancelada', false);
            $pend = (clone $base)->where('acerto_revendedor', false);
            return [
                'fil' => $f,
                'qtd' => (clone $pend)->count(),
                'total' => (float) (clone $pend)->sum('acerto_revendedor_valor'),
                'acertado' => (float) (clone $base)->where('acerto_revendedor', true)->sum('acerto_revendedor_valor'),
            ];
        });
        return view('acertos.meus', ['rev' => $u, 'filiados' => $filiados, 'linhas' => $linhas]);
    }

    public function baixaFiliado(Request $request, User $filiado)
    {
        $u = auth()->user();
        abort_unless($u->ehRevendedor() && $filiado->revendedor_id === $u->id, 403);
        Passagem::where('user_id', $filiado->id)->where('cancelada', false)->where('acerto_revendedor', false)
            ->update(['acerto_revendedor' => true, 'acerto_revendedor_em' => now(), 'acerto_revendedor_por' => auth()->id()]);
        return back()->with('ok', 'Acerto do filiado baixado.');
    }

    /** Revendedor cria/edita um filiado (vendedor vinculado a ele). */
    public function salvarFiliado(Request $request, ?User $filiado = null)
    {
        $u = auth()->user();
        abort_unless($u->ehRevendedor(), 403);
        if ($filiado && $filiado->exists) abort_unless($filiado->revendedor_id === $u->id, 404);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'login' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9._-]+$/'],
            'password' => ($filiado && $filiado->exists) ? 'nullable|string|min:4' : 'required|string|min:4',
            'prefixo' => ['required', 'string', 'max:8', 'regex:/^[A-Za-z0-9]+$/'],
            'modo_acerto' => 'required|in:comissao,cheio',
            'comissao_pct' => 'nullable|integer|min:0|max:100',
            'ativo' => 'nullable|boolean',
        ]);

        $payload = [
            'name' => $data['name'],
            'login' => $data['login'],
            'prefixo' => strtoupper($data['prefixo']),
            'revendedor_id' => $u->id,
            'is_revendedor' => false,
            'modo_acerto' => $data['modo_acerto'],
            'comissao_pct' => (int) ($data['comissao_pct'] ?? 0),
            'ativo' => $request->boolean('ativo', true),
            'empresa_id' => $u->empresa_id,
            'mod_perms' => ['passagem' => ['cadastra' => true, 'imprimir' => true]],
        ];
        if (! empty($data['password'])) $payload['password'] = Hash::make($data['password']);

        if ($filiado && $filiado->exists) {
            $filiado->update($payload);
            return back()->with('ok', 'Filiado atualizado.');
        }
        User::create($payload + ['proximo_seq' => 1]);
        return back()->with('ok', 'Filiado criado.');
    }
}
