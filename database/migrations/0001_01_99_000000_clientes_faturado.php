<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clientes_faturado')) {
            Schema::create('clientes_faturado', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->string('nome', 120);                 // contato / responsavel
                $t->string('empresa', 120)->nullable();  // razao social / nome da empresa
                $t->string('documento', 20)->nullable(); // CPF ou CNPJ
                $t->string('doc_tipo', 8)->nullable();   // CPF | CNPJ (auto)
                $t->string('telefone', 30)->nullable();
                $t->boolean('ativo')->default(true);
                $t->timestamps();
                $t->index(['empresa_id', 'nome']);
            });
        }
        Schema::table('passagens', function (Blueprint $t) {
            if (! Schema::hasColumn('passagens', 'cliente_faturado_id')) $t->unsignedBigInteger('cliente_faturado_id')->nullable();
        });
    }
};
