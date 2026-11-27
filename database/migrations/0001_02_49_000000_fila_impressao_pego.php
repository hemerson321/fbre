<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Trava da fila de impressão: marca quando um agente pegou o job, pra outro não imprimir o mesmo (saía em dobro). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fila_impressao') && ! Schema::hasColumn('fila_impressao', 'pego_em')) {
            Schema::table('fila_impressao', fn (Blueprint $t) => $t->timestamp('pego_em')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fila_impressao') && Schema::hasColumn('fila_impressao', 'pego_em')) {
            Schema::table('fila_impressao', fn (Blueprint $t) => $t->dropColumn('pego_em'));
        }
    }
};
