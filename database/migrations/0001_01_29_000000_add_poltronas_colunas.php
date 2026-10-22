<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Assentos por fileira da embarcação (ex.: 4 = 2+2 com corredor no meio). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('embarcacoes', 'poltronas_colunas')) $t->unsignedTinyInteger('poltronas_colunas')->default(4);
        });
    }

    public function down(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (Schema::hasColumn('embarcacoes', 'poltronas_colunas')) $t->dropColumn('poltronas_colunas');
        });
    }
};
