<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class MarketingAudio extends Model
{
    use PertenceEmpresa;

    protected $table = 'marketing_audios';
    protected $guarded = [];
    protected $casts = ['ativo' => 'boolean'];

    /** URL do arquivo de áudio (quando tipo = arquivo). */
    public function url(): ?string
    {
        $v = (string) ($this->arquivo ?? '');
        if ($v === '') return null;
        return preg_match('#^https?://#i', $v) ? $v : asset($v);
    }

    /** Formato que o terminal (player) entende. */
    public function paraPlayer(string $oid): array
    {
        return [
            'oid' => $oid,
            'id' => $this->id,
            'tipo' => $this->tipo,
            'nome' => (string) $this->nome,
            'texto' => (string) $this->texto,
            'voz' => (string) $this->voz,
            'url' => $this->tipo === 'arquivo' ? $this->url() : null,
        ];
    }
}
