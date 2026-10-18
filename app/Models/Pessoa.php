<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class Pessoa extends Model
{
    use PertenceEmpresa;

    protected $table = 'pessoas';
    protected $guarded = [];
}
