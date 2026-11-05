<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagamentos', function (Blueprint $t) {
            if (! Schema::hasColumn('pagamentos', 'conferido')) {
                $t->boolean('conferido')->default(false)->index();
            }
            if (! Schema::hasColumn('pagamentos', 'conferido_em')) {
                $t->timestamp('conferido_em')->nullable();
            }
            if (! Schema::hasColumn('pagamentos', 'conferido_por')) {
                $t->unsignedBigInteger('conferido_por')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $t) {
            foreach (['conferido', 'conferido_em', 'conferido_por'] as $c) {
                if (Schema::hasColumn('pagamentos', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
