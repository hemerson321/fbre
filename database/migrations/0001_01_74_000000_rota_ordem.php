<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rotas', function (Blueprint $t) {
            if (! Schema::hasColumn('rotas', 'ordem')) {
                $t->integer('ordem')->default(0)->after('nome');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rotas', function (Blueprint $t) {
            if (Schema::hasColumn('rotas', 'ordem')) {
                $t->dropColumn('ordem');
            }
        });
    }
};
