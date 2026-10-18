<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('caixa_todos')->default(false)->after('perm_caixa');       // vê caixa de todos (senão só o dele)
            $table->boolean('relatorio_todos')->default(false)->after('perm_relatorio'); // relatório de todos (senão só o dele)
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['caixa_todos', 'relatorio_todos']);
        });
    }
};
