<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Número oficial e definitivo da encomenda, como texto (ex.: RE-0001).
 * Gerado no aparelho no momento do cadastro e nunca reatribuído.
 *
 * Como `numero` passa a ser a sequência POR OPERADOR (pode repetir entre
 * operadores — RE-0001 e AG-0001 têm numero=1), a unicidade sai de
 * (empresa_id, numero) e passa para (empresa_id, numero_completo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encomendas', function (Blueprint $t) {
            if (! Schema::hasColumn('encomendas', 'numero_completo')) {
                $t->string('numero_completo', 30)->nullable()->after('numero');
            }
        });

        // backfill: encomendas antigas = número antigo com 6 dígitos (antes de criar o unique)
        DB::statement("UPDATE encomendas SET numero_completo = LPAD(CAST(numero AS CHAR), 6, '0') WHERE numero_completo IS NULL OR numero_completo = ''");

        // tira a unicidade antiga (empresa_id, numero) — numero agora repete entre operadores
        try {
            Schema::table('encomendas', fn (Blueprint $t) => $t->dropUnique('encomendas_empresa_id_numero_unique'));
        } catch (\Throwable $e) {
        }

        // nova unicidade e índice no número oficial
        try {
            Schema::table('encomendas', fn (Blueprint $t) => $t->unique(['empresa_id', 'numero_completo']));
        } catch (\Throwable $e) {
        }
        try {
            Schema::table('encomendas', fn (Blueprint $t) => $t->index('numero_completo'));
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        Schema::table('encomendas', function (Blueprint $t) {
            if (Schema::hasColumn('encomendas', 'numero_completo')) {
                try { $t->dropUnique(['empresa_id', 'numero_completo']); } catch (\Throwable $e) {}
                try { $t->dropIndex(['numero_completo']); } catch (\Throwable $e) {}
                $t->dropColumn('numero_completo');
            }
        });
    }
};
