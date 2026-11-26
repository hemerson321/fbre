<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class MarketingTerminal extends Model
{
    use PertenceEmpresa;

    protected $table = 'marketing_terminais';
    protected $guarded = [];
    protected $casts = ['ativo' => 'boolean', 'visto_em' => 'datetime'];

    /** Terminal buscou a fila nos últimos 2 minutos = ligado. */
    public function online(): bool
    {
        return $this->visto_em && $this->visto_em->gt(now()->subMinutes(2));
    }
}
