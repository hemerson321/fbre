<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_cadastros', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $t->string('codigo', 8);                 // código curto mostrado ao cliente
            $t->string('origem', 80)->nullable();
            $t->string('destino', 80)->nullable();
            $t->string('remetente_nome', 120)->nullable();
            $t->string('remetente_telefone', 30)->nullable();
            $t->string('destinatario_nome', 120)->nullable();
            $t->string('destinatario_telefone', 30)->nullable();
            $t->json('itens')->nullable();           // [{descricao, quantidade}]
            $t->string('observacao', 500)->nullable();
            $t->string('status', 20)->default('pendente'); // pendente | finalizado | cancelado
            $t->foreignId('encomenda_id')->nullable()->constrained('encomendas')->nullOnDelete();
            $t->timestamps();
            $t->index(['empresa_id', 'status']);
            $t->unique(['empresa_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_cadastros');
    }
};
