<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índice (empresa_id, created_at) em fretes — filtros por período ficam rápidos
 * em escala (encomendas/passagens/pagamentos já têm o equivalente).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fretes')) return;
        try {
            $existe = collect(DB::select('SHOW INDEX FROM fretes'))->contains(fn ($i) => $i->Key_name === 'fretes_empresa_id_created_at_index');
            if (! $existe) {
                Schema::table('fretes', fn ($t) => $t->index(['empresa_id', 'created_at']));
            }
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        try { Schema::table('fretes', fn ($t) => $t->dropIndex('fretes_empresa_id_created_at_index')); } catch (\Throwable $e) {}
    }
};
