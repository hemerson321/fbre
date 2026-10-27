<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frete com cobrança — campos extras (todos opcionais):
 * quem paga (padrão destinatário), responsável, dados da NF-e e veículos (1+).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fretes', function (Blueprint $t) {
            if (! Schema::hasColumn('fretes', 'quem_paga'))        $t->string('quem_paga', 12)->default('destinatario'); // destinatario|remetente
            if (! Schema::hasColumn('fretes', 'nome_responsavel')) $t->string('nome_responsavel', 120)->nullable();
            if (! Schema::hasColumn('fretes', 'nf_chave'))         $t->string('nf_chave', 60)->nullable();
            if (! Schema::hasColumn('fretes', 'nf_numero'))        $t->string('nf_numero', 30)->nullable();
            if (! Schema::hasColumn('fretes', 'nf_dados'))         $t->json('nf_dados')->nullable(); // retorno da consulta (meudanfe)
        });

        if (! Schema::hasTable('frete_veiculos')) {
            Schema::create('frete_veiculos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->foreignId('frete_id')->constrained('fretes')->cascadeOnDelete();
                $t->string('placa', 12)->nullable();
                $t->string('modelo', 60)->nullable();
                $t->string('cor', 30)->nullable();
                $t->string('documento', 40)->nullable(); // RENAVAM/chassi/doc
                $t->string('obs', 200)->nullable();
                $t->timestamps();
                $t->index('frete_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('frete_veiculos');
        Schema::table('fretes', function (Blueprint $t) {
            foreach (['quem_paga', 'nome_responsavel', 'nf_chave', 'nf_numero', 'nf_dados'] as $c) {
                if (Schema::hasColumn('fretes', $c)) $t->dropColumn($c);
            }
        });
    }
};
