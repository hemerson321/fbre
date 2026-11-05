<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passagens', function (Blueprint $t) {
            if (! Schema::hasColumn('passagens', 'transferida')) {
                $t->boolean('transferida')->default(false);
            }
            if (! Schema::hasColumn('passagens', 'transferida_para_id')) {
                $t->unsignedBigInteger('transferida_para_id')->nullable();
            }
            if (! Schema::hasColumn('passagens', 'origem_transferencia_id')) {
                $t->unsignedBigInteger('origem_transferencia_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('passagens', function (Blueprint $t) {
            foreach (['transferida', 'transferida_para_id', 'origem_transferencia_id'] as $c) {
                if (Schema::hasColumn('passagens', $c)) $t->dropColumn($c);
            }
        });
    }
};
