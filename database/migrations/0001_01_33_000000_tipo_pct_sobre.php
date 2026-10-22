<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Para tipos "Porcentagem": a % desconta só o TRANSPORTE ('tarifa') ou TUDO ('tudo').
 * Ex.: idoso meia = 50% só no transporte; alimentação e taxa continuam cheias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_passageiro', function (Blueprint $t) {
            if (! Schema::hasColumn('tipos_passageiro', 'pct_sobre')) $t->string('pct_sobre', 10)->default('tarifa');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('tipos_passageiro', 'pct_sobre')) {
            Schema::table('tipos_passageiro', fn (Blueprint $t) => $t->dropColumn('pct_sobre'));
        }
    }
};
