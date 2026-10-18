<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Empresa extends Model
{
    protected $guarded = [];

    protected $casts = [
        'recursos' => 'array',
        'config' => 'array',
        'validade' => 'date',
        'ativo' => 'boolean',
    ];

    public function users(): HasMany { return $this->hasMany(User::class); }
    public function embarcacoes(): HasMany { return $this->hasMany(Embarcacao::class); }
    public function localidades(): HasMany { return $this->hasMany(Localidade::class); }
    public function produtos(): HasMany { return $this->hasMany(Produto::class); }
    public function encomendas(): HasMany { return $this->hasMany(Encomenda::class); }

    public function temRecurso(string $recurso): bool
    {
        return in_array($recurso, $this->recursos ?? [], true);
    }

    public function config(string $chave, $padrao = null)
    {
        return data_get($this->config, $chave, $padrao);
    }

    public function vencida(): bool
    {
        return $this->validade && $this->validade->isPast();
    }

    public function bloqueada(): bool
    {
        return ! $this->ativo || $this->vencida();
    }

    /** Gera o próximo número de encomenda de forma atômica. */
    public function proximoNumero(): int
    {
        return DB::transaction(function () {
            $e = static::lockForUpdate()->find($this->id);
            $n = $e->proximo_numero;
            $e->update(['proximo_numero' => $n + 1]);
            return $n;
        });
    }

    public function nomeExibicao(): string
    {
        return $this->nome_fantasia ?: $this->nome;
    }
}
