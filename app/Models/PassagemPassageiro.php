<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassagemPassageiro extends Model
{
    protected $table = 'passagem_passageiros';
    protected $guarded = [];
    protected $casts = [
        'nascimento' => 'date',
        'valor_passagem' => 'decimal:2',
        'valor_taxa' => 'decimal:2',
        'valor_integral' => 'decimal:2',
        'valor_total' => 'decimal:2',
    ];

    public function passagem(): BelongsTo { return $this->belongsTo(Passagem::class); }
}
