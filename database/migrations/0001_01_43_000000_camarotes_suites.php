<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suítes / Camarotes no módulo de passagem.
 * - camarotes (catálogo de TIPOS de suíte/camarote): descrição + adicional por pessoa extra
 * - camarote_precos: preço por trecho (origem→destino), igual à passagem
 * - passagens: ganha tipo ('comum'|'camarote') + camarote_id
 * - passagem_passageiros: ganha papel ('titular'|'incluido'|'adicional')
 * Idempotente (guardas hasTable/hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        // tabela de catálogo já existe como scaffold; amplia
        if (Schema::hasTable('camarotes')) {
            Schema::table('camarotes', function (Blueprint $t) {
                if (! Schema::hasColumn('camarotes', 'descricao')) $t->text('descricao')->nullable()->after('capacidade');
                if (! Schema::hasColumn('camarotes', 'valor_adicional')) $t->decimal('valor_adicional', 10, 2)->default(0)->after('valor');
                if (! Schema::hasColumn('camarotes', 'ordem')) $t->unsignedInteger('ordem')->default(0);
            });
        } else {
            Schema::create('camarotes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->foreignId('rota_id')->nullable()->constrained('rotas')->nullOnDelete();
                $t->string('nome', 60);
                $t->unsignedSmallInteger('capacidade')->default(1);
                $t->text('descricao')->nullable();
                $t->decimal('valor', 10, 2)->default(0);
                $t->decimal('valor_adicional', 10, 2)->default(0);
                $t->unsignedInteger('ordem')->default(0);
                $t->boolean('ativo')->default(true);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('camarote_precos')) {
            Schema::create('camarote_precos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->foreignId('camarote_id')->constrained('camarotes')->cascadeOnDelete();
                $t->string('origem', 80);
                $t->string('destino', 80);
                $t->decimal('valor', 10, 2)->default(0);
                $t->timestamps();
                $t->unique(['empresa_id', 'camarote_id', 'origem', 'destino'], 'camarote_preco_unico');
                $t->index(['empresa_id', 'camarote_id']);
            });
        }

        if (Schema::hasTable('passagens')) {
            Schema::table('passagens', function (Blueprint $t) {
                if (! Schema::hasColumn('passagens', 'tipo')) $t->string('tipo', 20)->default('comum')->after('destino');
                if (! Schema::hasColumn('passagens', 'camarote_id')) {
                    $t->unsignedBigInteger('camarote_id')->nullable()->after('tipo');
                    $t->foreign('camarote_id')->references('id')->on('camarotes')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('passagem_passageiros')) {
            Schema::table('passagem_passageiros', function (Blueprint $t) {
                if (! Schema::hasColumn('passagem_passageiros', 'papel')) $t->string('papel', 20)->nullable()->after('tipo');
            });
        }
    }

    public function down(): void
    {
        // não desfaz (colunas/tabelas podem já existir de instalação nova)
    }
};
