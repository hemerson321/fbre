<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('empresas') && ! Schema::hasColumn('empresas', 'cor_terciaria')) {
            Schema::table('empresas', function (Blueprint $t) {
                $t->string('cor_terciaria', 9)->default('#f5a623')->after('cor_secundaria');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresas', 'cor_terciaria')) {
            Schema::table('empresas', function (Blueprint $t) {
                $t->dropColumn('cor_terciaria');
            });
        }
    }
};
