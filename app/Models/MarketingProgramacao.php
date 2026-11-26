<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingProgramacao extends Model
{
    use PertenceEmpresa;

    protected $table = 'marketing_programacoes';
    protected $guarded = [];
    protected $casts = ['ativo' => 'boolean', 'dias' => 'array'];

    public function audio(): BelongsTo { return $this->belongsTo(MarketingAudio::class, 'audio_id'); }

    public function terminal(): BelongsTo { return $this->belongsTo(MarketingTerminal::class, 'terminal_id'); }

    /** Toca neste dia da semana? (0=dom..6=sáb). dias vazio = todos. */
    public function tocaNoDia(int $dow): bool
    {
        $dias = array_map('intval', (array) $this->dias);
        return empty($dias) || in_array($dow, $dias, true);
    }
}
