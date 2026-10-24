<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\Conversa;
use App\Models\Encomenda;
use App\Models\Passagem;
use App\Support\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Confirma cobranças PIX: consulta o Mercado Pago, marca como paga, dá baixa
 * (encomenda/passagem) e AVISA o cliente no WhatsApp (quando veio do agente).
 * Usado pelo webhook do MP e pelo verificador agendado (a cada minuto).
 */
class ConfirmaPagamento
{
    public function __construct(
        protected MercadoPago $mp,
        protected Atendimento $at,
        protected EncomendaService $enc,
        protected PassagemService $pas,
    ) {}

    /** Varre cobranças pendentes recentes e aplica as que já foram pagas. Retorna quantas confirmou. */
    public function verificarPendentes(int $horas = 48): int
    {
        $n = 0;
        Cobranca::withoutGlobalScopes()
            ->where('status', 'pendente')
            ->whereNotNull('provider_payment_id')
            ->where('created_at', '>=', now()->subHours($horas))
            ->with('api')
            ->chunkById(100, function ($lote) use (&$n) {
                foreach ($lote as $cob) {
                    if (! $cob->api) continue;
                    Tenant::set($cob->empresa_id);
                    try {
                        $st = $this->mp->consultar($cob->api, (string) $cob->provider_payment_id);
                        if (($st['ok'] ?? false) && ($st['pago'] ?? false) && $this->aplicar($cob)) {
                            $n++;
                        }
                    } catch (\Throwable $e) {
                        Log::warning('verificarPendentes erro cobranca '.$cob->id.': '.$e->getMessage());
                    }
                }
            });
        return $n;
    }

    /** Marca paga + baixa + aviso. Retorna true se confirmou agora (não estava paga). */
    public function aplicar(Cobranca $cob): bool
    {
        if ($cob->status === 'pago') return false;
        $cob->forceFill(['status' => 'pago', 'pago_em' => now()])->save();
        Tenant::set($cob->empresa_id);

        $valorFmt = number_format((float) $cob->valor, 2, ',', '.');

        if ($cob->referencia_tipo === Conversa::class && $cob->referencia_id) {
            $conv = Conversa::withoutGlobalScopes()->find($cob->referencia_id);
            if ($conv) {
                $conv->forceFill(['funil' => 'pago'])->save();
                $msg = "✅ Pagamento confirmado! Recebi seu PIX de R$ {$valorFmt}. Sua passagem está garantida. Qualquer coisa, é só chamar. 🙏";
                try { $this->at->registrarSaida($conv, $msg, 'sistema'); } catch (\Throwable $e) {}
            }
        } elseif ($cob->referencia_tipo === Encomenda::class && $cob->referencia_id) {
            $e = Encomenda::withoutGlobalScopes()->find($cob->referencia_id);
            if ($e) $this->enc->baixa($e, ['pix' => (float) $cob->valor], (int) $e->user_id, 'mp-'.$cob->id);
        } elseif ($cob->referencia_tipo === Passagem::class && $cob->referencia_id) {
            $p = Passagem::withoutGlobalScopes()->find($cob->referencia_id);
            if ($p) $this->pas->baixa($p, ['pix' => (float) $cob->valor], (int) $p->user_id);
        }

        return true;
    }
}
