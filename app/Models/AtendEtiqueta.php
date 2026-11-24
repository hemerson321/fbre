<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class AtendEtiqueta extends Model
{
    use PertenceEmpresa;

    protected $table = 'atend_etiquetas';
    protected $guarded = [];
    protected $casts = [];
}
