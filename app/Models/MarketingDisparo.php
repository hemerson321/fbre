<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingDisparo extends Model
{
    use PertenceEmpresa;

    protected $table = 'marketing_disparos';
    protected $guarded = [];

    public function audio(): BelongsTo { return $this->belongsTo(MarketingAudio::class, 'audio_id'); }

    public function terminal(): BelongsTo { return $this->belongsTo(MarketingTerminal::class, 'terminal_id'); }
}
