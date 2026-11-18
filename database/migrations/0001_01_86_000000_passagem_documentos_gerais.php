<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passagens', function (Blueprint $t) {
            if (! Schema::hasColumn('passagens', 'documentos_gerais')) {
                $t->json('documentos_gerais')->nullable()->after('observacao');
            }
        });
    }

    public function down(): void
    {
        Schema::table('passagens', function (Blueprint $t) {
            if (Schema::hasColumn('passagens', 'documentos_gerais')) {
                $t->dropColumn('documentos_gerais');
            }
        });
    }
};
