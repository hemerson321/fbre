<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Preço de um tipo de camarote por trecho (origem→destino). */
class CamarotePreco extends Model
{
    use PertenceEmpresa;

    protected $table = 'camarote_precos';
    protected $guarded = [];
    protected $casts = ['valor' => 'decimal:2'];

    public function camarote(): BelongsTo { return $this->belongsTo(Camarote::class); }

    /** Acha o preço do camarote para o trecho, nos dois sentidos. */
    public static function para(int $camaroteId, string $origem, string $destino): ?self
    {
        $exato = static::where('camarote_id', $camaroteId)->where('origem', $origem)->where('destino', $destino)->first();
        if ($exato) return $exato;
        return static::where('camarote_id', $camaroteId)->where('origem', $destino)->where('destino', $origem)->first();
    }
}
