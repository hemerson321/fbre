<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil FINANCEIRO: só entra no Caixa e Relatório, mas vê TODOS os
 * operadores e TODOS os módulos (com filtro por módulo/operador/período).
 * Implica caixa_todos + relatorio_todos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'perm_financeiro')) $t->boolean('perm_financeiro')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'perm_financeiro')) $t->dropColumn('perm_financeiro');
        });
    }
};
