<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Prestação de contas com um proprietário (balcão) ou agente externo.
 * Cada "Fechar prestação" congela um ciclo (snapshot via `lote`) e o separa das vendas atuais.
 * Permite pagamento parcial (valor_pago acumula; saldo pendente até quitar).
 */
class Prestacao extends Model
{
    use PertenceEmpresa;

    protected $table = 'prestacoes';
    protected $guarded = [];
    protected $casts = [
        'total_bruto' => 'decimal:2', 'comissao_pct' => 'decimal:2', 'comissao' => 'decimal:2',
        'total_devido' => 'decimal:2', 'valor_pago' => 'decimal:2', 'saldo' => 'decimal:2',
        'desfeita' => 'boolean', 'fechada_em' => 'datetime', 'desfeita_em' => 'datetime', 'data_viagem' => 'date',
    ];

    public function embarcacao(): BelongsTo { return $this->belongsTo(Embarcacao::class); }
    public function viagem(): BelongsTo { return $this->belongsTo(Viagem::class); }
    public function revendedor(): BelongsTo { return $this->belongsTo(User::class, 'revendedor_id'); }
    public function fechadaPor(): BelongsTo { return $this->belongsTo(User::class, 'fechada_por'); }
    public function passagens(): HasMany { return $this->hasMany(Passagem::class); }
    public function pagamentos(): HasMany { return $this->hasMany(Pagamento::class); }

    public function scopeAtivas($q) { return $q->where('desfeita', false); }

    public function recalcularSaldo(): void
    {
        $this->saldo = round((float) $this->total_devido - (float) $this->valor_pago, 2);
        if ($this->saldo < 0) $this->saldo = 0;
        $this->status = $this->valor_pago <= 0 ? 'pendente' : ($this->saldo > 0 ? 'parcial' : 'acertada');
    }

    public function statusLabel(): string
    {
        return ['pendente' => 'Pendente', 'parcial' => 'Parcial', 'acertada' => 'Acertada'][$this->status] ?? $this->status;
    }

    /** Próximo número sequencial por empresa (com trava pra não duplicar). */
    public static function proximoNumero(): int
    {
        return (int) static::withoutGlobalScopes()->where('empresa_id', Tenant::id())->max('numero') + 1;
    }
}
