<?php

namespace App\Http\Controllers;

use App\Models\Embarcacao;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Tela de SIMULACAO (demo): o admin entra como um usuario de cada papel
 * (Administrador, Operador, Agente, Dono) para demonstrar as telas ao cliente.
 * Guarda o admin original na sessao para poder voltar.
 */
class SimulacaoController extends Controller
{
    protected function podeSimular(): bool
    {
        $u = auth()->user();
        return $u && ($u->isAdmin() || $u->isSuper());
    }

    public function index()
    {
        abort_unless($this->podeSimular() || session('simula_admin_id'), 403);
        // se ja estou simulando, so o admin original pode reabrir; redireciona pra sair
        if (session('simula_admin_id')) {
            return redirect()->route('inicio');
        }

        $donoIds = Embarcacao::whereNotNull('dono_id')->pluck('dono_id')->map(fn ($i) => (int) $i)->unique()->all();

        $admins = User::where('ativo', true)->where(fn ($q) => $q->where('admin', true)->orWhere('super_admin', true))->orderBy('name')->get();
        $operadores = User::where('ativo', true)->where('is_revendedor', false)->where('admin', false)->where('super_admin', false)
            ->whereNotIn('id', $donoIds)->orderBy('name')->get();
        $agentes = User::where('ativo', true)->where('is_revendedor', true)->orderBy('name')->get();
        $donos = $donoIds ? User::where('ativo', true)->whereIn('id', $donoIds)->orderBy('name')->get() : collect();

        return view('simulacao.index', compact('admins', 'operadores', 'agentes', 'donos'));
    }

    public function entrar(Request $request)
    {
        abort_unless($this->podeSimular(), 403);
        $data = $request->validate(['user_id' => 'required|integer']);
        $alvo = User::find($data['user_id']); // tenant-scoped (PertenceEmpresa)
        abort_unless($alvo, 404);

        $atual = auth()->user();
        // um admin comum nao pode simular um super admin
        abort_if($alvo->super_admin && ! $atual->isSuper(), 403, 'Sem permissão para simular um super admin.');

        // guarda o admin original so na 1a vez (nao sobrescreve se ja estiver simulando)
        if (! session('simula_admin_id')) {
            session(['simula_admin_id' => $atual->id]);
        }
        Auth::loginUsingId($alvo->id);
        return redirect()->route('inicio')->with('ok', 'Simulando como '.$alvo->name.'.');
    }

    /** Painel com as 4 telas ao mesmo tempo (2 desktop + 2 celular). */
    public function telas()
    {
        abort_unless($this->podeSimular(), 403);
        return view('simulacao.telas');
    }

    /** Renderiza a home do sistema COMO o usuario alvo (so para o iframe do painel). Nao mexe na sessao. */
    public function tela(Request $request, User $user)
    {
        $real = $request->user();
        abort_unless($real && ($real->isAdmin() || $real->isSuper()), 403);
        abort_if($user->super_admin && ! $real->isSuper(), 403);
        // fixa o usuario alvo so para este request (nao persiste na sessao)
        $request->setUserResolver(fn () => $user);
        auth()->setUser($user);
        return response(view('home', ['simFrame' => true]))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function sair(Request $request)
    {
        $adminId = session('simula_admin_id');
        if ($adminId) {
            session()->forget('simula_admin_id');
            Auth::loginUsingId((int) $adminId);
            return redirect()->route('simulacao')->with('ok', 'Você voltou ao seu usuário.');
        }
        return redirect()->route('inicio');
    }
}
