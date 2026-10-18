<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    use PertenceEmpresa;

    protected $table = 'produtos';
    protected $guarded = [];
}
