<?php

namespace App\Services;

use App\Models\Agente;
use App\Models\Cobranca;
use App\Models\Conversa;
use App\Models\TipoPassageiro;
use App\Models\TrechoPreco;
use App\Support\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Motor do agente IA: quando chega mensagem, decide se responde, monta o contexto
 * com os dados do sistema (rotas/preços) e responde pelo WhatsApp. Gera PIX (Mercado Pago)
 * quando o cliente fecha a compra (marcador [[PIX:valor;descricao]]).
 */
class AgenteBot
{
    public function __construct(
        protected LLM $llm,
        protected Atendimento $atendimento,
        protected MercadoPago $mp,
    ) {}

    public function processar(Conversa $conversa): void
    {
        try {
            $agente = Agente::where('modulo', $conversa->modulo)->where('ativo', true)->first();
            if (! $agente || $agente->regra('desligado')) return;
            if (! $this->deveResponder($agente, $conversa)) return;

            $prov = $agente->llm;
            if (! $prov) { Log::info('Agente sem provedor de IA.'); return; }

            $conversa->forceFill(['status' => 'ia', 'ia_ativa' => true, 'atendida' => true])->save();

            $sistema = $this->promptSistema($agente);
            $historico = $conversa->mensagens()->orderByDesc('id')->limit(16)->get()
                ->reverse()->values()
                ->map(fn ($m) => ['role' => $m->direcao === 'entrada' ? 'user' : 'assistant', 'content' => (string) $m->texto])
                ->all();
            if (! $historico) return;

            $r = $this->llm->chat($prov, $historico, $sistema, 600);
            if (! $r['ok']) { Log::warning('Agente IA falhou: '.($r['erro'] ?? '')); return; }

            $texto = trim((string) $r['texto']);
            if ($texto === '') return;

            $texto = $this->tratarPix($agente, $conversa, $texto);
            $this->atendimento->registrarSaida($conversa, $texto, 'ia');
        } catch (\Throwable $e) {
            Log::warning('AgenteBot erro: '.$e->getMessage());
        }
    }

    /** Teste manual: roda o agente numa mensagem e devolve a resposta (não envia WhatsApp). */
    public function responderTeste(Agente $a, string $texto): array
    {
        $prov = $a->llm;
        if (! $prov) return ['ok' => false, 'erro' => 'Agente sem provedor de IA.'];
        $sistema = $this->promptSistema($a);
        // modo teste NÃO gera cobrança real: mantém o texto como veio (marcador visível, se houver)
        return $this->llm->chat($prov, [['role' => 'user', 'content' => $texto]], $sistema, 600);
    }

    protected function deveResponder(Agente $a, Conversa $c): bool
    {
        if ($c->status === 'humano') return false; // humano assumiu
        return match ($a->assumir_modo) {
            'sempre' => true,
            'fora_horario' => ! $a->noHorario(),
            'apos_min' => ((int) $a->assumir_min) <= 0, // com minutos>0 precisa do agendador (próxima etapa)
            default => false,
        };
    }

    protected function promptSistema(Agente $a): string
    {
        $empresa = Tenant::empresa();
        $nome = $empresa?->nomeExibicao() ?? 'a empresa';

        $p = [];
        $p[] = "Você atende o WhatsApp de {$nome}, uma empresa de transporte fluvial, ajudando clientes a comprar passagem.";
        if ($a->persona) $p[] = "Instruções do dono: ".trim($a->persona);
        $p[] = "Responda em português, de forma curta, simpática e objetiva (mensagens de WhatsApp).";

        if ($a->regra('nao_revela_robo')) {
            $p[] = "IMPORTANTE: você é um atendente da empresa. NUNCA diga que é uma IA, robô, assistente virtual ou bot.";
        }
        if ($a->regra('sensiveis_humano')) {
            $p[] = "Se o cliente trouxer reclamação, reembolso, cancelamento ou problema, NÃO tente resolver: diga que vai chamar um atendente e encerre educadamente.";
        }
        if ($a->regra('nunca_inventa')) {
            $p[] = "Só informe rotas, preços e horários que estão nos DADOS abaixo. Se não souber, diga que vai confirmar com um atendente. Nunca invente valor.";
        }

        $p[] = "\nDADOS DO SISTEMA:\n".$this->dados();

        if ($a->pagamento_api_id) {
            $p[] = "\nPAGAMENTO: quando o cliente confirmar a compra e você souber origem, destino, data e quantos passageiros, gere o PIX escrevendo numa linha isolada EXATAMENTE assim: [[PIX:VALOR;DESCRICAO]] — ex.: [[PIX:120.00;Passagem Manaus-Parintins 1 adulto]]. Use o valor correto da tabela. O sistema troca esse marcador pelo código PIX automaticamente. Não escreva o marcador antes do cliente confirmar.";
        } else {
            $p[] = "\nPAGAMENTO: o pagamento é combinado com o atendente/no balcão (não há PIX automático configurado).";
        }

        return implode("\n", $p);
    }

