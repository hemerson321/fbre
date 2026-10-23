<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cobranca extends Model
{
    use PertenceEmpresa;

    protected $table = 'cobrancas';
    protected $guarded = [];
    protected $casts = ['valor' => 'decimal:2', 'pago_em' => 'datetime'];

    public function api(): BelongsTo
    {
        return $this->belongsTo(PagamentoApi::class, 'pagamento_api_id');
    }

    public function pago(): bool
    {
        return $this->status === 'pago';
    }
}
