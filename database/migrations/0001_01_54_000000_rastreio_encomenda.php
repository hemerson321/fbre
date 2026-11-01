<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('embarcacoes', 'rastreio_url')) {
                $t->string('rastreio_url', 255)->nullable(); // URL da API de rastreio do barco (ex.: rionave riotag)
            }
        });
        Schema::table('encomendas', function (Blueprint $t) {
            if (! Schema::hasColumn('encomendas', 'avisado_chegada_em')) {
                $t->timestamp('avisado_chegada_em')->nullable(); // quando o cliente foi avisado da chegada (~1h antes)
            }
        });
    }

    public function down(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (Schema::hasColumn('embarcacoes', 'rastreio_url')) $t->dropColumn('rastreio_url');
        });
        Schema::table('encomendas', function (Blueprint $t) {
            if (Schema::hasColumn('encomendas', 'avisado_chegada_em')) $t->dropColumn('avisado_chegada_em');
        });
    }
};
