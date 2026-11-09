<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class TrechoPreco extends Model
{
    use PertenceEmpresa;

    protected $table = 'trecho_precos';
    protected $guarded = [];
    protected $casts = ['tarifa' => 'decimal:2', 'alimentacao' => 'decimal:2', 'ativo' => 'boolean'];

    public static function chave(string $o, string $d, int $tipoId): string
    {
        return mb_strtolower(trim($o).'|'.trim($d).'|'.$tipoId);
    }
    /**
     * Re-vincula preços "órfãos" (presos num tipo inativo, após recriação/embaralho de tipos)
     * ao tipo NORMAL ativo da empresa. Idempotente — pode rodar ao abrir a venda/preços.
     */
    public static function curarOrfaos(): void
    {
        $normal = \App\Models\TipoPassageiro::where('ativo', true)->where('eh_normal', true)->first();
        if (! $normal) return;
        $ativos = \App\Models\TipoPassageiro::where('ativo', true)->pluck('id')->all();
        foreach (static::whereNotIn('tipo_passageiro_id', $ativos)->get() as $o) {
            $dup = static::where('origem', $o->origem)->where('destino', $o->destino)
                ->where('tipo_passageiro_id', $normal->id)->where('id', '!=', $o->id)->exists();
            if ($dup) { $o->delete(); }
            else { $o->tipo_passageiro_id = $normal->id; $o->save(); }
        }
    }
}
