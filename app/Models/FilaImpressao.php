<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilaImpressao extends Model
{
    use PertenceEmpresa;

    protected $table = 'fila_impressao';
    protected $guarded = [];
    protected $casts = ['dados' => 'array'];

    public function estacao(): BelongsTo { return $this->belongsTo(Estacao::class); }
    public function encomenda(): BelongsTo { return $this->belongsTo(Encomenda::class); }
}
