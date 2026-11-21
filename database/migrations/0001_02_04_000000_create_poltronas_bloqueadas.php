<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('poltronas_bloqueadas')) return;
        Schema::create('poltronas_bloqueadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('embarcacao_id')->constrained('embarcacoes')->cascadeOnDelete();
            $table->string('poltrona', 10);
            $table->date('data_viagem')->nullable();          // null = todas as viagens (permanente)
            $table->string('motivo', 120)->nullable();
            $table->boolean('ativo')->default(true);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['empresa_id', 'embarcacao_id', 'ativo']);
            $table->index(['embarcacao_id', 'data_viagem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poltronas_bloqueadas');
    }
};
