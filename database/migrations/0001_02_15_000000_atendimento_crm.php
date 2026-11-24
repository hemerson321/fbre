<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Atendimento (modo operador): respostas rápidas / funis de mensagem, etiquetas, mensagens agendadas e envio em massa. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('atend_respostas')) {
            Schema::create('atend_respostas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('empresa_id')->index();
                $t->string('titulo', 120);
                $t->json('passos');                 // [{texto, arquivo, mime, nome, espera}] — 1 passo = resposta rápida; vários = funil
                $t->unsignedInteger('ordem')->default(0);
                $t->boolean('ativo')->default(true);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('atend_etiquetas')) {
            Schema::create('atend_etiquetas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('empresa_id')->index();
                $t->string('nome', 60);
                $t->string('cor', 9)->default('#6366f1');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('atend_agendadas')) {
            Schema::create('atend_agendadas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('empresa_id')->index();
                $t->unsignedBigInteger('conversa_id')->nullable()->index();
                $t->unsignedBigInteger('whatsapp_conexao_id')->nullable();
                $t->string('telefone', 30)->nullable();
                $t->string('tipo', 12)->default('mensagem'); // mensagem | status
                $t->text('texto')->nullable();
                $t->string('arquivo', 255)->nullable();
                $t->string('mime', 80)->nullable();
                $t->string('nome_arquivo', 160)->nullable();
                $t->timestamp('enviar_em')->index();
                $t->string('status', 12)->default('pendente')->index(); // pendente | enviada | erro | cancelada
                $t->string('erro', 255)->nullable();
                $t->string('lote', 40)->nullable()->index();   // envio em massa / funil
                $t->string('lote_nome', 120)->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->timestamp('enviada_em')->nullable();
                $t->timestamps();
            });
        }
        if (Schema::hasTable('conversas') && ! Schema::hasColumn('conversas', 'etiquetas')) {
            Schema::table('conversas', fn (Blueprint $t) => $t->json('etiquetas')->nullable());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('atend_agendadas');
        Schema::dropIfExists('atend_etiquetas');
        Schema::dropIfExists('atend_respostas');
        if (Schema::hasColumn('conversas', 'etiquetas')) Schema::table('conversas', fn (Blueprint $t) => $t->dropColumn('etiquetas'));
    }
};
