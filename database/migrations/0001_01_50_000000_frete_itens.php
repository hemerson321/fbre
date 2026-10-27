<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Itens/produtos do frete (igual encomenda): descrição, qtd, valor unit., total. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('frete_itens')) {
            Schema::create('frete_itens', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->foreignId('frete_id')->constrained('fretes')->cascadeOnDelete();
                $t->string('descricao', 120);
                $t->decimal('quantidade', 10, 2)->default(1);
                $t->decimal('valor_unitario', 10, 2)->default(0);
                $t->decimal('valor_total', 10, 2)->default(0);
                $t->string('observacao', 200)->nullable();
                $t->timestamps();
                $t->index('frete_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('frete_itens');
    }
};
