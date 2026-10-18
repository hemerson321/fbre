<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class Setor extends Model
{
    use PertenceEmpresa;

    protected $table = 'setores';
    protected $guarded = [];
}
