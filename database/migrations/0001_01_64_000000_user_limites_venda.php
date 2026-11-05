<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'limite_passagens_dia')) {
                $t->unsignedInteger('limite_passagens_dia')->nullable();
            }
            if (! Schema::hasColumn('users', 'limite_valor_dia')) {
                $t->decimal('limite_valor_dia', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('users', 'limite_passagens_viagem')) {
                $t->unsignedInteger('limite_passagens_viagem')->nullable();
            }
            if (! Schema::hasColumn('users', 'limite_valor_viagem')) {
                $t->decimal('limite_valor_viagem', 12, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            foreach (['limite_passagens_dia', 'limite_valor_dia', 'limite_passagens_viagem', 'limite_valor_viagem'] as $c) {
                if (Schema::hasColumn('users', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
