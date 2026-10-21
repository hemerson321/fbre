<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo PASSAGEM — espelha a ideia do encomenda.
 * - embarcação ganha configuração de poltrona (barco com assento numerado ou livre)
 * - tipos_passageiro: adulto/estudante/idoso/criança com percentual da tarifa (configurável)
 * - tabela_precos: passagem base + taxa de embarque por rota (origem/destino)
 * - passagens + passagem_passageiros (cada passageiro é uma "linha", como item de encomenda)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) poltrona é configuração da embarcação
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('embarcacoes', 'usa_poltrona')) $t->boolean('usa_poltrona')->default(false);
            if (! Schema::hasColumn('embarcacoes', 'poltronas_qtd')) $t->unsignedInteger('poltronas_qtd')->default(0);
            if (! Schema::hasColumn('embarcacoes', 'poltronas_layout')) $t->text('poltronas_layout')->nullable();
        });

        // 2) tipos de passageiro (percentual da tarifa)
        if (! Schema::hasTable('tipos_passageiro')) {
            Schema::create('tipos_passageiro', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->string('nome', 60);
                $t->unsignedTinyInteger('percentual')->default(100); // % da passagem
                $t->boolean('paga_taxa')->default(true);             // paga taxa de embarque?
                $t->boolean('ativo')->default(true);
                $t->unsignedInteger('ordem')->default(0);
                $t->timestamps();
                $t->index(['empresa_id', 'ativo']);
            });
        }

        // 3) tabela de preços por rota (origem/destino)
        if (! Schema::hasTable('tabela_precos')) {
            Schema::create('tabela_precos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->string('origem', 80);
                $t->string('destino', 80);
                $t->decimal('passagem', 10, 2)->default(0);
                $t->decimal('taxa_embarque', 10, 2)->default(0);
                $t->boolean('ativo')->default(true);
                $t->timestamps();
                $t->unique(['empresa_id', 'origem', 'destino']);
            });
        }

        // 4) passagens
        if (! Schema::hasTable('passagens')) {
            Schema::create('passagens', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->uuid('uuid')->nullable()->unique();
                $t->unsignedBigInteger('numero')->default(0);           // sequência por operador
                $t->string('numero_completo', 30)->nullable();          // PREFIXO-0001 (definitivo)
                $t->foreignId('embarcacao_id')->nullable();
                $t->foreignId('rota_id')->nullable();
                $t->foreignId('viagem_id')->nullable();
                $t->date('data_viagem')->nullable();
                $t->string('origem', 80);
                $t->string('destino', 80);
                $t->string('comprador_nome', 120)->nullable();
                $t->string('comprador_telefone', 30)->nullable();
                $t->string('comprador_documento', 20)->nullable();
                $t->unsignedInteger('qtd_passageiros')->default(1);
                $t->decimal('subtotal', 10, 2)->default(0);
                $t->decimal('taxa_total', 10, 2)->default(0);
                $t->decimal('desconto', 10, 2)->default(0);
                $t->decimal('total', 10, 2)->default(0);
                $t->decimal('pago', 10, 2)->default(0);
                $t->decimal('devido', 10, 2)->default(0);
                $t->string('status_pagamento', 20)->default('pendente'); // pago|pendente|parcial
                $t->enum('forma', ['avista', 'areceber', 'faturado'])->default('avista');
                $t->string('observacao', 1000)->nullable();
                $t->foreignId('user_id')->constrained('users');
                $t->boolean('embarcou')->default(false);                 // "baixa" da passagem = embarque/check-in
                $t->timestamp('embarcou_em')->nullable();
                $t->foreignId('embarcou_por')->nullable();
                $t->boolean('cancelada')->default(false);
                $t->string('motivo_cancelamento', 300)->nullable();
                $t->boolean('origem_offline')->default(false);
                $t->timestamp('criada_no_aparelho_em')->nullable();
                $t->timestamps();
                $t->index(['empresa_id', 'created_at']);
                $t->index(['empresa_id', 'numero_completo']);
                $t->index(['empresa_id', 'viagem_id']);
            });
        }

        // 5) passageiros da passagem
        if (! Schema::hasTable('passagem_passageiros')) {
            Schema::create('passagem_passageiros', function (Blueprint $t) {
                $t->id();
                $t->foreignId('passagem_id')->constrained()->cascadeOnDelete();
                $t->string('nome', 120);
                $t->string('tipo', 60)->default('Adulto');       // nome do tipo no momento
                $t->unsignedTinyInteger('percentual')->default(100);
                $t->string('documento', 20)->nullable();
                $t->date('nascimento')->nullable();
                $t->string('poltrona', 10)->nullable();          // assento (quando a embarcação usa)
                $t->decimal('valor_passagem', 10, 2)->default(0);
                $t->decimal('valor_taxa', 10, 2)->default(0);
                $t->decimal('valor_total', 10, 2)->default(0);
                $t->string('observacao', 200)->nullable();
                $t->timestamps();
                $t->index('passagem_id');
            });
        }

        // 6) tipos padrão para empresas existentes
        foreach (DB::table('empresas')->pluck('id') as $eid) {
            if (DB::table('tipos_passageiro')->where('empresa_id', $eid)->exists()) continue;
            $base = [
                ['Adulto', 100, 1, 1],
                ['Estudante', 50, 1, 2],
                ['Criança', 50, 1, 3],
                ['Idoso', 50, 1, 4],
                ['Gratuito', 0, 0, 5],
            ];
            foreach ($base as [$nome, $pct, $taxa, $ordem]) {
                DB::table('tipos_passageiro')->insert([
                    'empresa_id' => $eid, 'nome' => $nome, 'percentual' => $pct,
                    'paga_taxa' => $taxa, 'ativo' => 1, 'ordem' => $ordem,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('passagem_passageiros');
        Schema::dropIfExists('passagens');
        Schema::dropIfExists('tabela_precos');
        Schema::dropIfExists('tipos_passageiro');
        Schema::table('embarcacoes', function (Blueprint $t) {
            foreach (['usa_poltrona', 'poltronas_qtd', 'poltronas_layout'] as $c) {
                if (Schema::hasColumn('embarcacoes', $c)) $t->dropColumn($c);
            }
        });
    }
};
