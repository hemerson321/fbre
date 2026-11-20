<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class ClienteFaturado extends Model
{
    use PertenceEmpresa;

    protected $table = 'clientes_faturado';
    protected $guarded = [];
    protected $casts = ['ativo' => 'boolean'];

    public function rotulo(): string
    {
        $e = trim((string) $this->empresa);
        return $e !== '' ? $this->nome.' · '.$e : $this->nome;
    }
}
