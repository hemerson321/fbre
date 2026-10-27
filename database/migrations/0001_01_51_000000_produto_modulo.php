<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Separa o catálogo de produtos por módulo (encomenda x frete). Os existentes ficam como 'encomenda'. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $t) {
            if (! Schema::hasColumn('produtos', 'modulo')) {
                $t->string('modulo', 12)->default('encomenda')->after('empresa_id');
                $t->index(['empresa_id', 'modulo', 'ativo']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $t) {
            if (Schema::hasColumn('produtos', 'modulo')) {
                try { $t->dropIndex(['empresa_id', 'modulo', 'ativo']); } catch (\Throwable $e) {}
                $t->dropColumn('modulo');
            }
        });
    }
};
