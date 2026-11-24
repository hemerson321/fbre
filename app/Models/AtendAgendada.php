<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class AtendAgendada extends Model
{
    use PertenceEmpresa;

    protected $table = 'atend_agendadas';
    protected $guarded = [];
    protected $casts = ['enviar_em' => 'datetime', 'enviada_em' => 'datetime'];

    public function conversa(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Conversa::class); }
}
