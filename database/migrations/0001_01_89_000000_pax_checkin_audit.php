<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passagem_passageiros', function (Blueprint $t) {
            if (! Schema::hasColumn('passagem_passageiros', 'checkin_em')) $t->timestamp('checkin_em')->nullable();
            if (! Schema::hasColumn('passagem_passageiros', 'checkin_por')) $t->unsignedBigInteger('checkin_por')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('passagem_passageiros', function (Blueprint $t) {
            foreach (['checkin_em', 'checkin_por'] as $c) {
                if (Schema::hasColumn('passagem_passageiros', $c)) $t->dropColumn($c);
            }
        });
    }
};
