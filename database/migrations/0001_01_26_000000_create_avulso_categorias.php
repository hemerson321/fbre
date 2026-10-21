<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Avulso = entradas e saídas do caixa, com categoria.
 * As categorias são cadastradas pelo admin (Cadastros gerais); o operador só escolhe.
 * O lançamento em si vira uma linha em `pagamentos` (modulo=avulso, tipo_mov, categoria).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('avulso_categorias')) {
            Schema::create('avulso_categorias', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->string('nome', 60);
                $t->enum('tipo', ['entrada', 'saida', 'ambos'])->default('ambos');
                $t->boolean('ativo')->default(true);
                $t->unsignedInteger('ordem')->default(0);
                $t->timestamps();
                $t->index(['empresa_id', 'ativo']);
            });
        }

        if (! Schema::hasColumn('pagamentos', 'categoria')) {
            Schema::table('pagamentos', function (Blueprint $t) {
                $t->string('categoria', 60)->nullable();
            });
        }

        // categorias padrão para empresas existentes
        foreach (DB::table('empresas')->pluck('id') as $eid) {
            if (DB::table('avulso_categorias')->where('empresa_id', $eid)->exists()) continue;
            $base = [
                ['Venda avulsa', 'entrada', 1], ['Suprimento (troco)', 'entrada', 2],
                ['Sangria', 'saida', 3], ['Despesa', 'saida', 4],
                ['Combustível', 'saida', 5], ['Outros', 'ambos', 6],
            ];
            foreach ($base as [$nome, $tipo, $ordem]) {
                DB::table('avulso_categorias')->insert([
                    'empresa_id' => $eid, 'nome' => $nome, 'tipo' => $tipo,
                    'ativo' => 1, 'ordem' => $ordem, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('avulso_categorias');
        if (Schema::hasColumn('pagamentos', 'categoria')) {
            Schema::table('pagamentos', fn (Blueprint $t) => $t->dropColumn('categoria'));
        }
    }
};