    /** Monta um resumo compacto de rotas/preços e tipos. */
    protected function dados(): string
    {
        $normal = TipoPassageiro::where('eh_normal', true)->first() ?? TipoPassageiro::first();
        $linhas = [];

        if ($normal) {
            $trechos = TrechoPreco::where('ativo', true)
                ->when($normal, fn ($q) => $q->where('tipo_passageiro_id', $normal->id))
                ->orderBy('origem')->limit(60)->get();
            if ($trechos->isNotEmpty()) {
                $linhas[] = "Preços (passageiro adulto, por trecho):";
                foreach ($trechos as $t) {
                    $preco = (float) $t->tarifa + (float) $t->alimentacao;
                    $linhas[] = "- {$t->origem} → {$t->destino}: R$ ".number_format($preco, 2, ',', '.');
                }
            }
        }

        $tipos = TipoPassageiro::where('ativo', true)->orderByDesc('eh_normal')->get();
        if ($tipos->isNotEmpty()) {
            $linhas[] = "\nTipos de passageiro:";
            foreach ($tipos as $t) {
                if ($t->eh_normal) { $linhas[] = "- {$t->nome}: preço cheio (base)"; continue; }
                $regra = ($t->modo ?? 'valor') === 'porcentagem'
                    ? ((int) $t->percentual).'% do adulto'
                    : 'preço próprio por trecho';
                $linhas[] = "- {$t->nome}: {$regra}";
            }
        }

        return $linhas ? implode("\n", $linhas) : "Sem tabela de preços cadastrada ainda.";
    }

    /** Detecta [[PIX:valor;descricao]], gera a cobrança no Mercado Pago e devolve o texto com o código. */
    protected function tratarPix(Agente $a, Conversa $c, string $texto): string
    {
        if (! preg_match('/\[\[PIX:\s*([\d.,]+)\s*;\s*([^\]]+)\]\]/i', $texto, $m)) {
            return $texto;
        }
        $marcador = $m[0];
        $raw = (string) $m[1];
        if (str_contains($raw, ',') && str_contains($raw, '.')) { $raw = str_replace(['.', ','], ['', '.'], $raw); }
        elseif (str_contains($raw, ',')) { $raw = str_replace(',', '.', $raw); }
        $valor = (float) preg_replace('/[^\d.]/', '', $raw);
        $descricao = trim($m[2]);

        $api = $a->pagamentoApi;
        if (! $api || $valor <= 0) {
            return trim(str_replace($marcador, '', $texto));
        }

        $r = $this->mp->cobrarPix($api, $valor, $descricao, ['nome' => $c->cliente_nome, 'telefone' => $c->cliente_telefone]);
        if (! $r['ok']) {
            Log::warning('Agente PIX falhou: '.($r['erro'] ?? ''));
            return trim(str_replace($marcador, "\n(No momento não consegui gerar o PIX, um atendente vai te ajudar.)", $texto));
        }

        Cobranca::create([
            'empresa_id' => $c->empresa_id,
            'pagamento_api_id' => $api->id,
            'modulo' => $c->modulo,
            'provider' => 'mercadopago',
            'provider_payment_id' => $r['payment_id'] ?? null,
            'valor' => $valor,
            'status' => 'pendente',
            'qr_copia_cola' => $r['copia_cola'] ?? null,
            'qr_base64' => $r['qr_base64'] ?? null,
            'ticket_url' => $r['ticket_url'] ?? null,
            'cliente_nome' => $c->cliente_nome,
            'cliente_telefone' => $c->cliente_telefone,
        ]);
        $c->forceFill(['funil' => 'aguardando_pagamento'])->save();

        $pix = $r['copia_cola'] ?? '';
        $bloco = "\n\n💳 PIX copia e cola (R$ ".number_format($valor, 2, ',', '.')."):\n".$pix."\n\nAssim que o pagamento cair eu confirmo aqui. 😉";
        return trim(str_replace($marcador, $bloco, $texto));
    }
}
