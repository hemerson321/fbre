<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('embarcacoes', 'gps_url')) {
                $t->string('gps_url', 255)->nullable()->after('rastreio_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (Schema::hasColumn('embarcacoes', 'gps_url')) {
                $t->dropColumn('gps_url');
            }
        });
    }
};
