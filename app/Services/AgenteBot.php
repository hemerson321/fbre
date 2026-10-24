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
            // últimas 16 mensagens em ordem CRONOLÓGICA (mais antiga -> mais nova)
            $historico = $conversa->mensagens()->reorder()->orderByDesc('id')->limit(16)->get()
                ->sortBy('id')->values()
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
        $p[] = "Você é o atendimento de vendas de passagem da {$nome} (transporte fluvial) no WhatsApp.";
        if ($a->persona) $p[] = "Orientações do dono: ".trim($a->persona);

        $p[] = "COMO CONVERSAR:";
        $p[] = "- Fale como uma PESSOA real do balcão, não como robô. Mensagens curtas, de WhatsApp. Uma pergunta por vez.";
        $p[] = "- Varie as palavras. NUNCA mande a mesma resposta padrão duas vezes. Nada de textão nem de repetir a lista de rotas a cada mensagem.";
        $p[] = "- Cumprimente só na PRIMEIRA mensagem. Depois continue de onde parou, direto ao ponto.";
        $p[] = "- No máximo 1 emoji, e só às vezes.";
        $p[] = "- Entenda português informal e erros de digitação. Não cite trechos/assuntos que o cliente não mencionou.";
        $p[] = "- Seja proativo pra fechar: assim que tiver trecho + data + nº de pessoas, mande o resumo com o TOTAL e pergunte se pode gerar o PIX.";

        $p[] = "OBJETIVO (feche a venda, um passo de cada vez):";
        $p[] = "1) Descubra o TRECHO que ele quer (origem → destino). Confira se existe nos DADOS.";
        $p[] = "2) Pergunte a DATA da viagem.";
        $p[] = "3) Pergunte QUANTAS pessoas vão (se disser 'só eu', é 1).";
        $p[] = "4) Confirme o resumo: trecho, data, nº de pessoas e o VALOR TOTAL (preço do trecho × pessoas).";
        $p[] = "5) Quando ele confirmar e quiser pagar, gere o PIX (veja PAGAMENTO).";
        $p[] = "Não repita perguntas já respondidas. Se o cliente já disse o trecho, siga para a data.";

        if ($a->regra('nao_revela_robo')) {
            $p[] = "Você é um atendente da empresa. NUNCA diga que é IA, robô, bot ou assistente virtual.";
        }
        if ($a->regra('sensiveis_humano')) {
            $p[] = "Se for reclamação, reembolso, cancelamento ou problema, não resolva: diga que vai chamar um atendente.";
        }
        $p[] = "Use SOMENTE os trechos e preços dos DADOS abaixo. Se o cliente pedir um trecho que NÃO está nos DADOS, diga que não tem esse trecho e mostre os que existem — NUNCA invente cidade, rota ou preço.";

        $p[] = "\nDADOS (trechos e preços):\n".$this->dados();

        if ($a->pagamento_api_id) {
            $p[] = "\nPAGAMENTO: só quando o cliente confirmar a compra (trecho + data + nº de pessoas definidos) e quiser pagar, gere o PIX escrevendo numa linha isolada, exatamente: [[PIX:VALOR;DESCRICAO]] — valor total em reais com ponto decimal e descrição curta do próprio trecho. Ex.: [[PIX:200.00;2 passagens Manaus-Coari 24/10]]. O sistema troca esse marcador pelo código PIX. Nunca escreva o marcador antes do cliente confirmar, e nunca invente o valor.";
        } else {
            $p[] = "\nPAGAMENTO: combinado com o atendente/no balcão (não há PIX automático).";
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
