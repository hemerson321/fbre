<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class Embarcacao extends Model
{
    use PertenceEmpresa;

    protected $table = 'embarcacoes';
    protected $guarded = [];
}
