<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('conversas', 'precisa_humano')) {
            Schema::table('conversas', fn (Blueprint $t) => $t->boolean('precisa_humano')->default(false));
        }
        if (! Schema::hasColumn('conversas', 'transferida_motivo')) {
            Schema::table('conversas', fn (Blueprint $t) => $t->string('transferida_motivo', 160)->nullable());
        }
        if (! Schema::hasColumn('conversas', 'transferida_em')) {
            Schema::table('conversas', fn (Blueprint $t) => $t->timestamp('transferida_em')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            foreach (['precisa_humano', 'transferida_motivo', 'transferida_em'] as $c) {
                if (Schema::hasColumn('conversas', $c)) $table->dropColumn($c);
            }
        });
    }
};
