<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Uso: ->middleware('perm:cadastra') | perm:baixa | perm:caixa | perm:admin | perm:super
 * Por módulo: ->middleware('perm:passagem') | perm:frete | perm:camarote | perm:lanchonete | perm:wifi | perm:avulso | perm:recibo
 */
class Permissao
{
    public function handle(Request $request, Closure $next, string $perm)
    {
        $u = $request->user();

        // formato por módulo+ação: perm:passagem.baixa
        if (str_contains($perm, '.')) {
            [$mod, $acao] = explode('.', $perm, 2);
            $ok = array_key_exists($mod, \App\Models\User::MODULOS)
                && ($acao === 'ver' ? $u->temModulo($mod) : $u->permite($mod, $acao));
            abort_unless($ok, 403, 'Você não tem permissão para esta ação.');
            return $next($request);
        }
        $ok = match ($perm) {
            'cadastra' => $u->podeCadastrar(),
            'baixa'    => $u->podeBaixar(),
            'caixa'    => $u->podeVerCaixa(),
            'filtro'   => $u->podeFiltrar(),
            'relatorio'=> $u->podeRelatorio(),
            'imprimir' => $u->podeImprimir(),
            'admin'    => $u->isAdmin() || $u->isSuper(),
            'super'    => $u->isSuper(),
            default    => array_key_exists($perm, \App\Models\User::MODULOS) ? $u->podeModulo($perm) : false,
        };

        abort_unless($ok, 403, 'Você não tem permissão para esta ação.');

        return $next($request);
    }
}
