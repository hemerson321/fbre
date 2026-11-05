<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('embarcacoes', 'dono_id')) {
                $t->unsignedBigInteger('dono_id')->nullable();
            }
            if (! Schema::hasColumn('embarcacoes', 'comissao_empresa_pct')) {
                $t->unsignedTinyInteger('comissao_empresa_pct')->default(0); // % que a empresa fica; dono recebe o resto
            }
        });
        Schema::table('passagens', function (Blueprint $t) {
            if (! Schema::hasColumn('passagens', 'acerto_dono')) {
                $t->boolean('acerto_dono')->default(false)->index();
            }
            if (! Schema::hasColumn('passagens', 'acerto_dono_em')) {
                $t->timestamp('acerto_dono_em')->nullable();
            }
            if (! Schema::hasColumn('passagens', 'acerto_dono_por')) {
                $t->unsignedBigInteger('acerto_dono_por')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            foreach (['dono_id', 'comissao_empresa_pct'] as $c) {
                if (Schema::hasColumn('embarcacoes', $c)) $t->dropColumn($c);
            }
        });
        Schema::table('passagens', function (Blueprint $t) {
            foreach (['acerto_dono', 'acerto_dono_em', 'acerto_dono_por'] as $c) {
                if (Schema::hasColumn('passagens', $c)) $t->dropColumn($c);
            }
        });
    }
};
