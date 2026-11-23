<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conserto: as colunas de viagem foram adicionadas à 0001_02_10 DEPOIS que ela já
 * havia rodado em alguns ambientes, então não foram criadas. Esta migração guarda
 * cada coluna com Schema::hasColumn e cria só o que faltar (idempotente).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prestacoes')) {
            if (! Schema::hasColumn('prestacoes', 'viagem_id')) {
                Schema::table('prestacoes', fn (Blueprint $t) => $t->unsignedBigInteger('viagem_id')->nullable()->index());
            }
            if (! Schema::hasColumn('prestacoes', 'data_viagem')) {
                Schema::table('prestacoes', fn (Blueprint $t) => $t->date('data_viagem')->nullable()->index());
            }
        }
        if (! Schema::hasColumn('pagamentos', 'viagem_id')) {
            Schema::table('pagamentos', fn (Blueprint $t) => $t->unsignedBigInteger('viagem_id')->nullable()->index());
        }
        if (! Schema::hasColumn('pagamentos', 'data_viagem')) {
            Schema::table('pagamentos', fn (Blueprint $t) => $t->date('data_viagem')->nullable()->index());
        }
        if (! Schema::hasColumn('pagamentos', 'prestacao_id')) {
            Schema::table('pagamentos', fn (Blueprint $t) => $t->unsignedBigInteger('prestacao_id')->nullable()->index());
        }
        if (! Schema::hasColumn('passagens', 'prestacao_id')) {
            Schema::table('passagens', fn (Blueprint $t) => $t->unsignedBigInteger('prestacao_id')->nullable()->index());
        }
    }

    public function down(): void
    {
        // sem rollback destrutivo (colunas podem já existir de 0001_02_10)
    }
};
