<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telas de atendimento: cada tela recebe as conversas de certos números de WhatsApp e define
 * quem atende (agente de IA e/ou quais operadores). + permissão por usuário pra ver o Custo da IA.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('atend_telas')) {
            Schema::create('atend_telas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('empresa_id')->index();
                $t->string('nome', 60);
                $t->json('conexao_ids')->nullable();      // números de WhatsApp que caem nesta tela
                $t->boolean('usa_ia')->default(true);     // agente de IA atende nesta tela?
                $t->json('agente_ids')->nullable();       // quais agentes de IA (vazio = qualquer agente ativo)
                $t->boolean('operadores_todos')->default(true);
                $t->json('user_ids')->nullable();         // operadores que entram na tela (quando não é "todos")
                $t->boolean('ativo')->default(true);
                $t->timestamps();
            });
        }
        if (! Schema::hasColumn('users', 'perm_custo_ia')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('perm_custo_ia')->default(false));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('atend_telas');
        if (Schema::hasColumn('users', 'perm_custo_ia')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('perm_custo_ia'));
        }
    }
};
