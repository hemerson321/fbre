<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consumo de tokens do agente de IA por conversa (painel "Custo da IA" do atendimento)
 * + vínculo do orçamento com a conversa quando é o agente que monta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agente_usos')) {
            Schema::create('agente_usos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('empresa_id')->index();
                $t->unsignedBigInteger('conversa_id')->nullable()->index();
                $t->unsignedBigInteger('agente_id')->nullable();
                $t->string('provider', 20)->nullable();
                $t->string('modelo', 80)->nullable();
                $t->string('tipo', 20)->default('chat');
                $t->unsignedInteger('tok_in')->default(0);
                $t->unsignedInteger('tok_out')->default(0);
                $t->unsignedInteger('tok_cache_r')->default(0);
                $t->unsignedInteger('tok_cache_w')->default(0);
                $t->timestamps();
                $t->index(['empresa_id', 'created_at']);
            });
        }
        if (Schema::hasTable('orcamentos') && ! Schema::hasColumn('orcamentos', 'conversa_id')) {
            Schema::table('orcamentos', fn (Blueprint $t) => $t->unsignedBigInteger('conversa_id')->nullable()->index());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agente_usos');
        if (Schema::hasTable('orcamentos') && Schema::hasColumn('orcamentos', 'conversa_id')) {
            Schema::table('orcamentos', fn (Blueprint $t) => $t->dropColumn('conversa_id'));
        }
    }
};
