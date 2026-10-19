<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Largura do papel por estação (nº de colunas do ESC/POS):
 * 48 = 80mm (padrão), 32 = 58mm. O agente usa esse valor ao montar comprovante/etiqueta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('estacoes', 'colunas')) {
                $t->unsignedSmallInteger('colunas')->default(48)->after('nome');
            }
        });
    }

    public function down(): void
    {
        Schema::table('estacoes', function (Blueprint $t) {
            if (Schema::hasColumn('estacoes', 'colunas')) {
                $t->dropColumn('colunas');
            }
        });
    }
};
