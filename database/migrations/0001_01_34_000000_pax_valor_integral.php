<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda o preço NORMAL (integral) de referência de cada passageiro,
 * pra respaldar no comprovante quando há desconto (ex.: idoso/gratuito).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passagem_passageiros', function (Blueprint $t) {
            if (! Schema::hasColumn('passagem_passageiros', 'valor_integral')) $t->decimal('valor_integral', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('passagem_passageiros', 'valor_integral')) {
            Schema::table('passagem_passageiros', fn (Blueprint $t) => $t->dropColumn('valor_integral'));
        }
    }
};
