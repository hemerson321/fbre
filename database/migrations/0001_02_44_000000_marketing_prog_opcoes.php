<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mais opções de programação do áudio: janela (hora fim), repetir a cada X min, tocar N vezes com intervalo. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_programacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('marketing_programacoes', 'hora_fim')) $t->string('hora_fim', 5)->nullable();          // fim da janela "HH:MM"
            if (! Schema::hasColumn('marketing_programacoes', 'repetir_min')) $t->unsignedSmallInteger('repetir_min')->nullable(); // dentro da janela, a cada X min
            if (! Schema::hasColumn('marketing_programacoes', 'vezes')) $t->unsignedSmallInteger('vezes')->default(1);     // quantas vezes toca em cada disparo
            if (! Schema::hasColumn('marketing_programacoes', 'intervalo_seg')) $t->unsignedSmallInteger('intervalo_seg')->default(5); // segundos entre as repetições
        });
    }

    public function down(): void
    {
        Schema::table('marketing_programacoes', function (Blueprint $t) {
            foreach (['hora_fim', 'repetir_min', 'vezes', 'intervalo_seg'] as $c) {
                if (Schema::hasColumn('marketing_programacoes', $c)) $t->dropColumn($c);
            }
        });
    }
};
