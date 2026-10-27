<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Frete extends Model
{
    use PertenceEmpresa;

    protected $table = 'fretes';
    protected $guarded = [];

    protected $casts = [
        'entregue' => 'boolean', 'cancelada' => 'boolean', 'origem_offline' => 'boolean',
        'data_viagem' => 'date', 'entregue_em' => 'datetime', 'criada_no_aparelho_em' => 'datetime',
        'peso' => 'decimal:2', 'preco_kilo' => 'decimal:2', 'subtotal' => 'decimal:2',
        'desconto' => 'decimal:2', 'total' => 'decimal:2', 'pago' => 'decimal:2', 'devido' => 'decimal:2',
        'nf_dados' => 'array',
    ];

    public const FORMAS = ['avista' => 'À vista', 'areceber' => 'A receber', 'faturado' => 'Faturado'];
    public const MODULO = 'frete';

    /** Quem é o responsável pelo pagamento (padrão: destinatário — geralmente a loja que recebe). */
    public function pagadorNome(): string
    {
        return ($this->quem_paga === 'remetente' ? $this->remetente_nome : $this->destinatario_nome) ?: '—';
    }

    public function pagadorTelefone(): ?string
    {
        return $this->quem_paga === 'remetente' ? $this->remetente_telefone : $this->destinatario_telefone;
    }

    public function veiculos(): HasMany { return $this->hasMany(FreteVeiculo::class); }
    public function pagamentos(): HasMany { return $this->hasMany(Pagamento::class, 'origem_id')->where('modulo', self::MODULO)->where('estornado', false); }
    public function embarcacao(): BelongsTo { return $this->belongsTo(Embarcacao::class); }
    public function viagem(): BelongsTo { return $this->belongsTo(Viagem::class); }
    public function rota(): BelongsTo { return $this->belongsTo(Rota::class); }
    public function operador(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function entreguePor(): BelongsTo { return $this->belongsTo(User::class, 'entregue_por'); }

    public function cor(): string
    {
        if ($this->cancelada) return 'cinza';
        if ($this->status_pagamento === 'pago' && $this->entregue) return 'verde';
        if ($this->status_pagamento === 'pago') return 'amarelo';
        return 'vermelho';
    }

    public function statusTexto(): string
    {
        if ($this->cancelada) return 'Cancelado';
        return match (true) {
            $this->status_pagamento === 'pago' && $this->entregue => 'Pago e entregue',
            $this->status_pagamento === 'pago' => 'Pago · aguardando entrega',
            $this->status_pagamento === 'parcial' => 'Pagamento parcial',
            $this->entregue => 'Entregue · não pago',
            default => 'Não pago',
        };
    }

    public function recalcular(): void
    {
        $subtotal = (float) $this->subtotal;
        $descontoPag = (float) $this->pagamentos()->where('meio', 'desconto')->sum('valor');
        $desconto = max((float) $this->desconto, $descontoPag);
        $total = max($subtotal - $desconto, 0);
        $pago = (float) $this->pagamentos()->where('meio', '!=', 'desconto')->sum('valor');
        $devido = max($total - $pago, 0);
        $status = $devido <= 0.009 ? 'pago' : ($pago > 0 ? 'parcial' : 'pendente');
        $this->forceFill(['desconto' => $desconto, 'total' => $total, 'pago' => $pago, 'devido' => $devido, 'status_pagamento' => $status])->save();
    }

    public function numeroFormatado(): string
    {
        return $this->numero_completo ?: str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT);
    }

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
            ->when(isset($f['entregue']) && $f['entregue'] !== '', fn ($q) => $q->where('entregue', (bool) $f['entregue']))
            ->when($f['forma'] ?? null, fn ($q, $v) => $q->where('forma', $v))
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['busca'] ?? null, function ($q, $v) {
                $v = trim($v);
                $q->where(function ($q) use ($v) {
                    $q->where('remetente_nome', 'like', "%$v%")->orWhere('destinatario_nome', 'like', "%$v%")
                      ->orWhere('descricao', 'like', "%$v%")->orWhere('numero_completo', 'like', "%$v%");
                    if (ctype_digit($v)) $q->orWhere('numero', (int) $v);
                });
            })
            ->where('cancelada', false);
    }
}
