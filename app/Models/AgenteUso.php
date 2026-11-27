<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma chamada à IA feita pelo agente numa conversa, com os tokens gastos. */
class AgenteUso extends Model
{
    use PertenceEmpresa;

    protected $table = 'agente_usos';
    protected $guarded = [];

    public function conversa(): BelongsTo { return $this->belongsTo(Conversa::class); }
}
