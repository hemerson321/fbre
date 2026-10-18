<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // EMPRESAS (tenants)
        // ---------------------------------------------------------------
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('nome_fantasia')->nullable();
            $table->string('cnpj', 20)->nullable();
            $table->string('slug', 60)->unique();          // usado em URLs / identificação
            $table->string('telefone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('endereco')->nullable();
            $table->string('cidade', 80)->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('pix')->nullable();              // chave PIX impressa no comprovante
            $table->string('logo')->nullable();             // caminho em storage
            $table->string('cor_primaria', 9)->default('#0b1a33');
            $table->string('cor_secundaria', 9)->default('#00a8e8');
            $table->string('template_comprovante', 40)->default('padrao');
            $table->string('template_etiqueta', 40)->default('padrao');
            $table->text('rodape_comprovante')->nullable();
            $table->json('recursos')->nullable();           // feature flags: ["pos","fretes"]
            $table->json('config')->nullable();             // configurações diversas
            $table->date('validade')->nullable();           // fim do contrato
            $table->boolean('ativo')->default(true);
            $table->unsignedInteger('proximo_numero')->default(1); // numeração de encomenda
            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // USERS
        // ---------------------------------------------------------------
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('setor', 60)->nullable();
            $table->boolean('super_admin')->default(false);   // GERATEC
            $table->boolean('admin')->default(false);         // admin da empresa
            $table->boolean('perm_cadastra')->default(true);
            $table->boolean('perm_baixa')->default(false);
            $table->boolean('perm_caixa')->default(false);
            $table->boolean('ativo')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->index(['empresa_id', 'ativo']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // ---------------------------------------------------------------
        // CADASTROS AUXILIARES
        // ---------------------------------------------------------------
        Schema::create('embarcacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 80);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index(['empresa_id', 'ativo']);
        });

        Schema::create('localidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 80);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index(['empresa_id', 'ativo']);
        });

        Schema::create('setores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 60);
            $table->timestamps();
        });

        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 120);
            $table->decimal('valor_padrao', 10, 2)->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index(['empresa_id', 'ativo']);
        });

        // Pessoas (remetentes/destinatários) para autocompletar
        Schema::create('pessoas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 120);
            $table->string('telefone', 30)->nullable();
            $table->string('documento', 20)->nullable(); // CPF/CNPJ
            $table->timestamps();
            $table->index(['empresa_id', 'nome']);
            $table->index(['empresa_id', 'telefone']);
        });

        // ---------------------------------------------------------------
        // ENCOMENDAS
        // ---------------------------------------------------------------
        Schema::create('encomendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('numero');                 // sequencial por empresa
            $table->foreignId('embarcacao_id')->nullable()->constrained('embarcacoes')->nullOnDelete();
            $table->string('origem', 80);
            $table->string('destino', 80);

            $table->string('remetente_nome', 120);
            $table->string('remetente_telefone', 30)->nullable();
            $table->string('remetente_documento', 20)->nullable();
            $table->string('destinatario_nome', 120);
            $table->string('destinatario_telefone', 30)->nullable();
            $table->string('destinatario_documento', 20)->nullable();

            $table->unsignedSmallInteger('volumes')->default(1);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('desconto', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('pago', 10, 2)->default(0);
            $table->decimal('devido', 10, 2)->default(0);

            $table->enum('forma', ['avista', 'areceber', 'faturado'])->default('avista');
            $table->enum('status_pagamento', ['pendente', 'parcial', 'pago'])->default('pendente');
            $table->boolean('entregue')->default(false);
            $table->timestamp('entregue_em')->nullable();
            $table->foreignId('entregue_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entregue_para', 120)->nullable();   // quem retirou

            $table->text('observacao')->nullable();
            $table->string('setor', 60)->nullable();
            $table->foreignId('user_id')->constrained('users');  // operador que cadastrou
            $table->boolean('cancelada')->default(false);
            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'created_at']);
            $table->index(['empresa_id', 'status_pagamento', 'entregue']);
            $table->index(['empresa_id', 'destino']);
            $table->index(['empresa_id', 'embarcacao_id']);
            $table->index(['empresa_id', 'remetente_nome']);
            $table->index(['empresa_id', 'destinatario_nome']);
        });

        Schema::create('encomenda_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encomenda_id')->constrained()->cascadeOnDelete();
            $table->string('descricao', 120);
            $table->decimal('quantidade', 10, 2)->default(1);
            $table->decimal('valor_unitario', 10, 2)->default(0);
            $table->decimal('valor_total', 10, 2)->default(0);
            $table->string('observacao')->nullable();
            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // PAGAMENTOS / CAIXA
        // ---------------------------------------------------------------
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encomenda_id')->constrained()->cascadeOnDelete();
            $table->enum('meio', ['dinheiro', 'pix', 'debito', 'credito', 'desconto']);
            $table->decimal('valor', 10, 2);
            $table->foreignId('user_id')->constrained('users');   // quem recebeu
            $table->enum('momento', ['cadastro', 'baixa'])->default('cadastro');
            $table->boolean('estornado')->default(false);
            $table->timestamps();

            $table->index(['empresa_id', 'created_at']);
            $table->index(['empresa_id', 'user_id', 'created_at']);
            $table->index(['empresa_id', 'meio']);
        });

        // Log de ações (baixas, entregas, cancelamentos)
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encomenda_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 40);        // cadastro | pagamento | entrega | cancelamento | reimpressao
            $table->json('dados')->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'created_at']);
            $table->index(['empresa_id', 'user_id', 'created_at']);
        });

        // ---------------------------------------------------------------
        // IMPRESSÃO DIRETA (FBRE Print Agent)
        // ---------------------------------------------------------------
        Schema::create('estacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 60);
            $table->string('token', 64)->unique();
            $table->timestamp('visto_em')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('fila_impressao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('estacao_id')->constrained('estacoes')->cascadeOnDelete();
            $table->foreignId('encomenda_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('tipo', ['comprovante', 'etiqueta', 'protocolo', 'relatorio']);
            $table->json('dados');
            $table->enum('status', ['pendente', 'impresso', 'erro'])->default('pendente');
            $table->string('erro')->nullable();
            $table->timestamp('impresso_em')->nullable();
            $table->timestamps();
            $table->index(['estacao_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach ([
            'fila_impressao', 'estacoes', 'eventos', 'pagamentos', 'encomenda_itens', 'encomendas',
            'pessoas', 'produtos', 'setores', 'localidades', 'embarcacoes',
            'sessions', 'password_reset_tokens', 'users', 'empresas',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
