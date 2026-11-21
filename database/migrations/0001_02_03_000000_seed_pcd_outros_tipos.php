<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tipos_passageiro')) return;

        $cols = Schema::getColumnListing('tipos_passageiro');
        $only = fn (array $row) => array_intersect_key($row, array_flip($cols));

        $empresas = DB::table('tipos_passageiro')->select('empresa_id')->distinct()->pluck('empresa_id');

        foreach ($empresas as $empId) {
            if ($empId === null) continue;
            $ordem = (int) DB::table('tipos_passageiro')->where('empresa_id', $empId)->max('ordem');

            // PCD — assento com cota de PCD. Nasce gratuito (0%); ajuste o % em Cadastros se a sua empresa cobra.
            $temPcd = DB::table('tipos_passageiro')->where('empresa_id', $empId)->where('pcd', true)->exists();
            if (! $temPcd) {
                $ordem++;
                DB::table('tipos_passageiro')->insert($only([
                    'empresa_id' => $empId, 'nome' => 'PCD', 'cor' => '#f59e0b',
                    'modo' => 'porcentagem', 'percentual' => 0, 'pct_sobre' => 'tarifa',
                    'tarifa_padrao' => 0, 'alimentacao_padrao' => 0,
                    'paga_taxa' => false, 'gratuito' => false, 'pcd' => true, 'idoso_meia' => false, 'colo' => false,
                    'eh_normal' => false, 'ativo' => true, 'ordem' => $ordem,
                    'created_at' => now(), 'updated_at' => now(),
                ]));
            }

            // Outros — valor digitado na venda (modo manual).
            $temOutros = DB::table('tipos_passageiro')->where('empresa_id', $empId)->where('modo', 'manual')->exists();
            if (! $temOutros) {
                $ordem++;
                DB::table('tipos_passageiro')->insert($only([
                    'empresa_id' => $empId, 'nome' => 'Outros', 'cor' => '#6366f1',
                    'modo' => 'manual', 'percentual' => 100, 'pct_sobre' => 'tarifa',
                    'tarifa_padrao' => 0, 'alimentacao_padrao' => 0,
                    'paga_taxa' => true, 'gratuito' => false, 'pcd' => false, 'idoso_meia' => false, 'colo' => false,
                    'eh_normal' => false, 'ativo' => true, 'ordem' => $ordem,
                    'created_at' => now(), 'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        // Mantém os tipos criados (podem já ter vendas vinculadas).
    }
};
