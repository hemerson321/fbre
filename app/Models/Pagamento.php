<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pagamento extends Model
{
    use PertenceEmpresa;

    protected $guarded = [];
    protected $casts = ['estornado' => 'boolean', 'valor' => 'decimal:2', 'pix_auto' => 'boolean'];

    public const MEIOS = [
        'dinheiro' => 'Dinheiro',
        'pix'      => 'PIX',
        'debito'   => 'Débito',
        'credito'  => 'Crédito',
        'desconto' => 'Desconto',
    ];

    /** Canais de recebimento (PIX dividido em Manual x Automático) — usado em caixa/relatórios/filtro. */
    public const CANAIS = [
        'dinheiro'   => 'Dinheiro',
        'pix_manual' => 'PIX Manual',
        'pix_auto'   => 'PIX Automático',
        'debito'     => 'Débito',
        'credito'    => 'Crédito',
    ];

    /** Expressão SQL que resolve o canal (divide o PIX por pix_auto). */
    public static function canalSql(): string
    {
        return "CASE WHEN meio='pix' AND COALESCE(pix_auto,0)=1 THEN 'pix_auto' WHEN meio='pix' THEN 'pix_manual' ELSE meio END";
    }

    /** Canal deste pagamento (dinheiro, pix_manual, pix_auto, debito, credito). */
    public function getCanalAttribute(): string
    {
        if ($this->meio === 'pix') return $this->pix_auto ? 'pix_auto' : 'pix_manual';
        return (string) $this->meio;
    }

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
