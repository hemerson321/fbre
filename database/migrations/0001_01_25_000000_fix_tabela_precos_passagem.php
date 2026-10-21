<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tabela `tabela_precos` já existia (criada na 0001_01_05 com origem/destino/valor/tipo),
 * então a 0001_01_22 pulou a criação e ela ficou sem as colunas que o módulo Passagem usa.
 * Aqui garantimos as colunas que faltam. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tabela_precos', function (Blueprint $t) {
            if (! Schema::hasColumn('tabela_precos', 'passagem')) $t->decimal('passagem', 10, 2)->default(0);
            if (! Schema::hasColumn('tabela_precos', 'taxa_embarque')) $t->decimal('taxa_embarque', 10, 2)->default(0);
            if (! Schema::hasColumn('tabela_precos', 'ativo')) $t->boolean('ativo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('tabela_precos', function (Blueprint $t) {
            foreach (['passagem', 'taxa_embarque', 'ativo'] as $c) {
                if (Schema::hasColumn('tabela_precos', $c)) $t->dropColumn($c);
            }
        });
    }
};
