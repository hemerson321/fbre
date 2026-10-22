<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class WhatsappConexao extends Model
{
    use PertenceEmpresa;

    protected $table = 'whatsapp_conexoes';
    protected $guarded = [];
    protected $casts = ['ativo' => 'boolean'];

    public const TIPOS = ['nao_oficial' => 'Não oficial (Evolution API)', 'oficial' => 'Oficial (Meta Cloud API)'];

    public function rotulo(): string
    {
        return $this->nome.' · '.(self::TIPOS[$this->tipo] ?? $this->tipo);
    }
}
