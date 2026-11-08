<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('embarcacoes', 'hora_saida')) {
                $t->time('hora_saida')->nullable()->after('comissao_empresa_pct');
            }
        });
    }

    public function down(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (Schema::hasColumn('embarcacoes', 'hora_saida')) {
                $t->dropColumn('hora_saida');
            }
        });
    }
};
