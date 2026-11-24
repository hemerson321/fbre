<?php

namespace App\Console\Commands;

use App\Models\Agente;
use App\Models\Cobranca;
use App\Models\Conversa;
use App\Services\Atendimento;
use App\Services\Vagas;
use App\Support\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Conversa do agente parada (cliente não respondeu):
 *  - 2 min sem resposta  -> avisa que vai encerrar em 1 minuto;
 *  - +1 min sem resposta -> solta a poltrona reservada, agradece e fecha a conversa.
 * Não mexe em conversa com atendente humano nem com PIX aguardando pagamento.
 */
class EncerrarConversasOciosas extends Command
{
    protected $signature = 'atendimento:ociosas';
    protected $description = 'Avisa e encerra conversas do agente sem resposta do cliente (solta a poltrona reservada)';

    public function handle(Atendimento $atendimento, Vagas $vagas): int
    {
        $avisos = 0; $fechadas = 0;
        Conversa::withoutGlobalScopes()
            ->where('status', 'ia')->where('ia_ativa', true)
            ->where(fn ($q) => $q->whereNull('precisa_humano')->orWhere('precisa_humano', false))
            ->where('ultima_msg_origem', '!=', 'cliente')
            ->whereBetween('ultima_msg_em', [now()->subHours(2), now()->subSeconds(55)])
            ->chunkById(100, function ($lote) use ($atendimento, $vagas, &$avisos, &$fechadas) {
                foreach ($lote as $c) {
                    try {
                        Tenant::set($c->empresa_id);
                        $ctx = $c->contexto ?? [];
                        $avisoEm = ! empty($ctx['ocioso_aviso_em']) ? \Illuminate\Support\Carbon::parse($ctx['ocioso_aviso_em']) : null;
                        // aviso antigo (cliente respondeu depois dele) não vale mais
                        if ($avisoEm && $c->cliente_respondeu_em && $c->cliente_respondeu_em->gt($avisoEm)) $avisoEm = null;

                        // PIX gerado e ainda não pago: o cliente está pagando — não encerra nem solta a poltrona
                        $pixPendente = Cobranca::withoutGlobalScopes()->where('referencia_tipo', Conversa::class)->where('referencia_id', $c->id)
                            ->where('status', 'pendente')->where('created_at', '>=', now()->subMinutes(30))->exists();
                        if ($pixPendente) continue;

                        $agente = Agente::withoutGlobalScopes()->where('empresa_id', $c->empresa_id)->where('modulo', $c->modulo)->where('ativo', true)->first();
                        if (! $agente || $agente->regra('desligado')) continue;

                        if (! $avisoEm) {
                            if ($c->ultima_msg_em->gt(now()->subMinutes(2))) continue; // ainda não deu 2 min
                            $ctx['ocioso_aviso_em'] = now()->toDateTimeString();
                            $c->forceFill(['contexto' => $ctx])->save();
                            $atendimento->registrarSaida($c, 'Ainda está por aí? 😊 Se não tiver mais nada, vou encerrar nosso atendimento em 1 minuto.', 'ia', null, $agente->nome);
                            $avisos++;
                            continue;
                        }

                        if ($avisoEm->gt(now()->subSeconds(55))) continue; // ainda não deu 1 min do aviso

                        $tinhaReserva = ! empty($ctx['poltronas_agente']);
                        try { $vagas->liberarConversa($c->id); } catch (\Throwable $e) {}
                        unset($ctx['poltronas_agente'], $ctx['poltrona_agente'], $ctx['pref_poltrona'], $ctx['lancha_agente'], $ctx['ocioso_aviso_em']);
                        $c->forceFill(['contexto' => $ctx])->save();
                        $txt = $tinhaReserva
                            ? 'Como não tive retorno, liberei a poltrona que estava reservada e vou encerrar por aqui. Obrigado pelo contato! Quando precisar, é só chamar que estou à disposição. 🙏'
                            : 'Vou encerrar nosso atendimento por aqui. Obrigado pelo contato! Quando precisar, é só chamar que estou à disposição. 🙏';
                        $atendimento->registrarSaida($c, $txt, 'ia', null, $agente->nome);
                        $c->forceFill(['status' => 'fechada', 'ia_ativa' => false, 'atendida' => true, 'nao_lidas' => 0])->save();
                        $fechadas++;
                    } catch (\Throwable $e) {
                        Log::warning('Conversa ociosa '.$c->id.': '.$e->getMessage());
                    }
                }
            });
        Tenant::set(null);
        $this->info("Avisos: {$avisos} · Encerradas: {$fechadas}");
        return self::SUCCESS;
    }
}
