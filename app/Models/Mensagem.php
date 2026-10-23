<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mensagem extends Model
{
    use PertenceEmpresa;

    protected $table = 'mensagens';
    protected $guarded = [];
    protected $casts = ['lida' => 'boolean', 'meta' => 'array'];

    public function conversa(): BelongsTo { return $this->belongsTo(Conversa::class); }
    public function autor(): BelongsTo     { return $this->belongsTo(User::class, 'user_id'); }

    public function doCliente(): bool { return $this->direcao === 'entrada'; }
}
