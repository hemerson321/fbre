<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // Recebimento PIX deste operador/agente:
            // empresa  = usa o PIX da empresa (por modulo, como e hoje)
            // api      = tem conta propria (PagamentoApi) -> PIX dinamico confirma sozinho
            // estatico = chave PIX propria -> QR estatico (BR Code) na venda
            if (! Schema::hasColumn('users', 'pix_modo')) {
                $t->string('pix_modo', 12)->default('empresa');
            }
            if (! Schema::hasColumn('users', 'pix_api_id')) {
                $t->unsignedBigInteger('pix_api_id')->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'pix_chave')) {
                $t->string('pix_chave', 140)->nullable();
            }
            if (! Schema::hasColumn('users', 'pix_recebedor')) {
                $t->string('pix_recebedor', 60)->nullable();
            }
            if (! Schema::hasColumn('users', 'pix_cidade')) {
                $t->string('pix_cidade', 60)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            foreach (['pix_modo', 'pix_api_id', 'pix_chave', 'pix_recebedor', 'pix_cidade'] as $c) {
                if (Schema::hasColumn('users', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
