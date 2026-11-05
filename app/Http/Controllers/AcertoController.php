<?php

namespace App\Http\Controllers;

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
    // ---------- ADMIN: acerto da empresa com os revendedores ----------
    public function index(Request $request)
    {
        [$inicio, $fim, $periodo] = CaixaController::periodo($request);

        // Agentes externos (revendedores): devem repassar das passagens vendidas (nao depende do periodo).
        $linhas = User::where('is_revendedor', true)->orderBy('name')->get()->map(function ($r) {
            $base = Passagem::where('revendedor_id', $r->id)->where('cancelada', false);
            $pend = (clone $base)->where('acerto_empresa', false);
            return [
                'rev' => $r,
                'qtd' => (clone $pend)->count(),
                'total' => (float) (clone $pend)->sum('acerto_empresa_valor'),
                'acertado' => (float) (clone $base)->where('acerto_empresa', true)->sum('acerto_empresa_valor'),
            ];
        });

        // Operadores de balcao (nao revendedores): conferir/validar o caixa do periodo.
        $ops = User::where('empresa_id', Tenant::id())->where('is_revendedor', false)->orderBy('name')->get()
            ->map(function ($o) use ($inicio, $fim) {
                $base = Pagamento::where('user_id', $o->id)->where('estornado', false)
                    ->where('tipo_mov', '!=', 'saida')->where('meio', '!=', 'desconto')
                    ->whereBetween('created_at', [$inicio, $fim]);
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

        return view('acertos.index', compact('linhas', 'ops', 'inicio', 'fim', 'periodo'));
    }

    /** ADMIN confere/valida o caixa de um operador de balcao no periodo mostrado. */
    public function baixaOperador(Request $request, User $operador)
    {
        abort_unless($operador->empresa_id === Tenant::id(), 404);
        [$inicio, $fim] = CaixaController::periodo($request);
        $n = Pagamento::where('user_id', $operador->id)->where('estornado', false)
            ->where('tipo_mov', '!=', 'saida')->where('conferido', false)
            ->whereBetween('created_at', [$inicio, $fim])
            ->update(['conferido' => true, 'conferido_em' => now(), 'conferido_por' => auth()->id()]);
        return back()->with('ok', 'Caixa de '.$operador->name.' conferido ('.$n.' lançamentos).');
    }

    public function baixaEmpresa(Request $request, User $revendedor)
    {
        abort_unless($revendedor->empresa_id === Tenant::id(), 404);
        Passagem::where('revendedor_id', $revendedor->id)->where('cancelada', false)->where('acerto_empresa', false)
            ->update(['acerto_empresa' => true, 'acerto_empresa_em' => now(), 'acerto_empresa_por' => auth()->id()]);
        return back()->with('ok', 'Acerto de '.$revendedor->name.' baixado.');
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
