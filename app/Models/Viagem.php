<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Viagem extends Model
{
    use PertenceEmpresa;

    protected $table = 'viagens';
    protected $guarded = [];
    protected $casts = ['data_partida' => 'date', 'data_chegada' => 'date'];

    public const STATUS = [
        'planejada' => 'Planejada', 'em_andamento' => 'Em andamento',
        'concluida' => 'Concluída', 'cancelada' => 'Cancelada',
    ];

    public function rota(): BelongsTo { return $this->belongsTo(Rota::class); }
    public function embarcacao(): BelongsTo { return $this->belongsTo(Embarcacao::class); }
    public function encomendas(): HasMany { return $this->hasMany(Encomenda::class); }

    public function titulo(): string
    {
        return $this->nome ?: ('Viagem '.str_pad((string) $this->numero, 4, '0', STR_PAD_LEFT));
    }

    public function numeroFormatado(): string
    {
        return 'V-'.str_pad((string) $this->numero, 4, '0', STR_PAD_LEFT);
    }
}
