<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'estacao_id')) {
                // impressora (estação) deste operador — usada na impressão direta
                $t->foreignId('estacao_id')->nullable()->constrained('estacoes')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'estacao_id')) {
                $t->dropConstrainedForeignId('estacao_id');
            }
        });
    }
};
