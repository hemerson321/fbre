<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastros separados e reutilizáveis:
 *  - llm_provedores: credencial de IA (Claude, OpenAI...). Vários por empresa.
 *  - agentes: a "persona" que usa um provedor de LLM + uma conexão de WhatsApp + uma API de
 *    pagamento, atrelada a um serviço/módulo (passagem, encomenda...). Regras, horário e modo de assumir.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('llm_provedores')) {
            Schema::create('llm_provedores', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->string('nome', 80);
                $t->string('provider', 20)->default('claude'); // claude | openai
                $t->text('token')->nullable();
                $t->string('modelo', 80)->nullable();          // ex.: claude-3-5-haiku-latest / gpt-4o-mini
                $t->string('base_url', 255)->nullable();        // custom/compatível (opcional)
                $t->boolean('ativo')->default(true);
                $t->json('config')->nullable();
                $t->timestamps();
                $t->index(['empresa_id', 'ativo']);
            });
        }

        if (! Schema::hasTable('agentes')) {
            Schema::create('agentes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->string('nome', 80);
                $t->string('modulo', 20)->default('passagem');   // serviço que atende
                $t->foreignId('llm_provedor_id')->nullable()->constrained('llm_provedores')->nullOnDelete();
                $t->foreignId('whatsapp_conexao_id')->nullable()->constrained('whatsapp_conexoes')->nullOnDelete();
                $t->foreignId('pagamento_api_id')->nullable()->constrained('pagamento_apis')->nullOnDelete();
                $t->boolean('ativo')->default(true);
                $t->text('persona')->nullable();                 // instruções/estilo do atendimento
                // regras: desligado, nao_revela_robo, sensiveis_humano, nunca_inventa
                $t->json('regras')->nullable();
                // horario: {modo:'24h'|'faixa', inicio:'08:00', fim:'18:00', dias:[1..7]}
                $t->json('horario')->nullable();
                // como assume: sempre | apos_min | fora_horario
                $t->string('assumir_modo', 20)->default('apos_min');
                $t->unsignedSmallInteger('assumir_min')->default(2);
                $t->boolean('recupera_funil')->default(false);
                $t->timestamps();
                $t->index(['empresa_id', 'modulo', 'ativo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agentes');
        Schema::dropIfExists('llm_provedores');
    }
};
