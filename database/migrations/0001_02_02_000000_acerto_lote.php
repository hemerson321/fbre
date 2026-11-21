<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('passagens', 'acerto_lote')) {
            Schema::table('passagens', function (Blueprint $table) {
                $table->string('acerto_lote', 40)->nullable()->index();
            });
        }
        if (! Schema::hasColumn('pagamentos', 'acerto_lote')) {
            Schema::table('pagamentos', function (Blueprint $table) {
                $table->string('acerto_lote', 40)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('passagens', 'acerto_lote')) {
            Schema::table('passagens', fn (Blueprint $t) => $t->dropColumn('acerto_lote'));
        }
        if (Schema::hasColumn('pagamentos', 'acerto_lote')) {
            Schema::table('pagamentos', fn (Blueprint $t) => $t->dropColumn('acerto_lote'));
        }
    }
};
