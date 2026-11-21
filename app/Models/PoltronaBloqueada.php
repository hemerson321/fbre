<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoltronaBloqueada extends Model
{
    use PertenceEmpresa;

    protected $table = 'poltronas_bloqueadas';
    protected $guarded = [];
    protected $casts = ['data_viagem' => 'date', 'ativo' => 'boolean'];

    public function embarcacao(): BelongsTo { return $this->belongsTo(Embarcacao::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
