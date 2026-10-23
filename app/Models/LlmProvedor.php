<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;

class LlmProvedor extends Model
{
    use PertenceEmpresa;

    protected $table = 'llm_provedores';
    protected $guarded = [];
    protected $casts = ['ativo' => 'boolean', 'config' => 'array'];

    public const PROVIDERS = ['claude' => 'Claude (Anthropic)', 'openai' => 'OpenAI'];

    public function rotulo(): string
    {
        return $this->nome.' · '.(self::PROVIDERS[$this->provider] ?? $this->provider).($this->modelo ? ' ('.$this->modelo.')' : '');
    }
}
