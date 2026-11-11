<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('embarcacoes', 'repasse_pix')) {
                $t->string('repasse_pix', 10)->default('dono')->after('dono_nome'); // dono | empresa
            }
            if (! Schema::hasColumn('embarcacoes', 'repasse_pix_api_id')) {
                $t->unsignedBigInteger('repasse_pix_api_id')->nullable()->after('repasse_pix');
            }
        });
    }

    public function down(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            foreach (['repasse_pix', 'repasse_pix_api_id'] as $c) {
                if (Schema::hasColumn('embarcacoes', $c)) $t->dropColumn($c);
            }
        });
    }
};
