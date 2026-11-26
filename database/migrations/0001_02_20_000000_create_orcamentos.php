<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orcamentos')) {
            return;
        }
        Schema::create('orcamentos', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('empresa_id')->index();
            $t->unsignedInteger('numero')->nullable();
            $t->string('cliente')->nullable();
            $t->string('documento')->nullable();
            $t->string('origem')->nullable();
            $t->string('destino')->nullable();
            $t->date('data_viagem')->nullable();
            $t->unsignedBigInteger('embarcacao_id')->nullable();
            $t->string('embarcacao_nome')->nullable();
            $t->json('itens')->nullable();
            $t->decimal('total', 12, 2)->default(0);
            $t->unsignedInteger('pax')->default(0);
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orcamentos');
    }
};
