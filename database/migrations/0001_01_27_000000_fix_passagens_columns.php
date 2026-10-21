<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A tabela `passagens` já existia (criada na 0001_01_05 com outra estrutura),
 * então a 0001_01_22 pulou a criação e faltam as colunas do módulo novo.
 * Aqui garantimos TODAS as colunas que o código usa. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passagens', function (Blueprint $t) {
            $add = [
                'numero_completo'       => fn () => $t->string('numero_completo', 30)->nullable(),
                'embarcacao_id'         => fn () => $t->unsignedBigInteger('embarcacao_id')->nullable(),
                'rota_id'               => fn () => $t->unsignedBigInteger('rota_id')->nullable(),
                'data_viagem'           => fn () => $t->date('data_viagem')->nullable(),
                'comprador_nome'        => fn () => $t->string('comprador_nome', 120)->nullable(),
                'comprador_telefone'    => fn () => $t->string('comprador_telefone', 30)->nullable(),
                'comprador_documento'   => fn () => $t->string('comprador_documento', 20)->nullable(),
                'qtd_passageiros'       => fn () => $t->unsignedInteger('qtd_passageiros')->default(1),
                'subtotal'              => fn () => $t->decimal('subtotal', 10, 2)->default(0),
                'taxa_total'            => fn () => $t->decimal('taxa_total', 10, 2)->default(0),
                'desconto'              => fn () => $t->decimal('desconto', 10, 2)->default(0),
                'total'                 => fn () => $t->decimal('total', 10, 2)->default(0),
                'status_pagamento'      => fn () => $t->string('status_pagamento', 20)->default('pendente'),
                'observacao'            => fn () => $t->string('observacao', 1000)->nullable(),
                'embarcou'              => fn () => $t->boolean('embarcou')->default(false),
                'embarcou_em'           => fn () => $t->timestamp('embarcou_em')->nullable(),
                'embarcou_por'          => fn () => $t->unsignedBigInteger('embarcou_por')->nullable(),
                'motivo_cancelamento'   => fn () => $t->string('motivo_cancelamento', 300)->nullable(),
                'origem_offline'        => fn () => $t->boolean('origem_offline')->default(false),
                'criada_no_aparelho_em' => fn () => $t->timestamp('criada_no_aparelho_em')->nullable(),
            ];
            foreach ($add as $col => $make) {
                if (! Schema::hasColumn('passagens', $col)) $make();
            }
        });

        // a coluna antiga passageiro_nome era NOT NULL; agora o passageiro fica em passagem_passageiros
        if (Schema::hasColumn('passagens', 'passageiro_nome')) {
            try { DB::statement('ALTER TABLE `passagens` MODIFY `passageiro_nome` VARCHAR(120) NULL'); } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        // não remove (colunas podem já existir de instalação nova)
    }
};
