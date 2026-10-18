<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RotaParada extends Model
{
    protected $table = 'rota_paradas';
    protected $guarded = [];

    public function rota(): BelongsTo { return $this->belongsTo(Rota::class); }

    public function diaNome(): ?string
    {
        return $this->dia_semana === null ? null : Rota::DIAS[$this->dia_semana];
    }
}
