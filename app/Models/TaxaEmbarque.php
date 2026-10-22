<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class TaxaEmbarque extends Model
{
    use PertenceEmpresa;

    protected $table = 'taxas_embarque';
    protected $guarded = [];
    protected $casts = ['taxa' => 'decimal:2', 'ativo' => 'boolean'];

    /** Taxa de embarque do trecho (exato, senão inverso). */
    public static function para(string $origem, string $destino): float
    {
        $r = static::where('ativo', true)->where('origem', $origem)->where('destino', $destino)->first()
          ?? static::where('ativo', true)->where('origem', $destino)->where('destino', $origem)->first();
        return $r ? (float) $r->taxa : 0.0;
    }
}
