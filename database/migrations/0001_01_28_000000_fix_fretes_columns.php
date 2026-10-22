<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tabela `fretes` já existia (0001_01_05). Acrescenta as colunas que o módulo usa.
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fretes', function (Blueprint $t) {
            $add = [
                'numero_completo'       => fn () => $t->string('numero_completo', 30)->nullable(),
                'embarcacao_id'         => fn () => $t->unsignedBigInteger('embarcacao_id')->nullable(),
                'rota_id'               => fn () => $t->unsignedBigInteger('rota_id')->nullable(),
                'data_viagem'           => fn () => $t->date('data_viagem')->nullable(),
                'preco_kilo'            => fn () => $t->decimal('preco_kilo', 10, 2)->default(0),
                'subtotal'              => fn () => $t->decimal('subtotal', 10, 2)->default(0),
                'desconto'              => fn () => $t->decimal('desconto', 10, 2)->default(0),
                'total'                 => fn () => $t->decimal('total', 10, 2)->default(0),
                'status_pagamento'      => fn () => $t->string('status_pagamento', 20)->default('pendente'),
                'observacao'            => fn () => $t->string('observacao', 1000)->nullable(),
                'setor'                 => fn () => $t->string('setor', 60)->nullable(),
                'entregue_em'           => fn () => $t->timestamp('entregue_em')->nullable(),
                'entregue_por'          => fn () => $t->unsignedBigInteger('entregue_por')->nullable(),
                'entregue_para'         => fn () => $t->string('entregue_para', 120)->nullable(),
                'motivo_cancelamento'   => fn () => $t->string('motivo_cancelamento', 300)->nullable(),
                'origem_offline'        => fn () => $t->boolean('origem_offline')->default(false),
                'criada_no_aparelho_em' => fn () => $t->timestamp('criada_no_aparelho_em')->nullable(),
            ];
            foreach ($add as $col => $make) {
                if (! Schema::hasColumn('fretes', $col)) $make();
            }
        });
    }

    public function down(): void {}
};
