<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class Embarcacao extends Model
{
    use PertenceEmpresa;

    protected $table = 'embarcacoes';
    protected $guarded = [];
    protected $casts = ['ativo' => 'boolean', 'usa_poltrona' => 'boolean', 'poltronas_qtd' => 'integer', 'poltronas_colunas' => 'integer', 'cancel_antes_pct' => 'integer', 'cancel_depois_pct' => 'integer', 'comissao_empresa_pct' => 'integer', 'tripulantes' => 'array', 'ficha' => 'array', 'regra_idade' => 'array', 'gratuito_qtd' => 'integer', 'pcd_qtd' => 'integer', 'idoso_meia_qtd' => 'integer'];

    public function dono() { return $this->belongsTo(User::class, 'dono_id'); }

    /** % que a empresa retem no cancelamento ANTES da viagem (deste transporte, ou padrao da empresa). */
    public function cancelAntes(): int
    {
        return $this->cancel_antes_pct ?? (int) (\App\Support\Tenant::empresa()?->config('cancel_antes_pct', 10));
    }

    /** % retido DEPOIS da viagem. */
    public function cancelDepois(): int
    {
        return $this->cancel_depois_pct ?? (int) (\App\Support\Tenant::empresa()?->config('cancel_depois_pct', 30));
    }

    /** Bandas de idade próprias do transporte (override), ou null = herda da empresa. */
    public function bandasIdade(): ?array
    {
        $r = $this->regra_idade;
        return (is_array($r) && count($r)) ? array_values($r) : null;
    }
}
