<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class PagamentoApi extends Model
{
    use PertenceEmpresa;

    protected $table = 'pagamento_apis';
    protected $guarded = [];
    protected $casts = ['ativo' => 'boolean', 'config' => 'array'];

    public const PROVIDERS = ['mercadopago' => 'Mercado Pago'];

    public function rotulo(): string
    {
        return $this->nome.' · '.(self::PROVIDERS[$this->provider] ?? $this->provider);
    }
}
