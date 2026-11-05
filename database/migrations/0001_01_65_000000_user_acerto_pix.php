<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'acerto_pix_modo')) {
                // como o agente paga a empresa: estatico (chave da empresa + comprovante) | dinamico (MercadoPago, baixa sozinho)
                $t->string('acerto_pix_modo', 12)->default('estatico');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'acerto_pix_modo')) {
                $t->dropColumn('acerto_pix_modo');
            }
        });
    }
};
