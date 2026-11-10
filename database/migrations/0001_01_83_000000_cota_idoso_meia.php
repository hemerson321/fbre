<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Cota de idoso 50% (meia) por viagem — separada da gratuidade do idoso. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_passageiro', function (Blueprint $t) {
            if (! Schema::hasColumn('tipos_passageiro', 'idoso_meia')) $t->boolean('idoso_meia')->default(false);
        });
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('embarcacoes', 'idoso_meia_qtd')) $t->unsignedSmallInteger('idoso_meia_qtd')->nullable();
        });
        // auto-marca tipos de idoso que NÃO são gratuidade (ex.: "Idoso Normal (50%)")
        DB::table('tipos_passageiro')->whereRaw("LOWER(nome) LIKE '%idoso%' AND LOWER(nome) NOT LIKE '%gratu%'")->update(['idoso_meia' => true]);
    }

    public function down(): void
    {
        Schema::table('tipos_passageiro', function (Blueprint $t) {
            if (Schema::hasColumn('tipos_passageiro', 'idoso_meia')) $t->dropColumn('idoso_meia');
        });
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (Schema::hasColumn('embarcacoes', 'idoso_meia_qtd')) $t->dropColumn('idoso_meia_qtd');
        });
    }
};
