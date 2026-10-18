<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evento extends Model
{
    use PertenceEmpresa;

    protected $guarded = [];
    protected $casts = ['dados' => 'array'];

    public function encomenda(): BelongsTo { return $this->belongsTo(Encomenda::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public static function registrar(string $tipo, ?Encomenda $encomenda = null, array $dados = []): self
    {
        return static::create([
            'encomenda_id' => $encomenda?->id,
            'user_id' => auth()->id(),
            'tipo' => $tipo,
            'dados' => $dados,
        ]);
    }
}
