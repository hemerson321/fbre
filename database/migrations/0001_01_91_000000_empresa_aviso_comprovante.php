<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $t) {
            if (! Schema::hasColumn('empresas', 'aviso_comprovante')) {
                $t->string('aviso_comprovante', 300)->nullable()->after('rodape_comprovante');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $t) {
            if (Schema::hasColumn('empresas', 'aviso_comprovante')) {
                $t->dropColumn('aviso_comprovante');
            }
        });
    }
};
