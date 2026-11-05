<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('embarcacoes', 'tripulantes')) {
                $t->json('tripulantes')->nullable();
            }
            if (! Schema::hasColumn('embarcacoes', 'ficha')) {
                $t->json('ficha')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            foreach (['tripulantes', 'ficha'] as $c) {
                if (Schema::hasColumn('embarcacoes', $c)) $t->dropColumn($c);
            }
        });
    }
};
