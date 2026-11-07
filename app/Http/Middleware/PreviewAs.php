<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * Simulacao: quando a requisicao traz ?preview_as=<id> E o usuario logado e admin/super,
 * renderiza/age COMO o usuario alvo (so neste request, sem mexer na sessao).
 * Usado pelos iframes do painel de simulacao para navegar/agir como cada papel.
 */
class PreviewAs
{
    public function handle(Request $request, Closure $next)
    {
        $pid = $request->input('preview_as');
        if ($pid) {
            $real = $request->user();
            if ($real && ($real->admin || $real->super_admin)) {
                $target = User::find($pid); // tenant-scoped (mesma empresa)
                if ($target && ! ($target->super_admin && ! $real->super_admin)) {
                    auth()->setUser($target);
                    $request->setUserResolver(fn () => $target);
                }
            }
        }
        return $next($request);
    }
}
