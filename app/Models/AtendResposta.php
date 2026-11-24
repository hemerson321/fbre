<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class AtendResposta extends Model
{
    use PertenceEmpresa;

    protected $table = 'atend_respostas';
    protected $guarded = [];
    protected $casts = ['passos' => 'array', 'ativo' => 'boolean'];
}
