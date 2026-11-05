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

    public const PROVIDERS = [
        'mercadopago' => 'Mercado Pago (PIX dinâmico, confirma sozinho)',
        'pix_estatico' => 'PIX estático (chave — gera QR)',
    ];

    public function rotulo(): string
    {
        return $this->nome.' · '.($this->ehEstatico() ? 'PIX estático' : (self::PROVIDERS[$this->provider] ?? $this->provider));
    }

    /** True quando é só uma chave PIX (QR estático), sem gateway que confirma sozinho. */
    public function ehEstatico(): bool
    {
        return $this->provider === 'pix_estatico';
    }

    public function pixTipo(): string       { return $this->config['tipo'] ?? 'aleatoria'; }
    public function pixChave(): ?string     { return $this->config['chave'] ?? null; }
    public function pixRecebedor(): ?string { return $this->config['recebedor'] ?? null; }
    public function pixCidade(): ?string    { return $this->config['cidade'] ?? null; }
}
