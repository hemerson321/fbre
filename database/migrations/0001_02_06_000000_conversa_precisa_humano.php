<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            if (! Schema::hasColumn('conversas', 'precisa_humano')) {
                $table->boolean('precisa_humano')->default(false)->after('ia_ativa');
            }
            if (! Schema::hasColumn('conversas', 'transferida_motivo')) {
                $table->string('transferida_motivo', 160)->nullable()->after('precisa_humano');
            }
            if (! Schema::hasColumn('conversas', 'transferida_em')) {
                $table->timestamp('transferida_em')->nullable()->after('transferida_motivo');
            }
        });
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
