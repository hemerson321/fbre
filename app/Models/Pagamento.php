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

    /** Modelo de origem do pagamento conforme o módulo. */
    public function fonte()
    {
        return match ($this->modulo) {
            'passagem' => $this->origem_id ? Passagem::find($this->origem_id) : null,
            'encomenda' => $this->encomenda_id ? ($this->relationLoaded('encomenda') ? $this->encomenda : Encomenda::find($this->encomenda_id)) : null,
            default => null,
        };
    }

    /** [numero, nome, url, rotulo, cor] para exibição no caixa. */
    public function resumoFonte(): array
    {
        $mod = $this->modulo ?: 'encomenda';
        $rotulo = \App\Support\Modulos::rotulo($mod);
        $cor = \App\Support\Modulos::cor($mod);
        $f = $this->fonte();
        if (! $f) return ['numero' => null, 'nome' => $this->descricao, 'url' => null, 'rotulo' => $rotulo, 'cor' => $cor];
        return [
            'numero' => method_exists($f, 'numeroFormatado') ? $f->numeroFormatado() : $f->id,
            'nome' => $f->destinatario_nome ?? $f->comprador_nome ?? $f->descricao ?? null,
            'url' => $mod === 'passagem' ? route('passagens.show', $f->id) : ($mod === 'encomenda' ? route('encomendas.show', $f->id) : null),
            'rotulo' => $rotulo, 'cor' => $cor,
        ];
    }
}
