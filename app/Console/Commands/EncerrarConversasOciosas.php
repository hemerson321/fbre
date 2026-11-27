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
 *  - X min sem resposta  -> avisa que vai encerrar (X, Y e a espera do PIX são configurados no agente);
 *  - +Y min sem resposta -> solta a poltrona reservada, agradece e fecha a conversa.
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
            ->whereBetween('ultima_msg_em', [now()->subHours(6), now()->subSeconds(55)])
            ->chunkById(100, function ($lote) use ($atendimento, $vagas, &$avisos, &$fechadas) {
                foreach ($lote as $c) {
                    try {
                        Tenant::set($c->empresa_id);
                        $ctx = $c->contexto ?? [];
                        $avisoEm = ! empty($ctx['ocioso_aviso_em']) ? \Illuminate\Support\Carbon::parse($ctx['ocioso_aviso_em']) : null;
                        // aviso antigo (cliente respondeu depois dele) não vale mais
                        if ($avisoEm && $c->cliente_respondeu_em && $c->cliente_respondeu_em->gt($avisoEm)) $avisoEm = null;

                        $agente = Agente::withoutGlobalScopes()->where('empresa_id', $c->empresa_id)->where('modulo', $c->modulo)->where('ativo', true)->first();
                        if (! $agente || $agente->regra('desligado')) continue;
                        $avisoMin = $agente->ociosoAvisoMin();
                        if ($avisoMin <= 0) continue; // configurado pra não encerrar sozinho

                        // PIX gerado e ainda não pago: o cliente está pagando — espera o tempo configurado antes de avisar/encerrar
                        $pixPendente = Cobranca::withoutGlobalScopes()->where('referencia_tipo', Conversa::class)->where('referencia_id', $c->id)
                            ->where('status', 'pendente')->where('created_at', '>=', now()->subMinutes($agente->ociosoPixMin()))->exists();
                        if ($pixPendente) {
                            try { $vagas->renovarConversa($c->id, $agente->reservaMin()); } catch (\Throwable $e) {} // segura a poltrona enquanto paga
                            continue;
                        }

                        // cliente já COMPROU nesta conversa: encerra direto, agradecendo e desejando boa viagem
                        if (! empty($ctx['passagem_id'])) {
                            if ($c->ultima_msg_em->gt(now()->subMinutes($avisoMin))) continue;
                            unset($ctx['poltronas_agente'], $ctx['poltrona_agente'], $ctx['pref_poltrona'], $ctx['lancha_agente'], $ctx['ocioso_aviso_em']);
                            $c->forceFill(['contexto' => $ctx])->save();
                            $atendimento->registrarSaida($c, 'Sua passagem está garantida ✅ Vou encerrar nosso atendimento por aqui. Se precisar do bilhete de novo ou de qualquer coisa, é só chamar. Obrigado pela preferência e boa viagem! 🚤', 'ia', null, $agente->nome);
                            $c->forceFill(['status' => 'fechada', 'ia_ativa' => false, 'atendida' => true, 'nao_lidas' => 0])->save();
                            $fechadas++;
                            continue;
                        }

                        if (! $avisoEm) {
                            if ($c->ultima_msg_em->gt(now()->subMinutes($avisoMin))) continue; // ainda não deu o tempo de espera
                            $ctx['ocioso_aviso_em'] = now()->toDateTimeString();
                            $c->forceFill(['contexto' => $ctx])->save();
                            $em = $agente->ociosoEncerrarMin();
                            $atendimento->registrarSaida($c, 'Ainda está por aí? 😊 Se não tiver mais nada, vou encerrar nosso atendimento em '.$em.' '.($em === 1 ? 'minuto' : 'minutos').'.', 'ia', null, $agente->nome);
                            $avisos++;
                            continue;
                        }

                        if ($avisoEm->gt(now()->subSeconds($agente->ociosoEncerrarMin() * 60 - 5))) continue; // ainda não deu o tempo depois do aviso

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
