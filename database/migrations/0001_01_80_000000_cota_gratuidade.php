<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cota de gratuidade por viagem (ex.: idoso gratuito — Estatuto do Idoso).
 * - tipos_passageiro.gratuito: marca quais tipos contam na cota.
 * - embarcacoes.gratuito_qtd: cota própria do transporte (null = herda da empresa).
 * - A cota padrão da empresa fica em config('gratuito_qtd').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_passageiro', function (Blueprint $t) {
            if (! Schema::hasColumn('tipos_passageiro', 'gratuito')) $t->boolean('gratuito')->default(false);
        });
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('embarcacoes', 'gratuito_qtd')) $t->unsignedSmallInteger('gratuito_qtd')->nullable();
        });
        // auto-marca tipos cujo nome indica gratuidade
        DB::table('tipos_passageiro')->whereRaw("LOWER(nome) LIKE '%gratu%' OR LOWER(nome) LIKE '%cortesia%'")->update(['gratuito' => true]);
    }

    public function down(): void
    {
        Schema::table('tipos_passageiro', function (Blueprint $t) {
            if (Schema::hasColumn('tipos_passageiro', 'gratuito')) $t->dropColumn('gratuito');
        });
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (Schema::hasColumn('embarcacoes', 'gratuito_qtd')) $t->dropColumn('gratuito_qtd');
        });
    }
};
