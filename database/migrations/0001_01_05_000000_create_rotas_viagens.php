<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ================================================================
        // ROTAS (linhas) e suas PARADAS com calendário (dia da semana + hora)
        // ================================================================
        Schema::create('rotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 80);                      // ex.: "Linha Tefé"
            $table->foreignId('embarcacao_id')->nullable()->constrained('embarcacoes')->nullOnDelete();
            $table->string('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index(['empresa_id', 'ativo']);
        });

        Schema::create('rota_paradas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rota_id')->constrained('rotas')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordem')->default(1);
            $table->string('localidade', 80);                // ex.: Manaus / Coari / Tefé
            $table->unsignedTinyInteger('dia_semana')->nullable(); // 0=domingo ... 6=sábado
            $table->time('hora')->nullable();                // horário previsto nessa parada
            $table->timestamps();
            $table->index(['rota_id', 'ordem']);
        });

        // ================================================================
        // VIAGENS — instância de uma rota num ciclo (gerada automaticamente)
        // ================================================================
        Schema::create('viagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rota_id')->nullable()->constrained('rotas')->nullOnDelete();
            $table->foreignId('embarcacao_id')->nullable()->constrained('embarcacoes')->nullOnDelete();
            $table->unsignedInteger('numero');               // sequencial por empresa
            $table->string('nome', 120)->nullable();         // ex.: "Linha Tefé · 20/10"
            $table->date('data_partida')->nullable();        // data do 1º embarque do ciclo
            $table->date('data_chegada')->nullable();
            $table->enum('status', ['planejada', 'em_andamento', 'concluida', 'cancelada'])->default('planejada');
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'numero']);
            $table->unique(['rota_id', 'data_partida']);     // 1 viagem por rota por ciclo
            $table->index(['empresa_id', 'status']);
            $table->index(['empresa_id', 'data_partida']);
        });

        // encomendas passam a poder pertencer a uma viagem (avulso = null)
        Schema::table('encomendas', function (Blueprint $table) {
            $table->foreignId('viagem_id')->nullable()->after('embarcacao_id')->constrained('viagens')->nullOnDelete();
            $table->foreignId('rota_id')->nullable()->after('viagem_id')->constrained('rotas')->nullOnDelete();
            $table->index(['empresa_id', 'viagem_id']);
        });

        // ================================================================
        // PRÉ-PRONTO (scaffolding) — módulos futuros, desligados por flag.
        // Tabelas e models existem; telas serão adicionadas no upgrade.
        // ================================================================
        Schema::create('camarotes', function (Blueprint $table) {    // tipos de camarote + preço
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rota_id')->nullable()->constrained('rotas')->nullOnDelete();
            $table->string('nome', 60);                      // ex.: "Suíte", "Camarote 01"
            $table->unsignedSmallInteger('capacidade')->default(1);
            $table->decimal('valor', 10, 2)->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('passagens', function (Blueprint $table) {    // bilhetes de passageiro
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('viagem_id')->nullable()->constrained('viagens')->nullOnDelete();
            $table->string('numero_provisorio', 30)->nullable();
            $table->unsignedInteger('numero')->nullable();
            $table->string('passageiro_nome', 120);
            $table->string('passageiro_documento', 20)->nullable();
            $table->string('passageiro_telefone', 30)->nullable();
            $table->string('origem', 80);
            $table->string('destino', 80);
            $table->enum('tipo', ['poltrona', 'rede', 'camarote'])->default('rede');
            $table->foreignId('camarote_id')->nullable()->constrained('camarotes')->nullOnDelete();
            $table->decimal('valor', 10, 2)->default(0);
            $table->decimal('pago', 10, 2)->default(0);
            $table->decimal('devido', 10, 2)->default(0);
            $table->enum('forma', ['avista', 'areceber', 'faturado'])->default('avista');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('embarcada')->default(false);
            $table->boolean('cancelada')->default(false);
            $table->timestamps();
            $table->index(['empresa_id', 'viagem_id']);
        });

        Schema::create('fretes', function (Blueprint $table) {       // carga/frete pesado
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('viagem_id')->nullable()->constrained('viagens')->nullOnDelete();
            $table->string('numero_provisorio', 30)->nullable();
            $table->unsignedInteger('numero')->nullable();
            $table->string('remetente_nome', 120);
            $table->string('remetente_telefone', 30)->nullable();
            $table->string('remetente_documento', 20)->nullable();
            $table->string('destinatario_nome', 120);
            $table->string('destinatario_telefone', 30)->nullable();
            $table->string('destinatario_documento', 20)->nullable();
            $table->string('descricao', 200);
            $table->decimal('peso', 10, 2)->nullable();
            $table->unsignedSmallInteger('volumes')->default(1);
            $table->string('origem', 80);
            $table->string('destino', 80);
            $table->decimal('valor', 10, 2)->default(0);
            $table->decimal('pago', 10, 2)->default(0);
            $table->decimal('devido', 10, 2)->default(0);
            $table->enum('forma', ['avista', 'areceber', 'faturado'])->default('avista');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('entregue')->default(false);
            $table->boolean('cancelada')->default(false);
            $table->timestamps();
            $table->index(['empresa_id', 'viagem_id']);
        });

        Schema::create('tabela_precos', function (Blueprint $table) { // preço por trecho/tipo (autopreenchimento futuro)
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rota_id')->nullable()->constrained('rotas')->nullOnDelete();
            $table->string('origem', 80);
            $table->string('destino', 80);
            $table->enum('tipo', ['encomenda', 'passagem', 'frete', 'camarote'])->default('encomenda');
            $table->decimal('valor', 10, 2)->default(0);
            $table->timestamps();
            $table->index(['empresa_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::table('encomendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('viagem_id');
            $table->dropConstrainedForeignId('rota_id');
        });
        foreach (['tabela_precos', 'fretes', 'passagens', 'camarotes', 'viagens', 'rota_paradas', 'rotas'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
