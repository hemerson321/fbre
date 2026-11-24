<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignageMidia extends Model
{
    use PertenceEmpresa;

    protected $table = 'signage_midias';
    protected $guarded = [];
    protected $casts = ['ativo' => 'boolean', 'som' => 'boolean', 'horarios' => 'array', 'dias' => 'array', 'data_inicio' => 'date', 'data_fim' => 'date'];

    public const LAYOUTS = [
        1 => 'Aviso', 2 => 'Urgente', 3 => 'Informativo', 4 => 'Boas-vindas', 5 => 'Elegante',
        6 => 'Gradiente', 7 => 'Promoção', 8 => 'Embarque', 9 => 'Clean', 10 => 'Neon',
    ];

    public function tela(): BelongsTo { return $this->belongsTo(SignageTela::class, 'tela_id'); }

    public function url(?string $campo = 'arquivo'): ?string
    {
        $v = (string) ($this->{$campo} ?? '');
        if ($v === '') return null;
        return preg_match('#^https?://#i', $v) ? $v : asset($v);
    }

    /** Dentro do período de validade (datas)? */
    public function vigente(): bool
    {
        $hoje = now()->startOfDay();
        if ($this->data_inicio && $this->data_inicio->gt($hoje)) return false;
        if ($this->data_fim && $this->data_fim->lt($hoje)) return false;
        return true;
    }

    /** Formato que o player entende. */
    public function paraPlayer(): array
    {
        return [
            'id' => $this->id, 'tipo' => $this->tipo, 'titulo' => (string) $this->titulo, 'texto' => (string) $this->texto,
            'layout' => (int) $this->layout, 'url' => $this->url('arquivo'), 'audio' => $this->url('audio'), 'som' => (bool) $this->som,
            'duracao' => (int) $this->duracao, 'modo' => $this->modo, 'intervalo' => (int) $this->intervalo_min,
            'horarios' => array_values((array) $this->horarios), 'dias' => array_map('intval', array_values((array) $this->dias)),
        ];
    }
}
