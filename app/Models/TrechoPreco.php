<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class TrechoPreco extends Model
{
    use PertenceEmpresa;

    protected $table = 'trecho_precos';
    protected $guarded = [];
    protected $casts = ['tarifa' => 'decimal:2', 'alimentacao' => 'decimal:2', 'ativo' => 'boolean'];

    public static function chave(string $o, string $d, int $tipoId): string
    {
        return mb_strtolower(trim($o).'|'.trim($d).'|'.$tipoId);
    }
}
