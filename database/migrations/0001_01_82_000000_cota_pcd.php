<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Cota de PCD por viagem — mesma ideia da gratuidade, cota própria. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_passageiro', function (Blueprint $t) {
            if (! Schema::hasColumn('tipos_passageiro', 'pcd')) $t->boolean('pcd')->default(false);
        });
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('embarcacoes', 'pcd_qtd')) $t->unsignedSmallInteger('pcd_qtd')->nullable();
        });
        DB::table('tipos_passageiro')->whereRaw("LOWER(nome) LIKE '%pcd%' OR LOWER(nome) LIKE '%defici%' OR LOWER(nome) LIKE '%cadeir%'")->update(['pcd' => true]);
    }

    public function down(): void
    {
        Schema::table('tipos_passageiro', function (Blueprint $t) {
            if (Schema::hasColumn('tipos_passageiro', 'pcd')) $t->dropColumn('pcd');
        });
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (Schema::hasColumn('embarcacoes', 'pcd_qtd')) $t->dropColumn('pcd_qtd');
        });
    }
};
