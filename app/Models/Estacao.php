<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class Estacao extends Model
{
    use PertenceEmpresa;

    protected $table = 'estacoes';
    protected $guarded = [];

    protected $casts = [
        'visto_em' => 'datetime',
        'ativo' => 'boolean',
        'colunas' => 'integer',
    ];

    /**
     * Resolve a estação para imprimir: 1) a escolhida na hora (se veio),
     * 2) a impressora do operador logado, 3) a última estação vista ativa.
     */
    public static function resolver(?int $estacaoId = null): ?self
    {
        $ativa = fn ($id) => $id ? static::where('ativo', true)->find($id) : null;

        return $ativa($estacaoId)
            ?: $ativa(auth()->user()?->estacao_id)
            ?: static::where('ativo', true)->orderByDesc('visto_em')->first();
    }
}
