<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SignageTela extends Model
{
    use PertenceEmpresa;

    protected $table = 'signage_telas';
    protected $guarded = [];
    protected $casts = ['ativo' => 'boolean', 'visto_em' => 'datetime'];

    public function midias(): HasMany
    {
        return $this->hasMany(SignageMidia::class, 'tela_id')->orderBy('ordem')->orderBy('id');
    }

    public function vertical(): bool { return $this->orientacao === 'vertical'; }

    /** Player buscou a programação nos últimos 2 minutos = tela ligada. */
    public function online(): bool { return $this->visto_em && $this->visto_em->gt(now()->subMinutes(2)); }
}
