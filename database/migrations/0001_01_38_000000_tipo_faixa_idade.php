<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Faixa etária por tipo de passageiro (ex.: criança 1 a 5, 5 a 10). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_passageiro', function (Blueprint $t) {
            if (! Schema::hasColumn('tipos_passageiro', 'idade_min')) $t->unsignedSmallInteger('idade_min')->nullable();
            if (! Schema::hasColumn('tipos_passageiro', 'idade_max')) $t->unsignedSmallInteger('idade_max')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tipos_passageiro', function (Blueprint $t) {
            foreach (['idade_min', 'idade_max'] as $c) {
                if (Schema::hasColumn('tipos_passageiro', $c)) $t->dropColumn($c);
            }
        });
    }
};
