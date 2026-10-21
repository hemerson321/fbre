<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class AvulsoCategoria extends Model
{
    use PertenceEmpresa;

    protected $table = 'avulso_categorias';
    protected $guarded = [];
    protected $casts = ['ativo' => 'boolean'];

    public const TIPOS = ['entrada' => 'Entrada', 'saida' => 'Saída', 'ambos' => 'Entrada e saída'];
}
