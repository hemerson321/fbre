<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taxa de servico e Alimentacao sao coisas separadas na passagem.
 * - tabela_precos ja tem taxa_embarque (vira "Taxa de servico"); adiciona alimentacao.
 * - passagem_passageiros ganha valor_alimentacao.
 * - passagens ganha alimentacao_total.
 * Opcional por empresa: config.usa_taxa_servico / config.usa_alimentacao.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tabela_precos', function (Blueprint $t) {
            if (! Schema::hasColumn('tabela_precos', 'alimentacao')) $t->decimal('alimentacao', 10, 2)->default(0);
        });
        Schema::table('passagem_passageiros', function (Blueprint $t) {
            if (! Schema::hasColumn('passagem_passageiros', 'valor_alimentacao')) $t->decimal('valor_alimentacao', 10, 2)->default(0);
        });
        Schema::table('passagens', function (Blueprint $t) {
            if (! Schema::hasColumn('passagens', 'alimentacao_total')) $t->decimal('alimentacao_total', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('tabela_precos', 'alimentacao')) Schema::table('tabela_precos', fn (Blueprint $t) => $t->dropColumn('alimentacao'));
        if (Schema::hasColumn('passagem_passageiros', 'valor_alimentacao')) Schema::table('passagem_passageiros', fn (Blueprint $t) => $t->dropColumn('valor_alimentacao'));
        if (Schema::hasColumn('passagens', 'alimentacao_total')) Schema::table('passagens', fn (Blueprint $t) => $t->dropColumn('alimentacao_total'));
    }
};
