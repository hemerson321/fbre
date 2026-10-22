<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preço é por TIPO de passageiro em cada trecho (como no sistema antigo):
 *  - tipo "Valor"      -> Tarifa + Alimentação próprios por trecho (padrão no tipo).
 *  - tipo "Porcentagem"-> automático = % da tarifa do NORMAL (Adulto) daquele trecho.
 * Taxa de embarque vira tela separada (por origem→destino).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_passageiro', function (Blueprint $t) {
            if (! Schema::hasColumn('tipos_passageiro', 'cor')) $t->string('cor', 9)->default('#2563eb');
            if (! Schema::hasColumn('tipos_passageiro', 'tarifa_padrao')) $t->decimal('tarifa_padrao', 10, 2)->default(0);
            if (! Schema::hasColumn('tipos_passageiro', 'alimentacao_padrao')) $t->decimal('alimentacao_padrao', 10, 2)->default(0);
            if (! Schema::hasColumn('tipos_passageiro', 'eh_normal')) $t->boolean('eh_normal')->default(false);
        });

        if (! Schema::hasTable('trecho_precos')) {
            Schema::create('trecho_precos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->string('origem', 80);
                $t->string('destino', 80);
                $t->unsignedBigInteger('tipo_passageiro_id');
                $t->decimal('tarifa', 10, 2)->default(0);
                $t->decimal('alimentacao', 10, 2)->default(0);
                $t->boolean('ativo')->default(true);
                $t->timestamps();
                $t->unique(['empresa_id', 'origem', 'destino', 'tipo_passageiro_id'], 'trecho_precos_uniq');
                $t->index(['empresa_id', 'origem', 'destino']);
            });
        }

        if (! Schema::hasTable('taxas_embarque')) {
            Schema::create('taxas_embarque', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->string('origem', 80);
                $t->string('destino', 80);
                $t->decimal('taxa', 10, 2)->default(0);
                $t->boolean('ativo')->default(true);
                $t->timestamps();
                $t->unique(['empresa_id', 'origem', 'destino'], 'taxas_embarque_uniq');
            });
        }

        // Marca um "Normal" por empresa (Adulto, senão o de menor ordem) e copia a tarifa base como padrão.
        foreach (DB::table('empresas')->pluck('id') as $eid) {
            if (DB::table('tipos_passageiro')->where('empresa_id', $eid)->where('eh_normal', true)->exists()) continue;
            $normal = DB::table('tipos_passageiro')->where('empresa_id', $eid)
                ->orderByRaw("CASE WHEN LOWER(nome)='adulto' THEN 0 ELSE 1 END")
                ->orderBy('ordem')->orderBy('id')->first();
            if ($normal) {
                DB::table('tipos_passageiro')->where('id', $normal->id)->update(['eh_normal' => true, 'modo' => 'valor']);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trecho_precos');
        Schema::dropIfExists('taxas_embarque');
        Schema::table('tipos_passageiro', function (Blueprint $t) {
            foreach (['cor', 'tarifa_padrao', 'alimentacao_padrao', 'eh_normal'] as $c) {
                if (Schema::hasColumn('tipos_passageiro', $c)) $t->dropColumn($c);
            }
        });
    }
};
