<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'super_admin' => 'boolean',
            'admin' => 'boolean',
            'perm_cadastra' => 'boolean',
            'perm_baixa' => 'boolean',
            'perm_caixa' => 'boolean',
            'perm_filtro' => 'boolean',
            'perm_relatorio' => 'boolean',
            'perm_imprimir' => 'boolean',
            'ativo' => 'boolean',
            'loc_permitidas' => 'array',
            'setores_permitidos' => 'array',
            'transportes_permitidos' => 'array',
            'perm_retroativo' => 'boolean',
            'caixa_todos' => 'boolean',
            'relatorio_todos' => 'boolean',
        ];
    }

    public const PAPEIS = [
        'operador_barco' => 'Operador do Barco',
        'agencia_externa' => 'Agência Externa',
        'gerente' => 'Gerente',
    ];

    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }

    // ---- escopo do operador no cadastro (vazio = tudo liberado) ----
    public function cidadesPermitidas(): array    { return array_values($this->loc_permitidas ?? []); }
    public function setoresPermitidos(): array    { return array_values($this->setores_permitidos ?? []); }
    public function transportesPermitidos(): array { return array_map('intval', $this->transportes_permitidos ?? []); }
    public function podeRetroativo(): bool        { return $this->admin || $this->gerente() || $this->perm_retroativo; }
    public function gerente(): bool               { return $this->papel === 'gerente'; }
    public function caixaTodos(): bool            { return $this->admin || $this->gerente() || $this->caixa_todos; }
    public function relatorioTodos(): bool        { return $this->admin || $this->gerente() || $this->relatorio_todos; }
    public function papelNome(): string           { return self::PAPEIS[$this->papel] ?? '—'; }

    public function podeCadastrar(): bool { return $this->admin || $this->perm_cadastra; }
    public function podeBaixar(): bool    { return $this->admin || $this->perm_baixa; }
    public function podeVerCaixa(): bool  { return $this->admin || $this->perm_caixa; }
    public function podeFiltrar(): bool   { return $this->admin || $this->perm_filtro; }
    public function podeRelatorio(): bool { return $this->admin || $this->perm_relatorio; }
    public function podeImprimir(): bool  { return $this->admin || $this->perm_imprimir; }
    public function isAdmin(): bool       { return $this->admin; }
    public function isSuper(): bool       { return $this->super_admin; }
}
