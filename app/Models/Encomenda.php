<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Encomenda extends Model
{
    use PertenceEmpresa;

    protected $guarded = [];

    protected $casts = [
        'entregue' => 'boolean',
        'cancelada' => 'boolean',
        'entregue_em' => 'datetime',
        'subtotal' => 'decimal:2',
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

    // ---------------- relações ----------------
    public function itens(): HasMany { return $this->hasMany(EncomendaItem::class); }
    public function pagamentos(): HasMany { return $this->hasMany(Pagamento::class)->where('estornado', false); }
    public function eventos(): HasMany { return $this->hasMany(Evento::class); }
    public function embarcacao(): BelongsTo { return $this->belongsTo(Embarcacao::class); }
    public function operador(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function entreguePor(): BelongsTo { return $this->belongsTo(User::class, 'entregue_por'); }

    // ---------------- status ----------------
    /** verde = pago+entregue | amarelo = pago, não entregue | vermelho = não pago */
    public function cor(): string
    {
        if ($this->cancelada) return 'cinza';
        if ($this->status_pagamento === 'pago' && $this->entregue) return 'verde';
        if ($this->status_pagamento === 'pago') return 'amarelo';
        return 'vermelho';
    }

    public function statusTexto(): string
    {
        if ($this->cancelada) return 'Cancelada';
        return match (true) {
            $this->status_pagamento === 'pago' && $this->entregue => 'Pago e entregue',
            $this->status_pagamento === 'pago' => 'Pago · aguardando entrega',
            $this->status_pagamento === 'parcial' && $this->entregue => 'Entregue · pagamento parcial',
            $this->status_pagamento === 'parcial' => 'Pagamento parcial',
            $this->entregue => 'Entregue · não pago',
            default => 'Não pago',
        };
    }

    /** Recalcula totais a partir dos itens e pagamentos. */
    public function recalcular(): void
    {
        $subtotal = (float) $this->itens()->sum('valor_total');
        $descontoPag = (float) $this->pagamentos()->where('meio', 'desconto')->sum('valor');
        $desconto = max((float) $this->desconto, $descontoPag);
        $total = max($subtotal - $desconto, 0);
        $pago = (float) $this->pagamentos()->where('meio', '!=', 'desconto')->sum('valor');
        $devido = max($total - $pago, 0);

        $status = $devido <= 0.009 ? 'pago' : ($pago > 0 ? 'parcial' : 'pendente');

        $this->forceFill([
            'subtotal' => $subtotal,
            'desconto' => $desconto,
            'total' => $total,
            'pago' => $pago,
            'devido' => $devido,
            'status_pagamento' => $status,
        ])->save();
    }

    public function numeroFormatado(): string
    {
        return str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT);
    }

    // ---------------- filtros ----------------
    public function scopeFiltrar(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['inicio'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when($f['fim'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->when($f['origem'] ?? null, fn ($q, $v) => $q->where('origem', $v))
            ->when($f['destino'] ?? null, fn ($q, $v) => $q->where('destino', $v))
            ->when($f['embarcacao_id'] ?? null, fn ($q, $v) => $q->where('embarcacao_id', $v))
            ->when($f['status_pagamento'] ?? null, fn ($q, $v) => $q->where('status_pagamento', $v))
            ->when(isset($f['entregue']) && $f['entregue'] !== '', fn ($q) => $q->where('entregue', (bool) $f['entregue']))
            ->when($f['forma'] ?? null, fn ($q, $v) => $q->where('forma', $v))
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['busca'] ?? null, function ($q, $v) {
                $v = trim($v);
                $q->where(function ($q) use ($v) {
                    $q->where('remetente_nome', 'like', "%$v%")
                      ->orWhere('destinatario_nome', 'like', "%$v%")
                      ->orWhere('remetente_telefone', 'like', "%$v%")
                      ->orWhere('destinatario_telefone', 'like', "%$v%");
                    if (ctype_digit($v)) {
                        $q->orWhere('numero', (int) $v);
                    }
                });
            })
            ->where('cancelada', false);
    }
}
