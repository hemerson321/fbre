<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagamentos', function (Blueprint $t) {
            if (! Schema::hasColumn('pagamentos', 'embarcacao_id')) {
                $t->unsignedBigInteger('embarcacao_id')->nullable()->index();
            }
        });

        // Backfill: vendas de agente JA acertadas com o dono (barcos com dono) viram
        // entrada no caixa do transporte, para aparecerem no "Caixa do meu transporte".
        $donoPorBarco = DB::table('embarcacoes')->whereNotNull('dono_id')->pluck('dono_id', 'id');
        if ($donoPorBarco->isEmpty()) return;

        // nao duplicar se ja existir entrada de repasse do dono para o barco
        $jaTem = DB::table('pagamentos')->whereNotNull('embarcacao_id')
            ->where('categoria', 'Repasse do agente')->pluck('embarcacao_id')->unique()->flip();

        $rows = DB::table('passagens')
            ->whereNotNull('revendedor_id')->where('cancelada', false)->where('acerto_dono', true)
            ->whereIn('embarcacao_id', array_keys($donoPorBarco->toArray()))
            ->groupBy('empresa_id', 'embarcacao_id')
            ->selectRaw('empresa_id, embarcacao_id, COALESCE(SUM(acerto_empresa_valor),0) as v')
            ->get();

        foreach ($rows as $r) {
            if ($r->v <= 0 || isset($jaTem[$r->embarcacao_id])) continue;
            DB::table('pagamentos')->insert([
                'empresa_id' => $r->empresa_id, 'encomenda_id' => null, 'modulo' => 'avulso',
                'embarcacao_id' => $r->embarcacao_id, 'origem_id' => null,
                'meio' => 'dinheiro', 'valor' => $r->v, 'user_id' => $donoPorBarco[$r->embarcacao_id],
                'momento' => 'baixa', 'tipo_mov' => 'entrada', 'conferido' => 1, 'acerto_conta' => 0,
                'categoria' => 'Repasse do agente', 'descricao' => 'Repasse do agente (acerto do dono)',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $t) {
            if (Schema::hasColumn('pagamentos', 'embarcacao_id')) $t->dropColumn('embarcacao_id');
        });
    }
};
