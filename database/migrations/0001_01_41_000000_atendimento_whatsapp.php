<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canal de atendimento por WhatsApp (módulo passagem e outros).
 * conversas = uma por cliente/telefone; mensagens = histórico (entrada/saída).
 * O agente IA (próxima etapa) e o funil de recuperação usam estes mesmos registros.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversas')) {
            Schema::create('conversas', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->string('modulo', 20)->default('passagem');
                $t->foreignId('whatsapp_conexao_id')->nullable()->constrained('whatsapp_conexoes')->nullOnDelete();
                $t->string('cliente_telefone', 30);
                $t->string('cliente_nome', 120)->nullable();
                // aberta | aguardando (precisa humano) | ia | humano | fechada
                $t->string('status', 20)->default('aberta');
                $t->boolean('atendida')->default(false);
                $t->boolean('ia_ativa')->default(false);           // o agente está cuidando desta conversa
                $t->foreignId('assumida_por')->nullable()->constrained('users')->nullOnDelete();
                $t->string('funil', 20)->nullable();               // novo|cotacao|aguardando_pagamento|pago|perdido
                $t->unsignedInteger('nao_lidas')->default(0);
                $t->string('ultima_msg_texto', 255)->nullable();
                $t->string('ultima_msg_origem', 12)->nullable();   // cliente|humano|ia|sistema
                $t->timestamp('ultima_msg_em')->nullable();
                $t->timestamp('cliente_respondeu_em')->nullable(); // p/ regra de "assumir após X min"
                $t->json('contexto')->nullable();                  // dados coletados (origem, destino, pax...)
                $t->timestamps();
                $t->unique(['empresa_id', 'modulo', 'cliente_telefone']);
                $t->index(['empresa_id', 'status']);
            });
        }

        if (! Schema::hasTable('mensagens')) {
            Schema::create('mensagens', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->foreignId('conversa_id')->constrained('conversas')->cascadeOnDelete();
                $t->string('direcao', 8);        // entrada | saida
                $t->string('origem', 12);         // cliente | humano | ia | sistema
                $t->text('texto')->nullable();
                $t->string('wa_message_id', 80)->nullable();
                $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->boolean('lida')->default(false);
                $t->json('meta')->nullable();
                $t->timestamps();
                $t->index(['conversa_id', 'id']);
                $t->index('wa_message_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagens');
        Schema::dropIfExists('conversas');
    }
};
