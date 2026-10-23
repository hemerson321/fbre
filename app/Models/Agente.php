<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agente extends Model
{
    use PertenceEmpresa;

    protected $table = 'agentes';
    protected $guarded = [];
    protected $casts = [
        'ativo' => 'boolean',
        'regras' => 'array',
        'horario' => 'array',
        'recupera_funil' => 'boolean',
    ];

    public const ASSUMIR = [
        'sempre'       => 'Já atende sempre',
        'apos_min'     => 'Só se o humano não responder',
        'fora_horario' => 'Só fora do horário humano',
    ];

    public function llm(): BelongsTo        { return $this->belongsTo(LlmProvedor::class, 'llm_provedor_id'); }
    public function conexao(): BelongsTo     { return $this->belongsTo(WhatsappConexao::class, 'whatsapp_conexao_id'); }
    public function pagamentoApi(): BelongsTo { return $this->belongsTo(PagamentoApi::class, 'pagamento_api_id'); }

    public function regra(string $k): bool { return (bool) ($this->regras[$k] ?? false); }

    /** Está no horário de trabalho configurado? */
    public function noHorario(?\DateTimeInterface $agora = null): bool
    {
        $h = $this->horario ?? [];
        if (($h['modo'] ?? '24h') === '24h') return true;
        $agora = $agora ?: now();
        $dias = $h['dias'] ?? [1, 2, 3, 4, 5, 6, 7];
        $dow = (int) $agora->format('N'); // 1=seg..7=dom
        if (! in_array($dow, $dias)) return false;
        $ini = $h['inicio'] ?? '00:00';
        $fim = $h['fim'] ?? '23:59';
        $hm = $agora->format('H:i');
        return $hm >= $ini && $hm <= $fim;
    }
}
