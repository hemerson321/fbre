<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passagem_passageiros', function (Blueprint $t) {
            if (! Schema::hasColumn('passagem_passageiros', 'colo_de_id')) {
                $t->unsignedBigInteger('colo_de_id')->nullable()->after('papel');
            }
        });
    }

    public function down(): void
    {
        Schema::table('passagem_passageiros', function (Blueprint $t) {
            if (Schema::hasColumn('passagem_passageiros', 'colo_de_id')) {
                $t->dropColumn('colo_de_id');
            }
        });
    }
};
