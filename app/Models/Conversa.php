<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversa extends Model
{
    use PertenceEmpresa;

    protected $table = 'conversas';
    protected $guarded = [];
    protected $casts = [
        'atendida' => 'boolean',
        'ia_ativa' => 'boolean',
        'contexto' => 'array',
        'ultima_msg_em' => 'datetime',
        'cliente_respondeu_em' => 'datetime',
    ];

    public const STATUS = [
        'aberta'     => 'Aberta',
        'aguardando' => 'Aguardando atendimento',
        'ia'         => 'Com o agente',
        'humano'     => 'Com atendente',
        'fechada'    => 'Fechada',
    ];

    public function mensagens(): HasMany { return $this->hasMany(Mensagem::class)->orderBy('id'); }
    public function conexao(): BelongsTo  { return $this->belongsTo(WhatsappConexao::class, 'whatsapp_conexao_id'); }
    public function atendente(): BelongsTo { return $this->belongsTo(User::class, 'assumida_por'); }

    public function statusLabel(): string { return self::STATUS[$this->status] ?? $this->status; }

    public function cor(): string
    {
        return match ($this->status) {
            'aguardando' => 'amber',
            'humano'     => 'emerald',
            'ia'         => 'cyan',
            'fechada'    => 'slate',
            default      => 'blue',
        };
    }

    public function nomeExibicao(): string
    {
        return $this->cliente_nome ?: $this->cliente_telefone;
    }
}
