<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avulso_categorias', function (Blueprint $t) {
            if (! Schema::hasColumn('avulso_categorias', 'conta_acerto')) {
                $t->boolean('conta_acerto')->default(true);
            }
            if (! Schema::hasColumn('avulso_categorias', 'comissao_pct')) {
                $t->unsignedTinyInteger('comissao_pct')->default(0);
            }
        });
        Schema::table('pagamentos', function (Blueprint $t) {
            if (! Schema::hasColumn('pagamentos', 'acerto_conta')) {
                $t->boolean('acerto_conta')->default(true);
            }
            if (! Schema::hasColumn('pagamentos', 'acerto_pct')) {
                $t->unsignedTinyInteger('acerto_pct')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('avulso_categorias', function (Blueprint $t) {
            foreach (['conta_acerto', 'comissao_pct'] as $c) {
                if (Schema::hasColumn('avulso_categorias', $c)) $t->dropColumn($c);
            }
        });
        Schema::table('pagamentos', function (Blueprint $t) {
            foreach (['acerto_conta', 'acerto_pct'] as $c) {
                if (Schema::hasColumn('pagamentos', $c)) $t->dropColumn($c);
            }
        });
    }
};
