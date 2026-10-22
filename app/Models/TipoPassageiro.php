<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class TipoPassageiro extends Model
{
    use PertenceEmpresa;

    protected $table = 'tipos_passageiro';
    protected $guarded = [];
    protected $casts = ['ativo' => 'boolean', 'paga_taxa' => 'boolean', 'eh_normal' => 'boolean', 'valor_fixo' => 'decimal:2', 'tarifa_padrao' => 'decimal:2', 'alimentacao_padrao' => 'decimal:2'];

    public const MODOS = ['valor' => 'Valor (Tarifa + Alimentação)', 'porcentagem' => 'Porcentagem (% do Normal)'];

    public function ehPorcentagem(): bool { return ($this->modo ?? 'valor') === 'porcentagem'; }

}
