<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Categoria das mensagens rápidas/funis (agrupa as respostas no painel de atendimento). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atend_respostas', function (Blueprint $t) {
            if (! Schema::hasColumn('atend_respostas', 'categoria')) {
                $t->string('categoria', 60)->nullable()->after('titulo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('atend_respostas', function (Blueprint $t) {
            if (Schema::hasColumn('atend_respostas', 'categoria')) $t->dropColumn('categoria');
        });
    }
};
