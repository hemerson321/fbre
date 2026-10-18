<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class Localidade extends Model
{
    use PertenceEmpresa;

    protected $table = 'localidades';
    protected $guarded = [];
}
