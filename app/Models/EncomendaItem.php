<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncomendaItem extends Model
{
    protected $table = 'encomenda_itens';
    protected $guarded = [];

    public function encomenda(): BelongsTo { return $this->belongsTo(Encomenda::class); }
}
