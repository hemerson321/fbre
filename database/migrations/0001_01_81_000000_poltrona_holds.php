<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reservas temporárias de poltrona (tempo real) — evita que dois operadores/agentes
 * escolham a mesma poltrona ao mesmo tempo. Expiram sozinhas (expires_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('poltrona_holds')) return;
        Schema::create('poltrona_holds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id');
            $t->unsignedBigInteger('embarcacao_id');
            $t->unsignedBigInteger('viagem_id')->nullable();
            $t->date('data_viagem')->nullable();
            $t->string('poltrona', 10);
            $t->string('origem', 80)->nullable();
            $t->string('destino', 80)->nullable();
            $t->unsignedBigInteger('user_id');
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->index(['empresa_id', 'embarcacao_id', 'viagem_id']);
            $t->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poltrona_holds');
    }
};
