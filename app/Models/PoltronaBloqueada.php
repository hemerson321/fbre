<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class PoltronaBloqueada extends Model
{
    use PertenceEmpresa;

    protected $table = 'poltronas_bloqueadas';
    protected $guarded = [];
    protected $casts = ['data_viagem' => 'date', 'ativo' => 'boolean', 'escopo_id' => 'integer'];

    public function embarcacao(): BelongsTo { return $this->belongsTo(Embarcacao::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Usuário alvo quando escopo_tipo = usuario. */
    public function alvo(): BelongsTo { return $this->belongsTo(User::class, 'escopo_id'); }

    protected static ?bool $temEscopo = null;

    protected static function temEscopo(): bool
    {
        if (self::$temEscopo === null) {
            self::$temEscopo = Schema::hasColumn('poltronas_bloqueadas', 'escopo_tipo');
        }
        return self::$temEscopo;
    }

    /**
     * Só as travas que valem para um canal de venda.
     * $canal: 'operador' | 'revendedor' | 'ia'. $userId = quem está vendendo (pra travas por pessoa).
     */
    public function scopeAplicaveis($q, string $canal, ?int $userId = null)
    {
        if (! self::temEscopo()) return $q; // antes da migração: toda trava vale (comportamento antigo)

        $grupo = match ($canal) {
            'operador'   => 'operadores',
            'revendedor' => 'revendedores',
            'ia'         => 'ia',
            default      => '__none__',
        };

        return $q->where(function ($w) use ($grupo, $canal, $userId) {
            $w->whereIn('escopo_tipo', ['todos', $grupo]);
            if ($userId && $canal !== 'ia') {
                $w->orWhere(fn ($x) => $x->where('escopo_tipo', 'usuario')->where('escopo_id', $userId));
            }
        });
    }

    /** Texto curto de pra quem a trava vale. */
    public function escopoLabel(): string
    {
        return match ($this->escopo_tipo ?? 'todos') {
            'operadores'   => 'só operadores (balcão)',
            'revendedores' => 'só revendedores/agentes',
            'ia'           => 'só a IA (WhatsApp)',
            'usuario'      => 'só '.(optional($this->alvo)->name ?: ('usuário #'.$this->escopo_id)),
            default        => 'todos',
        };
    }
}
