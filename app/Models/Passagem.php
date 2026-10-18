<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

/** Pré-pronto: módulo futuro (ligado por flag em empresa.recursos). */
class Passagem extends Model
{
    use PertenceEmpresa;

    protected $table = 'passagens';
    protected $guarded = [];
}
