<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class TipoPassageiro extends Model
{
    use PertenceEmpresa;

    protected $table = 'tipos_passageiro';
    protected $guarded = [];
    protected $casts = ['ativo' => 'boolean', 'paga_taxa' => 'boolean', 'valor_fixo' => 'decimal:2'];

    public const MODOS = ['porcentagem' => 'Porcentagem (% da tarifa)', 'valor' => 'Valor fixo (R$)'];
}
