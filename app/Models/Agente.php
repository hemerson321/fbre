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
    public function llmEscalonar(): BelongsTo { return $this->belongsTo(LlmProvedor::class, 'llm_escalonar_id'); }
    public function conexao(): BelongsTo     { return $this->belongsTo(WhatsappConexao::class, 'whatsapp_conexao_id'); }
    public function pagamentoApi(): BelongsTo { return $this->belongsTo(PagamentoApi::class, 'pagamento_api_id'); }

    public function regra(string $k): bool { return (bool) ($this->regras[$k] ?? false); }

    /** Minutos sem resposta do cliente até o aviso de encerramento. 0 = não encerra sozinho. Padrão 2. */
    public function ociosoAvisoMin(): int { return max(0, min(120, (int) ($this->regras['ocioso_aviso_min'] ?? 2))); }
    /** Minutos a mais, depois do aviso, até encerrar a conversa. Padrão 1. */
    public function ociosoEncerrarMin(): int { return max(1, min(60, (int) ($this->regras['ocioso_encerrar_min'] ?? 1))); }
    /** Com PIX gerado e não pago: quantos minutos aguardar o pagamento antes de poder encerrar. Padrão 30. */
    public function ociosoPixMin(): int { return max(1, min(240, (int) ($this->regras['ocioso_pix_min'] ?? 30))); }
    /** Quanto tempo a poltrona reservada pelo agente fica segura (cobre aviso + encerramento). */
    public function reservaMin(): int { return max(5, $this->ociosoAvisoMin() + $this->ociosoEncerrarMin() + 2); }

    /** Manda no WhatsApp a imagem do mapa da lancha com as poltronas do cliente? Padrão: sim. */
    public function enviaMapaPoltrona(): bool { return (bool) ($this->regras['mapa_imagem'] ?? true); }

    /** Antecedência máxima de venda (meses à frente). Padrão 2. */
    public function mesesVenda(): int { return max(1, min(12, (int) ($this->regras['meses_venda'] ?? 2))); }

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
