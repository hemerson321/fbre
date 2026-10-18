<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Uso: ->middleware('perm:cadastra') | perm:baixa | perm:caixa | perm:admin | perm:super
 */
class Permissao
{
    public function handle(Request $request, Closure $next, string $perm)
    {
        $u = $request->user();
        $ok = match ($perm) {
            'cadastra' => $u->podeCadastrar(),
            'baixa'    => $u->podeBaixar(),
            'caixa'    => $u->podeVerCaixa(),
            'admin'    => $u->isAdmin() || $u->isSuper(),
            'super'    => $u->isSuper(),
            default    => false,
        };

        abort_unless($ok, 403, 'Você não tem permissão para esta ação.');

        return $next($request);
    }
}
