<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class Estacao extends Model
{
    use PertenceEmpresa;

    protected $table = 'estacoes';
    protected $guarded = [];
}
