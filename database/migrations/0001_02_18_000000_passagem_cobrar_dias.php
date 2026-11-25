<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passagens', function (Blueprint $t) {
            // Dias para lembrar de cobrar o faturado (padrão 30). Vencimento = created_at + cobrar_dias.
            if (! Schema::hasColumn('passagens', 'cobrar_dias')) {
                $t->unsignedSmallInteger('cobrar_dias')->nullable()->after('cliente_faturado_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('passagens', function (Blueprint $t) {
            if (Schema::hasColumn('passagens', 'cobrar_dias')) {
                $t->dropColumn('cobrar_dias');
            }
        });
    }
};
