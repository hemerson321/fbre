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
            'ativo' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }

    public function podeCadastrar(): bool { return $this->admin || $this->perm_cadastra; }
    public function podeBaixar(): bool    { return $this->admin || $this->perm_baixa; }
    public function podeVerCaixa(): bool  { return $this->admin || $this->perm_caixa; }
    public function isAdmin(): bool       { return $this->admin; }
    public function isSuper(): bool       { return $this->super_admin; }
}
