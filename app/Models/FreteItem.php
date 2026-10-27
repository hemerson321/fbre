<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreteItem extends Model
{
    use PertenceEmpresa;

    protected $table = 'frete_itens';
    protected $guarded = [];
    protected $casts = ['quantidade' => 'decimal:2', 'valor_unitario' => 'decimal:2', 'valor_total' => 'decimal:2'];

    public function frete(): BelongsTo { return $this->belongsTo(Frete::class); }
}
