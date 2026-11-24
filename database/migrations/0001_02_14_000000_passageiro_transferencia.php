<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Guarda de onde o passageiro veio quando muda de transporte (lancha, poltrona e bilhete anteriores) — sai na via do passageiro. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passagem_passageiros', function (Blueprint $t) {
            if (! Schema::hasColumn('passagem_passageiros', 'transf_de_lancha')) $t->string('transf_de_lancha', 120)->nullable();
            if (! Schema::hasColumn('passagem_passageiros', 'transf_de_poltrona')) $t->string('transf_de_poltrona', 12)->nullable();
            if (! Schema::hasColumn('passagem_passageiros', 'transf_de_bilhete')) $t->string('transf_de_bilhete', 40)->nullable();
            if (! Schema::hasColumn('passagem_passageiros', 'transf_em')) $t->timestamp('transf_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('passagem_passageiros', function (Blueprint $t) {
            foreach (['transf_de_lancha', 'transf_de_poltrona', 'transf_de_bilhete', 'transf_em'] as $c) {
                if (Schema::hasColumn('passagem_passageiros', $c)) $t->dropColumn($c);
            }
        });
    }
};
