<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_passageiro', function (Blueprint $t) {
            if (! Schema::hasColumn('tipos_passageiro', 'colo')) {
                $t->boolean('colo')->default(false)->after('idoso_meia');
            }
        });

        // Garante um tipo "Bebê de colo" por empresa (não ocupa poltrona, não paga).
        if (Schema::hasColumn('tipos_passageiro', 'colo')) {
            $empresaIds = DB::table('tipos_passageiro')->distinct()->pluck('empresa_id');
            foreach ($empresaIds as $eid) {
                $temColo = DB::table('tipos_passageiro')->where('empresa_id', $eid)->where('colo', true)->exists();
                if ($temColo) continue;
                $ordem = (int) DB::table('tipos_passageiro')->where('empresa_id', $eid)->max('ordem') + 1;
                DB::table('tipos_passageiro')->insert([
                    'empresa_id' => $eid,
                    'nome' => 'Bebê de colo',
                    'cor' => '#64748b',
                    'modo' => 'porcentagem',
                    'percentual' => 0,
                    'pct_sobre' => 'tarifa',
                    'paga_taxa' => false,
                    'gratuito' => false,
                    'pcd' => false,
                    'idoso_meia' => false,
                    'colo' => true,
                    'eh_normal' => false,
                    'ativo' => true,
                    'ordem' => $ordem,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tipos_passageiro', function (Blueprint $t) {
            if (Schema::hasColumn('tipos_passageiro', 'colo')) {
                $t->dropColumn('colo');
            }
        });
    }
};
