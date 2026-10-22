<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preço integral (referência) por trecho+tipo, separado do valor cobrado
 * (tarifa + alimentacao). Permite ex.: gratuito com integral 100 mas cobrando 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trecho_precos', function (Blueprint $t) {
            if (! Schema::hasColumn('trecho_precos', 'integral')) $t->decimal('integral', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('trecho_precos', 'integral')) {
            Schema::table('trecho_precos', fn (Blueprint $t) => $t->dropColumn('integral'));
        }
    }
};
