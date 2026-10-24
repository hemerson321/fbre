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
                $this->criarPassagem($conv, $cob); // best-effort: cadastra a passagem no sistema
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
    /** Cadastra a passagem no sistema a partir dos dados da venda coletados na conversa. */
    protected function criarPassagem(Conversa $conv, Cobranca $cob): void
    {
        try {
            $ctx = $conv->contexto ?? [];
            if (! empty($ctx['passagem_id'])) return; // já criada
            $venda = $ctx['venda'] ?? null;
            if (! $venda || empty($venda['origem']) || empty($venda['destino'])) return;

            $paxList = $venda['passageiros'] ?? ($ctx['passageiros'] ?? []);
            if (! $paxList) $paxList = [['nome' => $conv->cliente_nome ?: 'Passageiro']];
            $n = max(count($paxList), 1);
            $unit = round(((float) $cob->valor) / $n, 2);

            $passageiros = array_map(fn ($px) => [
                'nome' => $px['nome'] ?? 'Passageiro',
                'tipo' => 'Adulto',
                'documento' => $px['documento'] ?? null,
                'nascimento' => $this->dataIso($px['nascimento'] ?? null),
                'valor_passagem' => $unit,
                'valor_integral' => $unit,
            ], $paxList);

            $op = \App\Models\User::withoutGlobalScopes()
                ->where('empresa_id', $conv->empresa_id)
                ->orderByDesc('admin')->orderByDesc('super_admin')->first();
            if (! $op) return;

            $dados = [
                'origem' => $venda['origem'],
                'destino' => $venda['destino'],
                'data_viagem' => $this->dataIso($venda['data'] ?? null),
                'comprador_nome' => $conv->cliente_nome,
                'comprador_telefone' => $conv->cliente_telefone,
                'forma' => 'avista',
                'passageiros' => $passageiros,
            ];

            $p = $this->pas->criar($dados, (int) $op->id);
            $this->pas->baixa($p, ['pix' => (float) $cob->valor], (int) $op->id);

            $ctx['passagem_id'] = $p->id;
            $conv->forceFill(['contexto' => $ctx])->save();
            $cob->forceFill(['referencia_tipo' => \App\Models\Passagem::class, 'referencia_id' => $p->id])->save();
        } catch (\Throwable $e) {
            Log::warning('criarPassagem (agente) erro: '.$e->getMessage());
        }
    }

    /** dd/mm/aaaa -> aaaa-mm-dd (ou null). */
    protected function dataIso(?string $s): ?string
    {
        $s = trim((string) $s);
        if ($s === '') return null;
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $s, $m)) return $m[3].'-'.$m[2].'-'.$m[1];
        try { return \Carbon\Carbon::parse($s)->toDateString(); } catch (\Throwable $e) { return null; }
    }
}
