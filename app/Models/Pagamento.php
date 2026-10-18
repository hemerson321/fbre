<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pagamento extends Model
{
    use PertenceEmpresa;

    protected $guarded = [];
    protected $casts = ['estornado' => 'boolean', 'valor' => 'decimal:2'];

    public const MEIOS = [
        'dinheiro' => 'Dinheiro',
        'pix'      => 'PIX',
        'debito'   => 'Débito',
        'credito'  => 'Crédito',
        'desconto' => 'Desconto',
    ];

    public function encomenda(): BelongsTo { return $this->belongsTo(Encomenda::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
