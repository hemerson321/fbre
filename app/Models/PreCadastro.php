<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PreCadastro extends Model
{
    use PertenceEmpresa;

    protected $table = 'pre_cadastros';
    protected $guarded = [];
    protected $casts = ['itens' => 'array'];

    /** Código curto e fácil de ditar (sem letras/números ambíguos). */
    public static function gerarCodigo(int $empresaId): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $c = '';
            for ($i = 0; $i < 4; $i++) $c .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        } while (static::withoutGlobalScopes()->where('empresa_id', $empresaId)->where('codigo', $c)->exists());

        return $c;
    }

    public function resumoItens(): string
    {
        return collect($this->itens ?? [])
            ->map(fn ($i) => (($i['quantidade'] ?? 1) . 'x ' . ($i['descricao'] ?? '')))
            ->implode(', ');
    }
}
