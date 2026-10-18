<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige/define as paradas das duas rotas do Ferry Boat (Oliveira) com o
 * calendário completo (dia da semana + hora de cada parada). Roda uma vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        $empresa = DB::table('empresas')->where('slug', 'oliveira')->first();
        if (! $empresa) return; // instalação nova: o seeder cria com os dados corretos

        $rotas = [
            'Subida — Manaus → Uarini' => [
                ['Manaus', 2, '11:00'], ['Codajás', 3, '06:30'], ['Coari', 3, '16:00'],
                ['Tefé', 4, '07:00'], ['Alvarães', 4, '19:00'], ['Uarini', 5, '07:00'],
            ],
            'Descida — Uarini → Manaus' => [
                ['Uarini', 6, '07:00'], ['Alvarães', 6, '11:00'], ['Tefé', 6, '17:30'],
                ['Coari', 0, '08:30'], ['Codajás', 0, '14:00'], ['Manaus', 1, '08:00'],
            ],
        ];

        foreach ($rotas as $nome => $paradas) {
            $rotaId = DB::table('rotas')->where('empresa_id', $empresa->id)->where('nome', $nome)->value('id');
            if (! $rotaId) {
                $rotaId = DB::table('rotas')->insertGetId([
                    'empresa_id' => $empresa->id, 'nome' => $nome, 'ativo' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('rota_paradas')->where('rota_id', $rotaId)->delete();
            foreach ($paradas as $i => [$loc, $dia, $hora]) {
                DB::table('rota_paradas')->insert([
                    'rota_id' => $rotaId, 'ordem' => $i + 1, 'localidade' => $loc,
                    'dia_semana' => $dia, 'hora' => $hora, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // rota de exemplo fora
        DB::table('rotas')->where('empresa_id', $empresa->id)->where('nome', 'Linha Tefé')->update(['ativo' => 0]);
    }

    public function down(): void {}
};
