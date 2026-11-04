<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('embarcacoes', 'poltronas_lista')) {
                // mapa de poltronas por nome (uma fileira por linha; "|" separa os lados; linha vazia separa blocos)
                $t->text('poltronas_lista')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (Schema::hasColumn('embarcacoes', 'poltronas_lista')) {
                $t->dropColumn('poltronas_lista');
            }
        });
    }
};
