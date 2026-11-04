<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'pode_desconto')) {
                // operador pode dar desconto (baixar o valor) na venda; revendedor nunca
                $t->boolean('pode_desconto')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'pode_desconto')) {
                $t->dropColumn('pode_desconto');
            }
        });
    }
};
