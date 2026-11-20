<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill: vendas antigas que tinham desconto (passagens.desconto > 0) mas não geraram
 * o lançamento meio=desconto no caixa. Cria o lançamento retroativo (na data da venda).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('passagens') || ! Schema::hasColumn('passagens', 'desconto')) return;

        \App\Models\Passagem::where('desconto', '>', 0.009)->chunkById(200, function ($lote) {
            foreach ($lote as $p) {
                $jaTem = \App\Models\Pagamento::where('modulo', 'passagem')
                    ->where('origem_id', $p->id)->where('meio', 'desconto')->exists();
                if ($jaTem) continue;

                $pag = \App\Models\Pagamento::create([
                    'empresa_id' => $p->empresa_id,
                    'modulo' => 'passagem',
                    'origem_id' => $p->id,
                    'encomenda_id' => null,
                    'meio' => 'desconto',
                    'valor' => (float) $p->desconto,
                    'user_id' => $p->user_id,
                    'momento' => 'cadastro',
                    'tipo_mov' => 'entrada',
                ]);
                // corrige a data pra ficar na data da venda (o caixa filtra por created_at)
                DB::table('pagamentos')->where('id', $pag->id)->update(['created_at' => $p->created_at]);
            }
        });
    }
};
