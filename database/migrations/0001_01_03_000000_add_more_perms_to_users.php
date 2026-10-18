<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('perm_filtro')->default(true)->after('perm_cadastra');     // ver lista/filtro de encomendas
            $table->boolean('perm_relatorio')->default(true)->after('perm_caixa');    // meu relatório
            $table->boolean('perm_imprimir')->default(true)->after('perm_relatorio'); // reimprimir comprovantes/etiquetas
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['perm_filtro', 'perm_relatorio', 'perm_imprimir']);
        });
    }
};
