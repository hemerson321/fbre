<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Define a empresa ativa a partir do usuário logado.
 * Super-admin pode "entrar" em outra empresa via sessão (empresa_ativa).
 */
class DefinirEmpresa
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user) {
            $empresaId = $user->empresa_id;

            if ($user->super_admin && $request->session()->has('empresa_ativa')) {
                $empresaId = (int) $request->session()->get('empresa_ativa');
            }

            Tenant::set($empresaId);

            // bloqueio por inatividade/vencimento (super-admin nunca é bloqueado)
            $empresa = Tenant::empresa();
            if (! $user->super_admin && $empresa && $empresa->bloqueada() && ! $request->is('bloqueado', 'logout')) {
                return redirect('/bloqueado');
            }

            if (! $user->ativo) {
                Auth::logout();
                return redirect('/login')->withErrors(['email' => 'Usuário desativado.']);
            }
        }

        return $next($request);
    }
}
