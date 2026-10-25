<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassagemPassageiro extends Model
{
    protected $table = 'passagem_passageiros';
    protected $guarded = [];
    protected $casts = [
        'valor_passagem' => 'decimal:2',
        'valor_taxa' => 'decimal:2',
        'valor_integral' => 'decimal:2',
        'valor_total' => 'decimal:2',
    ];

    /** Nascimento tolerante: nunca estoura com valor vazio ou 0000-00-00. */
    public function getNascimentoAttribute($value)
    {
        if (! $value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') return null;
        try { return \Illuminate\Support\Carbon::parse($value); } catch (\Throwable $e) { return null; }
    }

    public function passagem(): BelongsTo { return $this->belongsTo(Passagem::class); }
}
