<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Orcamento extends Model
{
    protected $table = 'orcamentos';

    protected $guarded = [];

    protected $casts = [
        'itens' => 'array',
        'data_viagem' => 'date',
        'total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
