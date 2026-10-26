<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distingue PIX Manual (valor digitado no caixa) de PIX Automático (QR gerado,
 * confirmado pelo Mercado Pago). Usado no form, no caixa, nos relatórios e no filtro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagamentos', function (Blueprint $t) {
            if (! Schema::hasColumn('pagamentos', 'pix_auto')) {
                $t->boolean('pix_auto')->default(false)->after('meio'); // true = PIX dinâmico (QR)
            }
        });
    }

    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $t) {
            if (Schema::hasColumn('pagamentos', 'pix_auto')) $t->dropColumn('pix_auto');
        });
    }
};
