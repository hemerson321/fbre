<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Troca a unicidade da encomenda: de (empresa_id, numero) para
 * (empresa_id, numero_completo). Necessária porque `numero` virou a
 * sequência POR OPERADOR (RE-0001 e AG-0001 têm numero=1) e não é mais
 * único por empresa — a trava antiga causava "Duplicate entry 1062" no sync.
 * Idempotente: pode já ter sido feita na 0001_01_18 em instalação nova.
 */
return new class extends Migration
{
    public function up(): void
    {
        // garante numero_completo preenchido antes do unique
        DB::statement("UPDATE encomendas SET numero_completo = LPAD(CAST(numero AS CHAR), 6, '0') WHERE numero_completo IS NULL OR numero_completo = ''");

        try {
            Schema::table('encomendas', fn (Blueprint $t) => $t->dropUnique('encomendas_empresa_id_numero_unique'));
        } catch (\Throwable $e) {
        }
        try {
            Schema::table('encomendas', fn (Blueprint $t) => $t->unique(['empresa_id', 'numero_completo']));
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        try {
            Schema::table('encomendas', fn (Blueprint $t) => $t->dropUnique(['empresa_id', 'numero_completo']));
        } catch (\Throwable $e) {
        }
    }
};
