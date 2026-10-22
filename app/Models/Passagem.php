<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Passagem extends Model
{
    use PertenceEmpresa;

    protected $table = 'passagens';
    protected $guarded = [];

    protected $casts = [
        'embarcou' => 'boolean',
        'cancelada' => 'boolean',
        'origem_offline' => 'boolean',
        'data_viagem' => 'date',
        'embarcou_em' => 'datetime',
        'criada_no_aparelho_em' => 'datetime',
        'subtotal' => 'decimal:2',
        'taxa_total' => 'decimal:2',
        'alimentacao_total' => 'decimal:2',
        'desconto' => 'decimal:2',
        'total' => 'decimal:2',
        'pago' => 'decimal:2',
        'devido' => 'decimal:2',
    ];

    public const FORMAS = [
        'avista'   => 'À vista',
        'areceber' => 'A receber',
        'faturado' => 'Faturado',
    ];

    public const MODULO = 'passagem';

    // ---------------- relações ----------------
    public function passageiros(): HasMany { return $this->hasMany(PassagemPassageiro::class); }
    public function pagamentos(): HasMany { return $this->hasMany(Pagamento::class, 'origem_id')->where('modulo', self::MODULO)->where('estornado', false); }
    public function embarcacao(): BelongsTo { return $this->belongsTo(Embarcacao::class); }
    public function viagem(): BelongsTo { return $this->belongsTo(Viagem::class); }
    public function rota(): BelongsTo { return $this->belongsTo(Rota::class); }
    public function operador(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function embarcouPor(): BelongsTo { return $this->belongsTo(User::class, 'embarcou_por'); }

    // ---------------- status ----------------
    public function cor(): string
    {
        if ($this->cancelada) return 'cinza';
        if ($this->status_pagamento === 'pago' && $this->embarcou) return 'verde';
        if ($this->status_pagamento === 'pago') return 'amarelo';
        return 'vermelho';
    }

    public function statusTexto(): string
    {
        if ($this->cancelada) return 'Cancelada';
        return match (true) {
            $this->status_pagamento === 'pago' && $this->embarcou => 'Pago e embarcou',
            $this->status_pagamento === 'pago' => 'Pago · aguardando embarque',
            $this->status_pagamento === 'parcial' => 'Pagamento parcial',
            $this->embarcou => 'Embarcou · não pago',
            default => 'Não pago',
        };
    }

    /** Recalcula totais a partir dos passageiros e pagamentos. */
    public function recalcular(): void
    {
        $subtotal = (float) $this->passageiros()->sum('valor_passagem');
        $taxa = (float) $this->passageiros()->sum('valor_taxa');
        $alim = (float) $this->passageiros()->sum('valor_alimentacao');
        $descontoPag = (float) $this->pagamentos()->where('meio', 'desconto')->sum('valor');
        $desconto = max((float) $this->desconto, $descontoPag);
        $total = max($subtotal + $taxa + $alim - $desconto, 0);
        $pago = (float) $this->pagamentos()->where('meio', '!=', 'desconto')->sum('valor');
        $devido = max($total - $pago, 0);
        $status = $devido <= 0.009 ? 'pago' : ($pago > 0 ? 'parcial' : 'pendente');

        $this->forceFill([
            'subtotal' => $subtotal,
            'taxa_total' => $taxa,
            'alimentacao_total' => $alim,
            'desconto' => $desconto,
            'total' => $total,
            'pago' => $pago,
            'devido' => $devido,
            'qtd_passageiros' => $this->passageiros()->count(),
            'status_pagamento' => $status,
        ])->save();
    }

    public function numeroFormatado(): string
    {
        return $this->numero_completo ?: str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT);
    }

    // ---------------- filtros ----------------
    public function scopeFiltrar(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['inicio'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v.' '.(($f['hora_inicio'] ?? '') ?: '00:00').':00'))
            ->when($f['fim'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v.' '.(($f['hora_fim'] ?? '') ?: '23:59').':59'))
            ->when($f['origem'] ?? null, fn ($q, $v) => $q->where('origem', $v))
            ->when($f['destino'] ?? null, fn ($q, $v) => $q->where('destino', $v))
            ->when($f['embarcacao_id'] ?? null, fn ($q, $v) => $q->where('embarcacao_id', $v))
            ->when($f['viagem_id'] ?? null, fn ($q, $v) => $q->where('viagem_id', $v))
            ->when($f['status_pagamento'] ?? null, fn ($q, $v) => $q->where('status_pagamento', $v))
            ->when(isset($f['embarcou']) && $f['embarcou'] !== '', fn ($q) => $q->where('embarcou', (bool) $f['embarcou']))
            ->when($f['forma'] ?? null, fn ($q, $v) => $q->where('forma', $v))
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['busca'] ?? null, function ($q, $v) {
                $v = trim($v);
                $q->where(function ($q) use ($v) {
                    $q->where('comprador_nome', 'like', "%$v%")
                      ->orWhere('comprador_telefone', 'like', "%$v%")
                      ->orWhere('numero_completo', 'like', "%$v%");
                    if (ctype_digit($v)) $q->orWhere('numero', (int) $v);
                });
            })
            ->where('cancelada', false);
    }
}
