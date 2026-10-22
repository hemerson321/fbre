<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

/** Preço base (passagem + taxa de embarque) por rota origem→destino. */
class TabelaPreco extends Model
{
    use PertenceEmpresa;

    protected $table = 'tabela_precos';
    protected $guarded = [];
    protected $casts = ['ativo' => 'boolean', 'passagem' => 'decimal:2', 'taxa_embarque' => 'decimal:2'];

    /** Acha o preço da rota (origem/destino), nos dois sentidos. */
    public static function para(string $origem, string $destino): ?self
    {
        // sentido exato primeiro (subida/descida podem ter preços diferentes)
        $exato = static::where('ativo', true)->where('origem', $origem)->where('destino', $destino)->first();
        if ($exato) return $exato;
        // senão, usa o sentido inverso
        return static::where('ativo', true)->where('origem', $destino)->where('destino', $origem)->first();
    }
}
