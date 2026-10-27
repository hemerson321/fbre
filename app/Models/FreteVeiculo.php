<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreteVeiculo extends Model
{
    use PertenceEmpresa;

    protected $table = 'frete_veiculos';
    protected $guarded = [];

    public function frete(): BelongsTo { return $this->belongsTo(Frete::class); }
}
