<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class PoltronaHold extends Model
{
    use PertenceEmpresa;

    protected $table = 'poltrona_holds';
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime'];

    public function scopeAtivo($q)
    {
        return $q->where('expires_at', '>', now());
    }
}
