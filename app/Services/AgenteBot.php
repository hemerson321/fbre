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

    public function processar(Conversa $conversa, ?Agente $agente = null, bool $demo = false): void
    {
        try {
            $agente = $agente ?: Agente::where('modulo', $conversa->modulo)->where('ativo', true)->first();
            if (! $agente) return;
            if (! $demo && ($agente->regra('desligado') || ! $this->deveResponder($agente, $conversa))) return;

            $prov = $agente->llm;
            if (! $prov) { Log::info('Agente sem provedor de IA.'); return; }

            if (! $demo) {
                // debounce: cliente costuma mandar em partes ("1" / "so eu"). Espera e, se chegou msg nova,
                // deixa o próximo processamento responder com a conversa completa (evita resposta dupla).
                $ultimoId = (int) $conversa->mensagens()->max('id');
                sleep(3);
                if ((int) $conversa->mensagens()->max('id') !== $ultimoId) return;
            }

            $conversa->forceFill(['status' => 'ia', 'ia_ativa' => true, 'atendida' => true])->save();

            $sistema = $this->promptSistema($agente);
            // atendimento reaberto: só considera mensagens a partir do reinício (ignora conversa já finalizada)
            $reinicio = (int) ($conversa->contexto['reinicio_id'] ?? 0);
            // últimas 16 mensagens em ordem CRONOLÓGICA (mais antiga -> mais nova)
            $historico = $conversa->mensagens()->reorder()
                ->when($reinicio > 0, fn ($q) => $q->where('id', '>=', $reinicio))
                ->orderByDesc('id')->limit(16)->get()
                ->sortBy('id')->values()
                ->map(fn ($m) => ['role' => $m->direcao === 'entrada' ? 'user' : 'assistant', 'content' => (string) $m->texto])
                ->all();
            if (! $historico) return;

            $r = $this->llm->chat($prov, $historico, $sistema, 600);
            if (! $r['ok']) { Log::warning('Agente IA falhou: '.($r['erro'] ?? '')); return; }

            $texto = trim((string) $r['texto']);
            if ($texto === '') return;

            $texto = $this->tratarVenda($conversa, $texto);
            $texto = $this->tratarPax($conversa, $texto);
            $res = $this->tratarPix($agente, $conversa, $texto, $demo);
            $this->atendimento->registrarSaida($conversa, $res['texto'], 'ia');
            if (! empty($res['pix'])) {
                $this->atendimento->registrarSaida($conversa, $res['pix'], 'ia'); // PIX em mensagem separada (copiar fácil)
            }
            app(\App\Services\ConfirmaPagamento::class)->tentarCriarPassagem($conversa); // cria se já pagou e já tem dados
        } catch (\Throwable $e) {
            Log::warning('AgenteBot erro: '.$e->getMessage());
        }
    }

    /** Cliente mandou foto do documento: lê por OCR, guarda os dados e o agente confirma. */
    public function processarFoto(Conversa $conversa, string $base64, string $mime = 'image/jpeg', ?Agente $agente = null, bool $demo = false): void
    {
        try {
            $agente = $agente ?: Agente::where('modulo', $conversa->modulo)->where('ativo', true)->first();
            if (! $agente) return;
            if (! $demo && ($agente->regra('desligado') || ! $this->deveResponder($agente, $conversa))) return;
            $prov = $agente->llm;
            if (! $prov) return;

            $conversa->forceFill(['status' => 'ia', 'ia_ativa' => true, 'atendida' => true])->save();

            $cfg = ['provider' => $prov->provider, 'token' => (string) $prov->token, 'modelo' => (string) $prov->modelo];
            $dataUri = 'data:'.$mime.';base64,'.$base64;
            $r = app(\App\Services\LeitorDocumento::class)->extrair($dataUri, $cfg);

            if (! ($r['ok'] ?? false) || empty($r['campos'])) {
                $this->atendimento->registrarSaida($conversa, "Recebi a foto, mas não consegui ler direito. Pode me mandar por escrito o nome completo, o documento (RG ou CPF) e a data de nascimento?", 'ia');
                return;
            }

            $campos = $r['campos'];
            $ctx = $conversa->contexto ?? [];
            $pax = $ctx['passageiros'] ?? [];
            $pax[] = [
                'nome' => $campos['nome'] ?? null,
                'documento' => $campos['documento'] ?? null,
                'nascimento' => $campos['nascimento'] ?? null,
                'sexo' => $campos['sexo'] ?? null,
                'nacionalidade' => $campos['nacionalidade'] ?? null,
            ];
            $ctx['passageiros'] = $pax;
            $conversa->forceFill(['contexto' => $ctx])->save();

            $lido = "DADOS LIDOS DA FOTO DO DOCUMENTO -> nome: ".($campos['nome'] ?? '?')
                .", documento: ".($campos['documento'] ?? '?')
                .", nascimento: ".($campos['nascimento'] ?? '?')
                .", sexo: ".($campos['sexo'] ?? '?').".";

            $sistema = $this->promptSistema($agente)."\n\n".$lido
                ." Confirme esses dados com o cliente de forma natural (ex.: 'Peguei aqui: Fulano, nascimento tal, confere?') e siga a venda.";

            $hist = $conversa->mensagens()->reorder()->orderByDesc('id')->limit(16)->get()
                ->sortBy('id')->values()
                ->map(fn ($m) => ['role' => $m->direcao === 'entrada' ? 'user' : 'assistant', 'content' => (string) $m->texto])
                ->all();
            $hist[] = ['role' => 'user', 'content' => '(enviei a foto do meu documento)'];

            $rr = $this->llm->chat($prov, $hist, $sistema, 400);
            if ($rr['ok'] ?? false) {
                $this->atendimento->registrarSaida($conversa, trim((string) $rr['texto']), 'ia');
            }
            app(\App\Services\ConfirmaPagamento::class)->tentarCriarPassagem($conversa);
        } catch (\Throwable $e) {
            Log::warning('processarFoto erro: '.$e->getMessage());
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
        $hoje = now();
        $diaHoje = \App\Models\Rota::DIAS[$hoje->dayOfWeek] ?? '';
        $p[] = "Hoje é {$diaHoje}, ".$hoje->format('d/m/Y').". Use isso pra entender 'hoje', 'amanhã', 'essa semana', 'semana que vem'.";
        if ($a->persona) $p[] = "Orientações do dono: ".trim($a->persona);

        $p[] = "COMO CONVERSAR:";
        $p[] = "- Fale como uma PESSOA real do balcão, não como robô. Mensagens curtas, de WhatsApp. Uma pergunta por vez.";
        $p[] = "- Varie as palavras. NUNCA mande a mesma resposta padrão duas vezes. Nada de textão nem de repetir a lista de rotas a cada mensagem.";
        $p[] = "- Cumprimente só na PRIMEIRA mensagem. Depois continue de onde parou, direto ao ponto.";
        $p[] = "- No máximo 1 emoji, e só às vezes.";
        $p[] = "- Entenda português informal e erros de digitação. Não cite trechos/assuntos que o cliente não mencionou.";
        $p[] = "- Seja proativo pra fechar: assim que tiver trecho + data + nº de pessoas, mande o resumo com o TOTAL e pergunte se pode gerar o PIX.";
        $p[] = "- HORÁRIOS: use só o que está nos DADOS. A SAÍDA da origem é o dia/hora da parada de origem; a CHEGADA no destino é o dia/hora da parada de destino. NUNCA invente horário, hora de chegada ou duração ('X horas de viagem'). Se perguntarem algo que não está nos DADOS, diga que confirma com um atendente.";

        $p[] = "OBJETIVO (feche a venda rápido, sem enrolar, um passo por vez):";
        $p[] = "1) TRECHO que ele quer (origem → destino). Confirme que existe nos DADOS.";
        $p[] = "2) DATA. Se o cliente disser 'qualquer dia', 'semana que vem' ou não souber, NÃO devolva a pergunta: OFEREÇA as próximas saídas REAIS da rota (estão nos DADOS em 'próximas saídas') e peça pra ele escolher uma.";
        $p[] = "3) QUANTAS pessoas (se disser 'só eu', é 1 — não pergunte de novo).";
        $p[] = "4) Mande o resumo curto: trecho, data e VALOR TOTAL (preço do trecho × pessoas) e pergunte 'posso gerar o PIX?'. (NÃO peça dados de passageiro ainda.)";
        $p[] = "5) Confirmando, gere o PIX (veja PAGAMENTO). Primeiro o pagamento.";
        $p[] = "6) SÓ DEPOIS que o sistema confirmar o pagamento (vai aparecer '✅ Pagamento confirmado' na conversa), aí sim peça os DADOS DO(S) PASSAGEIRO(S): NOME COMPLETO, DOCUMENTO (RG/CPF) e DATA DE NASCIMENTO — ou diga que pode mandar a FOTO do RG/CNH que você pega os dados. Quando tiver os dados de um passageiro, emita numa linha isolada (o cliente não vê): [[PAX: Nome|documento|nascimento dd/mm/aaaa]] — vários passageiros separados por ' ;; '.";
        $p[] = "NUNCA repita uma pergunta já respondida. Avance sempre pro próximo passo. Não peça dados que o cliente já deu.";

        if ($a->regra('nao_revela_robo')) {
            $p[] = "Você é um atendente da empresa. NUNCA diga que é IA, robô, bot ou assistente virtual.";
        }
        if ($a->regra('sensiveis_humano')) {
            $p[] = "Se for reclamação, reembolso, cancelamento ou problema, não resolva: diga que vai chamar um atendente.";
        }
        $p[] = "Use SOMENTE os trechos e preços dos DADOS abaixo. Se o cliente pedir um trecho que NÃO está nos DADOS, diga que não tem esse trecho e mostre os que existem — NUNCA invente cidade, rota ou preço.";

        $p[] = "\nDADOS (trechos e preços):\n".$this->dados();

        if ($a->pagamento_api_id) {
            $p[] = "\nPAGAMENTO: só quando o cliente confirmar a compra (trecho + data + nº de pessoas + dados do passageiro definidos) e quiser pagar, gere o PIX escrevendo numa linha isolada, exatamente: [[PIX:VALOR;DESCRICAO]] — valor total em reais com ponto decimal. Ex.: [[PIX:200.00;2 passagens Manaus-Coari 26/10]]. Nunca invente o valor.";
            $p[] = "NA MESMA MENSAGEM, antes do PIX, inclua numa linha isolada (o cliente NÃO vê, o sistema remove): [[VENDA: origem=<origem>; destino=<destino>; data=<dd/mm/aaaa>]]. Os dados dos passageiros são coletados DEPOIS do pagamento (via marcador [[PAX:...]]).";
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

        // horários/dias de saída (rotas) + próximas datas concretas
        $rotas = \App\Models\Rota::with('paradas')->get();
        if ($rotas->isNotEmpty()) {
            $linhas[] = "\nRotas (paradas na ordem, com dia e hora de cada parada — a saída de cada cidade é esse dia/hora):";
            foreach ($rotas as $r) {
                $cal = method_exists($r, 'resumoCalendario') ? $r->resumoCalendario() : $r->nome;
                $prox = $this->proximasDatas($r);
                $linhas[] = "- ".$r->nome.": ".$cal;
                if ($prox) $linhas[] = "   próximas saídas (da 1ª parada): ".$prox;
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

    /** Próximas 3 datas de saída de uma rota (a partir do dia-da-semana da 1ª parada). */
    protected function proximasDatas(\App\Models\Rota $r): string
    {
        $p = $r->paradas->first();
        if (! $p || $p->dia_semana === null) return '';
        $dia = (int) $p->dia_semana; // 0=Dom .. 6=Sáb (igual Carbon)
        $hora = $p->hora ? ' '.substr($p->hora, 0, 5) : '';
        $base = now()->startOfDay();
        $datas = [];
        $d = $base->copy();
        for ($i = 0; $i < 70 && count($datas) < 3; $i++) {
            if ($d->dayOfWeek === $dia) {
                $datas[] = $d->format('d/m').' ('.(\App\Models\Rota::DIAS_CURTO[$dia] ?? '').$hora.')';
            }
            $d->addDay();
        }
        return implode(', ', $datas);
    }

    /** Captura [[PAX: nome|doc|nasc ;; ...]] (oculto), guarda os passageiros em contexto e remove do texto. */
    protected function tratarPax(Conversa $c, string $texto): string
    {
        if (! preg_match('/\[\[PAX:(.+?)\]\]/is', $texto, $m)) return $texto;
        $pax = [];
        foreach (preg_split('/\s*;;\s*/', trim($m[1])) as $linha) {
            $parts = array_map('trim', explode('|', $linha));
            if (($parts[0] ?? '') !== '') {
                $pax[] = ['nome' => $parts[0], 'documento' => $parts[1] ?? null, 'nascimento' => $parts[2] ?? null];
            }
        }
        if ($pax) {
            $ctx = $c->contexto ?? [];
            $ctx['passageiros'] = $pax;
            $c->forceFill(['contexto' => $ctx])->save();
        }
        return trim(str_replace($m[0], '', $texto));
    }

    /** Captura [[VENDA: origem=..; destino=..; data=..; passageiros=..]] (oculto), guarda em contexto e remove do texto. */
    protected function tratarVenda(Conversa $c, string $texto): string
    {
        if (! preg_match('/\[\[VENDA:(.+?)\]\]/is', $texto, $m)) return $texto;
        $corpo = $m[1];
        $venda = $c->contexto['venda'] ?? [];
        foreach (['origem', 'destino', 'data'] as $k) {
            if (preg_match('/'.$k.'\s*=\s*([^;]+)/i', $corpo, $mm)) $venda[$k] = trim($mm[1]);
        }
        if (preg_match('/passageiros\s*=\s*(.+)$/is', $corpo, $mm)) {
            $pax = [];
            foreach (preg_split('/\s*;;\s*/', trim($mm[1])) as $linha) {
                $parts = array_map('trim', explode('|', $linha));
                if (($parts[0] ?? '') !== '') {
                    $pax[] = ['nome' => $parts[0], 'documento' => $parts[1] ?? null, 'nascimento' => $parts[2] ?? null];
                }
            }
            if ($pax) $venda['passageiros'] = $pax;
        }
        $ctx = $c->contexto ?? [];
        $ctx['venda'] = $venda;
        $c->forceFill(['contexto' => $ctx])->save();
        return trim(str_replace($m[0], '', $texto));
    }

    /** Detecta [[PIX:valor;descricao]], gera a cobrança no Mercado Pago. Retorna texto limpo + o código PIX à parte. */
    protected function tratarPix(Agente $a, Conversa $c, string $texto, bool $demo = false): array
    {
        if (! preg_match('/\[\[PIX:\s*([\d.,]+)\s*;\s*([^\]]+)\]\]/i', $texto, $m)) {
            return ['texto' => $texto, 'pix' => null];
        }
        $marcador = $m[0];
        $raw = (string) $m[1];
        if (str_contains($raw, ',') && str_contains($raw, '.')) { $raw = str_replace(['.', ','], ['', '.'], $raw); }
        elseif (str_contains($raw, ',')) { $raw = str_replace(',', '.', $raw); }
        $valor = (float) preg_replace('/[^\d.]/', '', $raw);
        $descricao = trim($m[2]);

        // MODO DEMO: nao chama o Mercado Pago. Gera uma cobranca "demo" com copia-e-cola ficticio,
        // que o botao "Simular pagamento" marca como pago para testar o fluxo completo.
        if ($demo) {
            if ($valor <= 0) return ['texto' => trim(str_replace($marcador, '', $texto)), 'pix' => null];
            $copia = 'DEMO-PIX-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(10)).'-'.str_replace('.', '', number_format($valor, 2, '.', ''));
            Cobranca::create([
                'empresa_id' => $c->empresa_id,
                'pagamento_api_id' => optional($a->pagamentoApi)->id,
                'modulo' => $c->modulo,
                'referencia_tipo' => \App\Models\Conversa::class,
                'referencia_id' => $c->id,
                'provider' => 'demo',
                'provider_payment_id' => null,
                'valor' => $valor,
                'status' => 'pendente',
                'qr_copia_cola' => $copia,
                'cliente_nome' => $c->cliente_nome,
                'cliente_telefone' => $c->cliente_telefone,
            ]);
            $c->forceFill(['funil' => 'aguardando_pagamento'])->save();
            $aviso = "Pronto! É só pagar os R$ ".number_format($valor, 2, ',', '.')." no PIX copia e cola que vou te mandar agora. Assim que cair, confirmo aqui. 😉";
            return ['texto' => trim(str_replace($marcador, $aviso, $texto)), 'pix' => $copia];
        }

        $api = $a->pagamentoApi;
        if (! $api || $valor <= 0) {
            return ['texto' => trim(str_replace($marcador, '', $texto)), 'pix' => null];
        }

        $r = $this->mp->cobrarPix($api, $valor, $descricao, ['nome' => $c->cliente_nome, 'telefone' => $c->cliente_telefone]);
        if (! $r['ok']) {
            Log::warning('Agente PIX falhou: '.($r['erro'] ?? ''));
            return ['texto' => trim(str_replace($marcador, "\n(No momento não consegui gerar o PIX, um atendente vai te ajudar.)", $texto)), 'pix' => null];
        }

        Cobranca::create([
            'empresa_id' => $c->empresa_id,
            'pagamento_api_id' => $api->id,
            'modulo' => $c->modulo,
            'referencia_tipo' => \App\Models\Conversa::class,
            'referencia_id' => $c->id,
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

        $pix = (string) ($r['copia_cola'] ?? '');
        $aviso = "Pronto! É só pagar os R$ ".number_format($valor, 2, ',', '.')." no PIX copia e cola que vou te mandar agora. Assim que cair, confirmo aqui. 😉";
        $textoLimpo = trim(str_replace($marcador, $aviso, $texto));
        return ['texto' => $textoLimpo, 'pix' => $pix ?: null];
    }
}
