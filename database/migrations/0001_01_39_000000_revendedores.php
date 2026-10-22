<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revendedores (agentes) de passagem + acerto.
 * - users: é revendedor, revendedor-pai (vendedor do revendedor), modo de acerto.
 * - passagens: a quem pertence e status/valor de acerto (empresa↔revendedor e revendedor↔vendedor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'is_revendedor')) $t->boolean('is_revendedor')->default(false);
            if (! Schema::hasColumn('users', 'revendedor_id')) $t->unsignedBigInteger('revendedor_id')->nullable()->index();
            if (! Schema::hasColumn('users', 'modo_acerto')) $t->string('modo_acerto', 10)->default('cheio'); // comissao | cheio
            if (! Schema::hasColumn('users', 'comissao_pct')) $t->unsignedTinyInteger('comissao_pct')->default(0);
        });

        Schema::table('passagens', function (Blueprint $t) {
            if (! Schema::hasColumn('passagens', 'revendedor_id')) $t->unsignedBigInteger('revendedor_id')->nullable()->index();
            if (! Schema::hasColumn('passagens', 'acerto_empresa')) $t->boolean('acerto_empresa')->default(false);
            if (! Schema::hasColumn('passagens', 'acerto_empresa_valor')) $t->decimal('acerto_empresa_valor', 10, 2)->default(0);
            if (! Schema::hasColumn('passagens', 'acerto_empresa_em')) $t->timestamp('acerto_empresa_em')->nullable();
            if (! Schema::hasColumn('passagens', 'acerto_empresa_por')) $t->unsignedBigInteger('acerto_empresa_por')->nullable();
            if (! Schema::hasColumn('passagens', 'acerto_revendedor')) $t->boolean('acerto_revendedor')->default(false);
            if (! Schema::hasColumn('passagens', 'acerto_revendedor_valor')) $t->decimal('acerto_revendedor_valor', 10, 2)->default(0);
            if (! Schema::hasColumn('passagens', 'acerto_revendedor_em')) $t->timestamp('acerto_revendedor_em')->nullable();
            if (! Schema::hasColumn('passagens', 'acerto_revendedor_por')) $t->unsignedBigInteger('acerto_revendedor_por')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            foreach (['is_revendedor', 'revendedor_id', 'modo_acerto', 'comissao_pct'] as $c) {
                if (Schema::hasColumn('users', $c)) $t->dropColumn($c);
            }
        });
        Schema::table('passagens', function (Blueprint $t) {
            foreach (['revendedor_id', 'acerto_empresa', 'acerto_empresa_valor', 'acerto_empresa_em', 'acerto_empresa_por', 'acerto_revendedor', 'acerto_revendedor_valor', 'acerto_revendedor_em', 'acerto_revendedor_por'] as $c) {
                if (Schema::hasColumn('passagens', $c)) $t->dropColumn($c);
            }
        });
    }
};
