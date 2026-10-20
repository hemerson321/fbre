<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Número oficial e definitivo da encomenda, como texto (ex.: RE-0001).
 * Gerado no aparelho no momento do cadastro e nunca reatribuído.
 * Encomendas antigas recebem o número antigo preenchido (000012).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encomendas', function (Blueprint $t) {
            if (! Schema::hasColumn('encomendas', 'numero_completo')) {
                $t->string('numero_completo', 30)->nullable()->after('numero');
                $t->index('numero_completo');
            }
        });

        // backfill: encomendas antigas = número antigo com 6 dígitos
        DB::statement("UPDATE encomendas SET numero_completo = LPAD(CAST(numero AS CHAR), 6, '0') WHERE numero_completo IS NULL OR numero_completo = ''");
    }

    public function down(): void
    {
        Schema::table('encomendas', function (Blueprint $t) {
            if (Schema::hasColumn('encomendas', 'numero_completo')) {
                $t->dropIndex(['numero_completo']);
                $t->dropColumn('numero_completo');
            }
        });
    }
};
