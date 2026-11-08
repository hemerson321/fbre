<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige preços "órfãos": quando os tipos de passageiro foram recriados, o tipo
 * NORMAL ganhou um id novo e os trecho_precos ficaram presos no id antigo (inativo),
 * fazendo a venda mostrar R$ 0,00. Aqui re-vinculamos cada preço órfão ao tipo
 * normal ATIVO da mesma empresa (sem colidir com um preço já existente).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trecho_precos') || ! Schema::hasTable('tipos_passageiro')) return;

        foreach (DB::table('empresas')->pluck('id') as $eid) {
            $normal = DB::table('tipos_passageiro')->where('empresa_id', $eid)->where('ativo', true)->where('eh_normal', true)->first();
            if (! $normal) continue;
            $ativos = DB::table('tipos_passageiro')->where('empresa_id', $eid)->where('ativo', true)->pluck('id')->all();
            if (! $ativos) continue;

            // preços apontando para um tipo que não está mais ativo (órfãos)
            $orfaos = DB::table('trecho_precos')->where('empresa_id', $eid)->whereNotIn('tipo_passageiro_id', $ativos)->get();
            foreach ($orfaos as $o) {
                $jaTem = DB::table('trecho_precos')->where('empresa_id', $eid)
                    ->where('origem', $o->origem)->where('destino', $o->destino)
                    ->where('tipo_passageiro_id', $normal->id)->exists();
                if ($jaTem) {
                    DB::table('trecho_precos')->where('id', $o->id)->delete();
                } else {
                    DB::table('trecho_precos')->where('id', $o->id)->update(['tipo_passageiro_id' => $normal->id]);
                }
            }
        }
    }

    public function down(): void
    {
        // não reverte (correção de dados)
    }
};
