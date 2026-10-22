<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class Captura extends Model
{
    use PertenceEmpresa;

    protected $table = 'capturas';
    protected $guarded = [];
    protected $casts = ['nascimento' => 'date', 'dados' => 'array'];
}
