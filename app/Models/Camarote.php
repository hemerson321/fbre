<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tipo de suíte/camarote (catálogo da empresa): capacidade, descrição e preço por trecho. */
class Camarote extends Model
{
    use PertenceEmpresa;

    protected $table = 'camarotes';
    protected $guarded = [];

    protected $casts = [
        'capacidade' => 'integer',
        'valor' => 'decimal:2',
        'valor_adicional' => 'decimal:2',
        'ativo' => 'boolean',
    ];

    public function precos(): HasMany { return $this->hasMany(CamarotePreco::class); }

    /** Preço do camarote para o trecho (origem/destino), nos dois sentidos. */
    public function precoPara(string $origem, string $destino): ?float
    {
        $p = CamarotePreco::para($this->id, $origem, $destino);
        return $p ? (float) $p->valor : null;
    }
}
