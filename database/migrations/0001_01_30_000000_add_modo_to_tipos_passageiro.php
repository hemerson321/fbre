<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de passageiro pode ser por PORCENTAGEM (% da tarifa) ou VALOR fixo.
 * (mesma regra do sistema antigo: modo 'porcentagem' | 'valor')
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_passageiro', function (Blueprint $t) {
            if (! Schema::hasColumn('tipos_passageiro', 'modo')) $t->string('modo', 12)->default('porcentagem');
            if (! Schema::hasColumn('tipos_passageiro', 'valor_fixo')) $t->decimal('valor_fixo', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('tipos_passageiro', function (Blueprint $t) {
            foreach (['modo', 'valor_fixo'] as $c) {
                if (Schema::hasColumn('tipos_passageiro', $c)) $t->dropColumn($c);
            }
        });
    }
};
