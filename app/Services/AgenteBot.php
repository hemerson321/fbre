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

    /** Conversa/agente do processamento atual — pra registrar o consumo de tokens de cada chamada à IA. */
    protected ?Conversa $convUso = null;
    protected ?Agente $agUso = null;

    /** Grava o consumo de tokens de uma chamada (painel de custos do atendimento). Nunca derruba o atendimento. */
    protected function registrarUso(?\App\Models\LlmProvedor $prov, array $r, string $tipo = 'chat'): void
    {
        try {
            $u = $r['uso'] ?? null;
            if (! is_array($u) || ! $this->convUso) return;
            static $temTabela = null;
            $temTabela ??= \Illuminate\Support\Facades\Schema::hasTable('agente_usos');
            if (! $temTabela) return;
            \App\Models\AgenteUso::create([
                'empresa_id' => $this->convUso->empresa_id,
                'conversa_id' => $this->convUso->id,
                'agente_id' => $this->agUso?->id,
                'provider' => (string) ($r['provider'] ?? $prov?->provider ?? ''),
                'modelo' => mb_substr((string) ($r['modelo'] ?? $prov?->modelo ?? ''), 0, 80),
                'tipo' => $tipo,
                'tok_in' => (int) ($u['in'] ?? 0),
                'tok_out' => (int) ($u['out'] ?? 0),
                'tok_cache_r' => (int) ($u['cache_r'] ?? 0),
                'tok_cache_w' => (int) ($u['cache_w'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            Log::info('registrarUso: '.$e->getMessage());
        }
    }

    /**
     * Proteção contra abuso: o mesmo número não pode ficar conversando sem parar (curioso, concorrente, outro robô).
     * Passou do limite de mensagens do agente em 24h -> manda UM aviso e fica em silêncio até a janela liberar.
     * Também há um teto geral por dia (todos os clientes) contra ataque com vários números.
     */
    protected function limiteAtingido(Agente $a, Conversa $c): bool
    {
        try {
            $ctx = $c->contexto ?? [];
            // já bloqueado: silêncio total (nem conta, nem avisa de novo)
            if (! empty($ctx['limite_ate']) && now()->lt(\Illuminate\Support\Carbon::parse($ctx['limite_ate']))) return true;

            $max = $a->limiteClienteDia();
            if ($max > 0) {
                $n = $c->mensagens()->reorder()->where('direcao', 'saida')->where('origem', 'ia')->where('created_at', '>=', now()->subDay())
                    ->where('id', '>=', (int) ($ctx['limite_zerado_id'] ?? 0))->count(); // operador clicou em 🔄: zera a contagem
                if ($n >= $max) {
                    // quem já pagou está terminando a compra (dados dos passageiros): triplo do limite
                    $pagou = Cobranca::where('referencia_tipo', Conversa::class)->where('referencia_id', $c->id)
                        ->where('status', 'pago')->where('updated_at', '>=', now()->subDays(2))->exists();
                    if (! $pagou || $n >= $max * 3) {
                        $ctx['limite_ate'] = now()->addDay()->toDateTimeString();
                        $ctx['limite_motivo'] = $n.' mensagens do agente em 24h';
                        $c->forceFill(['contexto' => $ctx])->save();
                        try { app(\App\Services\Vagas::class)->liberarConversa($c->id); } catch (\Throwable $e) {}
                        $this->atendimento->registrarSaida($c, $a->limiteMsg(), 'ia', null, $a->nome);
                        Log::warning("Agente: limite por cliente atingido na conversa {$c->id} ({$n} msgs em 24h).");
                        return true;
                    }
                }
            }

            $teto = $a->limiteTotalDia();
            if ($teto > 0) {
                $hoje = \App\Models\Mensagem::where('empresa_id', $c->empresa_id)->where('direcao', 'saida')->where('origem', 'ia')
                    ->where('created_at', '>=', now()->startOfDay())->count();
                if ($hoje >= $teto) {
                    Log::warning("Agente: TETO GERAL do dia atingido ({$hoje}/{$teto}) — em silêncio até amanhã.");
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::info('limiteAtingido: '.$e->getMessage());
        }
        return false;
    }

    public function processar(Conversa $conversa, ?Agente $agente = null, bool $demo = false, string $audioModo = 'texto'): void
    {
        try {
            $agente = $agente ?: Agente::where('modulo', $conversa->modulo)->where('ativo', true)->first();
            if (! $agente) return;
            $this->convUso = $conversa; $this->agUso = $agente;
            if (! $demo && ($agente->regra('desligado') || ! $this->deveResponder($agente, $conversa))) return;
            if (! $demo && ($conversa->precisa_humano ?? false)) return; // já transferido pro humano: agente fica em silêncio
            if (! $demo && $this->limiteAtingido($agente, $conversa)) return; // proteção contra abuso: não gasta mais token com esse número hoje
            try { app(\App\Services\Vagas::class)->renovarConversa($conversa->id, $agente->reservaMin()); } catch (\Throwable $e) {} // mantém a reserva enquanto o cliente responde

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
                ->orderByDesc('id')->limit(40)->get()
                ->sortBy('id')->values()
                ->map(fn ($m) => ['role' => $m->direcao === 'entrada' ? 'user' : 'assistant', 'content' => (string) $m->texto])
                ->all();
            if (! $historico) return;

            // MEMÓRIA: o cliente voltou depois do atendimento encerrado -> o agente ainda lembra do que foi falado
            // (as últimas mensagens de até 2 dias atrás), pra não tratar "obrigado, boa noite" como cliente novo.
            // Reinício manual do operador (🔄) continua apagando tudo.
            if ($reinicio > 0) {
                $piso = (int) ($conversa->contexto['limite_zerado_id'] ?? 0);
                $antes = $conversa->mensagens()->reorder()->where('id', '<', $reinicio)->where('id', '>=', $piso)
                    ->where('created_at', '>=', now()->subDays(2))
                    ->orderByDesc('id')->limit(14)->get()->sortBy('id')->values()
                    ->map(fn ($m) => ['role' => $m->direcao === 'entrada' ? 'user' : 'assistant', 'content' => (string) $m->texto])
                    ->all();
                if ($antes) {
                    $historico[0]['content'] = "[NOVO CONTATO — o atendimento acima já foi encerrado]\n".$historico[0]['content'];
                    $historico = array_merge($antes, $historico);
                }
            }

            $r = $this->conversarCascata($agente, $historico, $sistema, 1400);
            if (! $r['ok']) { Log::warning('Agente IA falhou: '.($r['erro'] ?? '')); return; }

            $texto = trim((string) $r['texto']);
            if ($texto === '') return;

            // resposta com orçamento não mexe em poltrona nem PIX (isso só quando o cliente fechar a compra)
            if (preg_match('/\[\[ORCAMENTO:/i', $texto)) {
                $texto = preg_replace('/\[\[(POLTRONAS?|ESCOLHER|PIX|VENDA)\b[^\]]*\]\]/i', '', $texto);
                $this->pularGuardiao = true;
            }
            $texto = $this->tratarVenda($conversa, $texto);
            $texto = $this->tratarVagas($conversa, $texto);
            $texto = $this->tratarPoltronas($conversa, $texto);
            $texto = $this->tratarEscolher($conversa, $texto);
            $texto = $this->tratarPoltrona($conversa, $texto);
            $texto = $this->tratarLiberar($conversa, $texto);
            $texto = $this->tratarPref($conversa, $texto);
            if (! $this->pularGuardiao) $texto = $this->guardiaoPoltrona($conversa, $texto);
            $this->pularGuardiao = false;
            $texto = $this->tratarPax($conversa, $texto);
            $texto = $this->tratarOrcamento($agente, $conversa, $texto);
            $texto = $this->tratarHumano($conversa, $texto, $demo, $agente);
            $res = $this->tratarPix($agente, $conversa, $texto, $demo);
            $res['texto'] = $this->limparMarcadores($res['texto']);
            if ($res['texto'] === '') $res['texto'] = 'Só um segundo…';
            $this->atendimento->registrarSaida($conversa, $res['texto'], 'ia', null, $agente?->nome, $audioModo);
            if (! $demo) $this->enviarMapaPoltronas($conversa, $agente);
            $this->enviarOrcamentoPdf($conversa);
            if (! empty($res['pix'])) {
                $this->atendimento->registrarSaida($conversa, $res['pix'], 'ia'); // PIX em mensagem separada (copiar fácil)
            }
            try { app(\App\Services\Vagas::class)->renovarConversa($conversa->id, $agente->reservaMin()); } catch (\Throwable $e) {} // reserva dura até o encerramento por falta de resposta
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
            $this->convUso = $conversa; $this->agUso = $agente;
            if (! $demo && $this->limiteAtingido($agente, $conversa)) return;
            $prov = $this->provedorVisao($agente); // imagem precisa de Claude/OpenAI (Gemini não entra aqui)
            if (! $prov) return;

            $conversa->forceFill(['status' => 'ia', 'ia_ativa' => true, 'atendida' => true])->save();

            $cfg = ['provider' => $prov->provider, 'token' => (string) $prov->token, 'modelo' => (string) $prov->modelo];
            $dataUri = 'data:'.$mime.';base64,'.$base64;
            $r = app(\App\Services\LeitorDocumento::class)->extrair($dataUri, $cfg);

            if (! ($r['ok'] ?? false) || empty($r['campos'])) {
                $this->atendimento->registrarSaida($conversa, "Recebi a foto, mas não consegui ler direito. Pode me mandar por escrito o nome completo, o documento (RG ou CPF) e a data de nascimento?", 'ia', null, $agente?->nome);
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

            $hist = $conversa->mensagens()->reorder()->orderByDesc('id')->limit(40)->get()
                ->sortBy('id')->values()
                ->map(fn ($m) => ['role' => $m->direcao === 'entrada' ? 'user' : 'assistant', 'content' => (string) $m->texto])
                ->all();
            $hist[] = ['role' => 'user', 'content' => '(enviei a foto do meu documento)'];

            $rr = $this->llm->chat($prov, $hist, $sistema, 400);
            $this->registrarUso($prov, $rr, 'foto');
            if ($rr['ok'] ?? false) {
                $this->atendimento->registrarSaida($conversa, $this->limparMarcadores(trim((string) $rr['texto'])), 'ia', null, $agente?->nome);
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

    /** Conversa com cascata de custo: modelo principal (barato) -> escalona pro esperto se falhar ou pedir [[ESCALAR]]. */
    protected function conversarCascata(Agente $agente, array $hist, string $sistema, int $max = 1400): array
    {
        $prim = $agente->llm;
        $esc = $agente->llmEscalonar;

        $r = $prim ? $this->llm->chat($prim, $hist, $sistema, $max) : ['ok' => false, 'erro' => 'sem provedor principal'];
        $this->registrarUso($prim, $r);
        $texto = ($r['ok'] ?? false) ? trim((string) $r['texto']) : '';
        $pediuAjuda = $texto !== '' && preg_match('/\[\[ESCALAR[^\]]*\]\]/i', $texto);
        // modelo barato se enrolou: veio vazio, ou com marcador [[...]] aberto/cortado -> sobe pro modelo melhor
        if (($r['ok'] ?? false) && ($texto === '' || substr_count($texto, '[[') !== substr_count($texto, ']]'))) $pediuAjuda = true;

        if ($esc && $esc->id !== ($prim->id ?? null) && ((! ($r['ok'] ?? false)) || $pediuAjuda)) {
            $r2 = $this->llm->chat($esc, $hist, $sistema, $max);
            $this->registrarUso($esc, $r2, 'escalonado');
            if ($r2['ok'] ?? false) { $r = $r2; $texto = trim((string) $r2['texto']); }
        }

        $texto = trim(preg_replace('/\[\[ESCALAR[^\]]*\]\]/i', '', $texto));
        return ['ok' => $r['ok'] ?? false, 'texto' => $texto, 'erro' => $r['erro'] ?? null];
    }

    /** Provedor que lê IMAGEM (Claude/OpenAI). Prefere o de escalonamento; senão o principal; senão qualquer um ativo. */
    protected function provedorVisao(Agente $agente): ?\App\Models\LlmProvedor
    {
        foreach ([$agente->llmEscalonar, $agente->llm] as $pv) {
            if ($pv && in_array($pv->provider, ['claude', 'openai'], true)) return $pv;
        }
        return \App\Models\LlmProvedor::where('ativo', true)->whereIn('provider', ['claude', 'openai'])->orderByDesc('id')->first();
    }

    /** Rede de segurança: remove QUALQUER marcador que tenha sobrado (completo ou truncado) pra não vazar pro cliente. */
    protected function limparMarcadores(string $t): string
    {
        $t = preg_replace('/\[\[[^\]]*\]\]/', '', $t); // marcadores completos que sobraram
        $t = preg_replace('/\[\[[^\]]*$/s', '', $t);      // marcador aberto e cortado (ex.: "[[POLTRONA: ...")
        $t = preg_replace('/[ \t]+\n/', "\n", (string) $t);
        $t = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/su', '*$1*', (string) $t); // **negrito** de markdown -> *negrito* do WhatsApp
        $t = str_replace('**', '*', (string) $t);
        return trim((string) $t);
    }

    protected function promptSistema(Agente $a): string
    {
        $empresa = Tenant::empresa();
        $nome = $empresa?->nomeExibicao() ?? 'a empresa';

        $auto = $a->regra('autonomo'); // atende sozinho: nunca transfere pro atendimento humano
        $p = [];
        $p[] = "Você é o atendimento de vendas de passagem da {$nome} (transporte fluvial) no WhatsApp.";
        $hoje = now();
        $diaHoje = \App\Models\Rota::DIAS[$hoje->dayOfWeek] ?? '';
        $p[] = "Hoje é {$diaHoje}, ".$hoje->format('d/m/Y').". Use isso pra entender 'hoje', 'amanhã', 'essa semana', 'semana que vem'.";
        if ($a->persona) $p[] = "Orientações do dono: ".trim($a->persona);

        $p[] = "COMO CONVERSAR (MUITO IMPORTANTE):";
        $p[] = "- Fale como uma PESSOA real do balcão. O cliente NÃO pode perceber que é robô/IA. Nunca soe formal ou automático.";
        $p[] = "- Respostas CURTAS e objetivas: 1 ou 2 linhas, no máximo. Nada de textão, nada de explicar demais, nada de lista longa. Se der pra responder em 5 palavras, responda em 5 palavras.";
        $p[] = "- Uma pergunta por vez. Vá direto ao ponto. Não repita o que o cliente já disse nem o que você já falou.";
        $p[] = "- Varie as palavras. NUNCA mande a mesma resposta padrão duas vezes. Tom de WhatsApp, simples e leve (pode usar 'pra', 'tá', 'blz').";
        $p[] = "- Cumprimente só na PRIMEIRA mensagem. Depois continue de onde parou, sem rodeio.";
        $p[] = "- No máximo 1 emoji, e só às vezes.";
        $p[] = "- Entenda português informal e erros de digitação. Não cite trechos/assuntos que o cliente não mencionou.";
        $p[] = "- Seja proativo pra fechar: assim que tiver trecho + data + nº de pessoas, mande o resumo com o TOTAL e pergunte se pode gerar o PIX.";
        $p[] = "- HORÁRIOS: use SÓ o que está nos DADOS (seção ROTAS E SAÍDAS). A SAÍDA de uma cidade é o DIA e a HORA da parada DELA — ex.: se Manaus sai Terça 11:00, use Terça 11:00 e as datas em 'próximas' de Manaus. NUNCA troque o dia/hora por outro, NUNCA use o de outra cidade/rota e NUNCA invente um horário que não está nos DADOS.";
        $p[] = "- DURAÇÃO DA VIAGEM: quando perguntarem 'quantas horas', ESTIME pelo horário das paradas nos DADOS — da saída da origem até a parada do destino (ex.: sai Manaus Seg 18:00 e a parada de Tefé é Ter 20:00 ≈ 26 horas). Fale como ESTIMATIVA ('mais ou menos X horas'), nunca como garantia. Se faltar o horário de alguma ponta, dê um aproximado com cuidado ou confirme com o atendente.";
        $p[] = "- ITINERÁRIO E HORÁRIOS (responda SEMPRE, é informação pública): se perguntarem o roteiro, por onde a lancha passa, que dia/que horas sai, que horas PASSA ou CHEGA em tal cidade, responda direto com o que está em ROTAS E SAÍDAS — as paradas na ordem, com o dia e a hora de cada uma e o nome da lancha. Ex.: 'A Glória de Deus 7 sai de Manaus terça 11:00, passa em Codajás terça umas 19:00 e chega em Tefé quarta por volta das 06:00'. O horário de uma parada do meio do caminho é PREVISÃO (rio, carga e clima mudam): fale 'por volta de', 'previsão', nunca como garantia. Se mais de uma lancha faz o trecho, mostre as opções. Se a cidade não está em nenhuma rota, diga que não atendemos lá e cite as cidades que atendemos.";
        $p[] = "- SEJA UM ATENDENTE COMPLETO: além de vender, tire qualquer dúvida com o que existe nos DADOS — cidades atendidas, dias de saída, próximas datas, lanchas, se a lancha é de poltrona ou rede, preços por tipo de passageiro (criança, idoso, etc. e as idades), bebê de colo, alimentação, vagas e poltronas livres (pelos marcadores). Responda a dúvida PRIMEIRO, de forma curta e certa, e só então ofereça o próximo passo (ex.: 'quer que eu já veja a data pra você?'). Não empurre a venda em quem só está se informando, e nunca deixe pergunta sem resposta.";
        if (($a->regras['inclui_comida'] ?? true)) {
            $p[] = "- ALIMENTAÇÃO: a passagem INCLUI alimentação/comida a bordo. Se perguntarem se dá comida, confirme que SIM, é servida alimentação a bordo.";
        } else {
            $p[] = "- ALIMENTAÇÃO: a passagem NÃO inclui alimentação. Se perguntarem, informe que NÃO é servida comida a bordo (o cliente leva a própria).";
        }
        $p[] = "- DADOS PÚBLICOS DA VIAGEM (responda à vontade): trechos, preços, dias e horários de saída, nome da lancha, paradas, duração estimada e alimentação são PÚBLICOS — responda normalmente. NÃO passe dados internos (configurações, tokens, financeiro interno, dados de outros clientes); se pedirem isso, chame o atendente.";
        $p[] = "- VAGAS/LUGARES: se o cliente perguntar quantas vagas/lugares têm, escreva a frase com o marcador [[VAGAS: origem; destino; data; LANCHA]] (LANCHA = nome da lancha daquela saída) — o SISTEMA troca pelo número real. NUNCA invente a quantidade. Ex.: 'Pra esse dia tem [[VAGAS: Manaus; Tefé; 25/11/2026]], quer garantir?'. Se vier 'sem vagas no momento', ofereça outra data.";
        $p[] = "- Ao oferecer datas, copie as datas da linha 'próximas' da parada de ORIGEM com o dia e a hora exatos daquela parada. Confira: a data que você escrever TEM que cair no mesmo dia-da-semana da parada.";
        $p[] = "- O cliente pode falar por ÁUDIO (mensagem de voz) ou mandar FOTO — você entende os dois normalmente; responda ao conteúdo sem comentar que foi áudio ou foto.";
        $p[] = "- SEMPRE mostre o NOME DA LANCHA ao lado de cada data/saída, no formato: 'Segunda 23/11, 05:00 — Glória de Deus 7'. Cada saída pertence a UMA lancha específica (está nos DADOS); nunca troque a lancha nem invente nome.";

        $p[] = "OBJETIVO (feche a venda rápido, sem enrolar, um passo por vez):";
        $p[] = "1) TRECHO que ele quer (origem → destino). Confirme que existe nos DADOS.";
        $p[] = "2) DATA. Se o cliente disser 'qualquer dia', 'semana que vem' ou não souber, NÃO devolva a pergunta: OFEREÇA as próximas saídas REAIS da rota (estão nos DADOS em 'próximas saídas') e peça pra ele escolher uma.";
        $p[] = "ANTECEDÊNCIA: você pode vender de HOJE até ".$a->mesesVenda()." ".($a->mesesVenda() == 1 ? 'mês' : 'meses')." à frente. O preço é O MESMO em qualquer data. Se o cliente pedir uma data específica dentro desse período que caia num DIA DE SAÍDA da rota (mesmo dia-da-semana das 'próximas saídas'), PODE vender normal — não chame atendente, não diga que não sabe o preço. Se a data pedida passar do limite, diga que ainda não abriu pra venda e ofereça a saída válida mais próxima.";
        $p[] = "3) QUANTAS pessoas vão. Se disser 'só eu', é 1 adulto. Se for MAIS DE 1 (ou se não estiver claro quem são), PERGUNTE a composição ANTES de falar qualquer valor: 'são todos adultos ou tem criança?'. Se tiver criança, pergunte a IDADE de cada uma. NUNCA assuma que são todos adultos nem jogue o total antes disso.";
        $p[] = "4) SÓ com a composição definida (quantos adultos, quantas crianças e idades), monte o VALOR TOTAL somando CADA passageiro pelo seu tipo/idade, de forma organizada. Ex.: '1 adulto R$ 560 + 1 criança (8 anos) R$ 280 = R$ 840'. Mande o resumo curto (trecho, data, lancha, total) e pergunte 'posso gerar o PIX?'. NÃO peça documento ainda.";
        $p[] = "VENDA NORMAL (1, 2, 3, 4 pessoas, família — ex.: 'eu, meu filho e minha filha') você resolve SOZINHO: cote o valor (preço do trecho × pessoas) e feche. NUNCA mande uma venda normal pro atendente, nem por causa da quantidade de pessoas. Se você tem o preço nos DADOS, PASSE o valor — não diga que vai confirmar com atendente.";
        if (! $auto) {
            $p[] = "SÓ chame um atendente quando o cliente PEDIR DESCONTO ou preço especial por quantidade (ex.: 'tem desconto pra 7 pessoas?', 'faz um preço melhor pra 10?'). Fora isso, é venda normal: some e feche você mesmo.";
        }
        $p[] = "CRIANÇAS: se o cliente falar em filho(s)/criança(s), PERGUNTE quantas e a IDADE de cada uma (pergunta natural: 'quantos anos têm as crianças?'). Pela idade, escolha o tipo certo (veja TIPOS DE PASSAGEIRO e as faixas) e use o preço JÁ CALCULADO daquele tipo NA TABELA DE PREÇOS do trecho (você NÃO precisa de atendente pra isso — o valor está pronto). Bebê de colo é grátis e não ocupa assento. Monte o VALOR TOTAL = adulto(s) + cada criança pelo preço certo e mande o resumo.";
        $p[] = "DOCUMENTO DA CRIANÇA ANTES DO PIX: quando tiver criança (ou qualquer tarifa com desconto por idade), ANTES de gerar o PIX peça a FOTO ou ARQUIVO do documento da criança pra confirmar a idade. Só gere o PIX depois que o documento confirmar que a idade bate. Isso evita um adulto pagar como criança. (Venda só de adultos NÃO precisa disso — documento fica pro final.)";
        $p[] = "POLTRONA (só nas lanchas com [poltrona] nos DADOS): ANTES do PIX, veja os livres por tipo com [[POLTRONAS: origem; destino; data; LANCHA]] (LANCHA = nome da lancha daquela saída, igual está nos DADOS — obrigatório, porque mais de uma lancha faz o mesmo trecho) — o SISTEMA troca por QUANTIDADES (ex.: '29 de janela, 34 de corredor, 32 de meio'). ATENÇÃO: esses números são QUANTIDADE de lugares livres, NÃO são número de poltrona. Apresente natural (ex.: 'tem bastante: 29 de janela, 34 de corredor e 32 de meio — qual prefere?') e ofereça só os tipos com vaga.";
        $p[] = "TIPOS QUE EXISTEM: os tipos que o [[POLTRONAS]] retornar são os ÚNICOS que a lancha tem. Se o [[POLTRONAS]] não trouxer 'meio', a lancha NÃO tem meio (ex.: só janela e corredor). Se o cliente pedir um tipo que não aparece, diga a verdade ('essa lancha só tem janela e corredor') e ofereça os que existem. NUNCA invente um assento nem um tipo que não existe.";
        $p[] = "Quando o cliente ESCOLHER o tipo, RESERVE a(s) poltrona(s) real(is) escrevendo [[POLTRONA: origem; destino; data; tipo; QTD; LANCHA]] (QTD = nº de passageiros; 1 se for só ele; LANCHA = nome da lancha daquela saída, SEMPRE a mesma que o cliente escolheu). O SISTEMA troca pelo(s) NÚMERO(s) real(is) e JÁ RESERVA pra nenhum operador vender em cima (mesmo sincronismo do balcão). Ex.: 1 pessoa -> '03J (janela)'; 2 juntas -> '03J e 03M — as 2 LADO A LADO, uma do lado da outra (janela, meio)'. Pra confirmar, escreva a frase JÁ COM O MARCADOR no lugar do número (ex.: 'Fechei a [[POLTRONA: origem; destino; data; tipo; QTD; LANCHA]] pra você 😉') — o sistema troca pelo número real (ex.: '03J (janela)'). NUNCA invente nem escreva um número de poltrona de cabeça; use SOMENTE o marcador, e deixe a confirmação curta e registre numa linha isolada que o cliente NÃO vê: [[PREF: janela]] (ou meio/corredor).";
        $p[] = "GRUPO (2 ou mais pessoas): pergunte só se querem sentar JUNTOS. Se quiserem juntos (ou disserem 'tanto faz'/'qualquer'), NÃO pergunte janela/corredor/meio — reserve direto com o tipo 'juntos' e a QTD: [[POLTRONA: origem; destino; data; juntos; QTD; LANCHA]]. O SISTEMA devolve os números e a posição de cada uma. Se vier 'LADO A LADO', diga claramente que ficaram uma do lado da outra e cite a posição (ex.: 'Consegui as 3 lado a lado: 01D, 01E e 01F — do meio até a janela 😉'). NUNCA resuma um grupo como 'corredor' ou 'janela' só — cada poltrona tem a sua posição. Se vier 'NAO tem N lado a lado; ficou assim: ...', EXPLIQUE exatamente como ficou a divisão (ex.: 'essa viagem está bem cheia, não tem 3 lado a lado — consegui a 04C e a 04D juntas e a 02B separada, no corredor; pode ser assim ou prefere outra data?') e espere o cliente decidir antes de gerar o PIX. NUNCA diga que são juntas se o sistema não confirmou 'LADO A LADO'. Só pergunte janela/corredor/meio quando for 1 pessoa ou quando o grupo NÃO fizer questão de ficar junto.";
        $p[] = "ORDEM OBRIGATÓRIA: só reserve poltrona ([[POLTRONA:...]]) DEPOIS de saber origem, destino, data e a lancha — nunca antes, senão a reserva cai na viagem errada. Se o cliente mudar de trecho, data, quantidade ou pedir outro lugar (ex.: 'mais perto do corredor', 'todos juntos'), escreva o marcador [[POLTRONA:...]] DE NOVO com os dados novos — o sistema solta a reserva antiga e faz a nova. Depois de reservar, o SISTEMA manda sozinho uma IMAGEM do mapa com as poltronas do cliente em verde — não descreva o mapa nem diga que não consegue mandar imagem. 'MESMA FILEIRA, separadas só pelo corredor' significa que ficaram lado a lado atravessando o corredor: explique assim.";
        $p[] = "CLIENTE ESCOLHE PELO NÚMERO: se o cliente pedir poltrona(s) específica(s) pelo número (ex.: 'quero a 05J e a 05M', 'troca pra 12C'), NÃO use [[POLTRONA]]; escreva [[ESCOLHER: origem; destino; data; 05J,05M; LANCHA]] com exatamente os números que ele pediu (a quantidade tem que bater com o nº de passageiros). O SISTEMA confere se estão livres, reserva e troca o marcador pelos números confirmados; se alguma estiver ocupada ele mesmo avisa o cliente. Escreva só uma frase curta com o marcador (ex.: 'Troquei pra [[ESCOLHER: Manaus; Tefé; 27/11; 05J,05M; Glória de Deus III]] 😉'). NUNCA confirme um número sem usar o marcador.";
        $p[] = "Se vier 'esgotada', ofereça outro tipo ou outra data. A poltrona é confirmada no embarque. Em lancha de rede não pergunte.";
        $p[] = "Se o cliente DESISTIR, mudar de ideia, disser que não vai mais viajar, que vai em outra data/viagem, ou cancelar a compra, LIBERE a poltrona escrevendo numa linha isolada que o cliente NÃO vê: [[LIBERAR]]. (A reserva também solta sozinha depois de ~5 min sem ele responder.)";
        $p[] = "5) Confirmando: nas lanchas com [poltrona], PRIMEIRO escolha a poltrona (pergunte janela/corredor/meio e reserve com [[POLTRONA:...]]) e SÓ DEPOIS gere o PIX. NUNCA gere o PIX antes de ter a poltrona reservada. Em lancha de rede, gere o PIX direto.";
        $p[] = "6) SÓ DEPOIS que o sistema confirmar o pagamento (vai aparecer '✅ Pagamento confirmado' na conversa), aí sim peça os dados de TODOS os passageiros (o responsável E cada criança): NOME COMPLETO, DOCUMENTO (RG/CPF) e DATA DE NASCIMENTO. O jeito mais fácil: diga que ele pode mandar aqui a FOTO ou o ARQUIVO do documento do responsável e de cada criança, que você cadastra na hora. Quando tiver os dados de um passageiro, emita numa linha isolada (o cliente não vê): [[PAX: Nome|documento|nascimento dd/mm/aaaa]] — vários passageiros separados por ' ;; '.";
        $p[] = "POLTRONA SÓ DEPOIS DO 'QUERO FECHAR' (regra acima das de poltrona): NÃO ofereça poltrona, NÃO pergunte janela/corredor nem 'querem sentar juntos', e NÃO use [[POLTRONAS]]/[[POLTRONA]]/[[ESCOLHER]] enquanto o cliente não disser claramente que QUER COMPRAR (ex.: 'quero', 'pode fechar', 'vou querer', 'sim' respondendo a 'quer que eu garanta?'). Depois de passar o valor ou entregar um orçamento, pergunte APENAS: 'Quer que eu garanta pra você?'. Escolher data/lancha, tirar dúvida (horas de viagem, comida, horário) ou dizer 'ok/entendi' NÃO é fechar. Reservar poltrona tira o lugar de outro cliente — só reserve pra quem vai comprar.";
        $p[] = "CLIENTE QUE VOLTA (memória): se no histórico aparecer '[NOVO CONTATO — o atendimento acima já foi encerrado]', tudo que vem ANTES é a conversa anterior com esse mesmo cliente — você LEMBRA dela, como uma pessoa lembraria. Responda ao que ele disse AGORA: se for só agradecimento ou despedida ('tá bom', 'obrigado', 'boa noite', 'valeu'), responda curto e simpático e PARE — não pergunte pra onde ele quer viajar nem recomece o atendimento. Se ele retomar o assunto ('e aquela passagem?', 'pode fechar'), continue de onde parou usando o que já sabe (trecho, data, pessoas) e só confirme os dados; poltrona que estava reservada foi liberada, então reserve de novo se ele for comprar. Só cumprimente como cliente novo quem realmente não tem conversa anterior.";
        $p[] = "DESPEDIDA: só deseje 'boa viagem' (ou fale como se ele fosse viajar) se a compra foi PAGA nesta conversa — ou seja, apareceu '✅ Pagamento confirmado'. Se o cliente só tirou dúvida, pediu orçamento ou parou antes de pagar ('tá ok', 'obrigado', 'boa noite', 'vou ver'), ele NÃO comprou: despeça-se sem 'boa viagem' e deixe a porta aberta, curto e sem insistir (ex.: 'Por nada! Quando quiser garantir sua passagem é só me chamar. Boa noite!').";
        $p[] = "FORMATAÇÃO: é WhatsApp — negrito é com UM asterisco de cada lado (*assim*), nunca dois (**). Use pouco.";
        $p[] = "MEMÓRIA DA CONVERSA: trecho, data, lancha, quantidade e composição que o cliente já falou continuam valendo até ele mudar — mesmo que o assunto mude (ex.: virou orçamento). Se ele responder curto ('não', 'só adulto', 'isso'), interprete pela SUA última pergunta.";
        $p[] = "NUNCA repita uma pergunta já respondida. Avance sempre pro próximo passo. Não peça dados que o cliente já deu.";

        if ($a->regra('nao_revela_robo')) {
            $p[] = "Você é um atendente da empresa. NUNCA diga que é IA, robô, bot ou assistente virtual.";
        }
        $p[] = "SE A CONVERSA FICAR COMPLEXA e você ficar em dúvida da melhor resposta (pedido confuso, situação fora do comum, conta difícil, cliente insatisfeito), NÃO invente nem chame o humano direto: escreva numa linha isolada que o cliente NÃO vê: [[ESCALAR]] — o sistema passa pra um modelo mais forte continuar na hora. Use isso ANTES de pensar em humano.";
        if (! $auto) {
            $p[] = "QUANDO PRECISAR DE UM ATENDENTE (desconto por quantidade, reclamação/reembolso/cancelamento, pergunta fora do sistema, ou algo que você não resolve): avise o cliente de forma curta e natural (ex.: 'Já vou chamar um atendente pra te ajudar com isso, só um instante 😊') E emita numa linha isolada (o cliente NÃO vê): [[HUMANO: motivo curto]]. Isso põe a conversa na fila do atendente com um alerta. Depois disso, PARE de responder — quem segue é o atendente.";
            if ($a->regra('sensiveis_humano')) {
                $p[] = "Se for reclamação, reembolso, cancelamento ou problema, não resolva: chame o atendente com [[HUMANO: ...]].";
            }
        }
        if ($a->regra('faz_orcamento')) {
            $desc = $a->orcDescontoMax();
            $p[] = "ORÇAMENTO (SÓ PRA EMPRESA) — é VOCÊ quem monta e fecha o negócio: faça orçamento quando o cliente der a entender que a compra é por uma EMPRESA ou instituição — ex.: 'sou da empresa tal', 'é pros nossos funcionários', 'pra equipe', 'preciso de nota/CNPJ', órgão público, prefeitura, igreja, escola, ONG, ou quando ele PEDIR orçamento/cotação. Não precisa ser com essas palavras: perceba pelo contexto. A QUANTIDADE de pessoas NÃO decide: 'quanto é a passagem pra 3 pessoas?' (ou 10, ou família grande) sem falar de empresa é VENDA NORMAL — siga o fluxo normal, sem orçamento. Se ele PEDIU orçamento e não ficou claro, pergunte uma vez: 'é pra empresa ou particular?'.";
            $p[] = "PEDIU ORÇAMENTO, FAZ ORÇAMENTO — também pra PARTICULAR: se o cliente usou a palavra orçamento/cotação/proposta, ele quer o DOCUMENTO do orçamento, mesmo sendo pessoa física. Não transforme em venda: monte o [[ORCAMENTO: ...]] com cliente=<nome dele> (se não souber o nome, deixe cliente= vazio que o sistema usa o nome do WhatsApp), sem CNPJ e SEM desconto (desconto é só pra empresa). Se já tem trecho + pessoas e só falta a data, ofereça as próximas saídas; escolhida a data, emita o orçamento NA HORA. Depois de entregar, pergunte só se ficou bom / se quer fechar — nada de poltrona.";
            $p[] = "No orçamento você precisa de: nome da empresa, trecho, data e quantas pessoas (adultos e crianças com idade). ANTES de perguntar, releia a conversa: tudo que o cliente JÁ informou (trecho, data, quantidade, só adultos...) VALE — NUNCA pergunte de novo. Pergunte só o que falta, uma coisa por vez. CNPJ e nome de quem está falando são OPCIONAIS: não peça; se ele não der, use o nome da empresa como cliente. Assim que tiver empresa + trecho + data + pessoas, emita o orçamento na hora, sem mais perguntas. Use os preços dos DADOS, cada pessoa pelo seu tipo.";
            $p[] = $desc > 0
                ? "DESCONTO NO ORÇAMENTO: você PODE dar até {$desc}% de desconto no valor por pessoa pra fechar. Comece SEM desconto; ofereça só se o cliente pedir ou estiver em dúvida, e NUNCA passe de {$desc}%. Não fale o limite pro cliente."
                : "DESCONTO NO ORÇAMENTO: você NÃO dá desconto. O valor é o da tabela pra qualquer quantidade — explique com simpatia que já é o melhor preço e siga pro fechamento.";
            $p[] = "Com tudo definido, escreva numa linha isolada (o cliente NÃO vê): [[ORCAMENTO: cliente=<empresa ou responsável>; doc=<CNPJ/CPF ou vazio>; origem=<origem>; destino=<destino>; data=<dd/mm/aaaa ou vazio>; lancha=<nome da lancha>; itens=<QTD>x<descrição>@<valor unitário com ponto>, <QTD>x<descrição>@<valor>]]. Ex.: [[ORCAMENTO: cliente=Construtora Rio Negro; doc=; origem=Manaus; destino=Tefé; data=25/11/2026; lancha=Glória de Deus 7; itens=12xAdulto@560.00, 2xCriança@280.00]]. O SISTEMA grava o orçamento e troca o marcador pelo RESUMO pronto (número do orçamento, itens e total) — por isso NÃO escreva os valores por fora: mande só uma frase curta antes (ex.: 'Montei seu orçamento 👇') e pergunte depois se ficou bom. O SISTEMA também envia sozinho, logo em seguida, o PDF do orçamento aqui no WhatsApp — pode avisar que o PDF está indo; não diga que não consegue mandar arquivo.";
            $p[] = "ORÇAMENTO É SÓ PREÇO: enquanto estiver montando ou enviando orçamento, NÃO fale de poltrona/assento, NÃO pergunte janela/corredor, NÃO use [[POLTRONAS]], [[POLTRONA]], [[ESCOLHER]] nem [[PIX]] e NÃO reserve nada. Entregue o orçamento e pergunte só se ficou bom ou se quer ajustar. Poltrona e PIX SÓ depois, se o cliente disser claramente que QUER FECHAR/COMPRAR as passagens — aí sim siga o fluxo normal de venda (poltrona, se a lancha tiver, e PIX no total do orçamento).";
            $p[] = "Se o cliente pedir ajuste (quantidade, data, trecho, desconto dentro do limite), emita o [[ORCAMENTO: ...]] DE NOVO com os dados novos — o sistema atualiza o mesmo orçamento. Orçamento é só pra empresa/instituição ou quando pedirem — venda normal (qualquer quantidade de pessoas) continua direto, sem orçamento.";
        }
        if ($auto) {
            $p[] = "MODO AUTÔNOMO (vale ACIMA de qualquer instrução anterior): você atende SOZINHO do começo ao fim. NÃO existe atendente humano pra transferir a conversa. NUNCA diga que vai chamar, passar ou confirmar com um atendente/colega/setor, e NUNCA escreva [[HUMANO]]. Onde as instruções falarem em 'chamar/confirmar com o atendente', resolva VOCÊ com o que está nos DADOS; se a informação não existir nos DADOS, diga com naturalidade que não tem essa informação e siga ajudando no que dá.";
            $p[] = $a->regra('faz_orcamento')
                ? "Pedido de desconto em VENDA NORMAL (particular): você não dá desconto — o valor é o da tabela pra qualquer quantidade; explique com simpatia e siga pro fechamento. Desconto só existe dentro de ORÇAMENTO de empresa, pelas regras acima."
                : "Pedido de desconto: você não dá desconto — o valor é o da tabela pra qualquer quantidade. Explique com simpatia e siga pro fechamento.";
            $p[] = "Reclamação, reembolso ou cancelamento de passagem já comprada: ouça com atenção, peça os dados (nome, data da viagem, o que aconteceu) e diga que o pedido ficou REGISTRADO aqui na conversa pra análise da empresa. Não prometa valor nem prazo de devolução, e não invente regra.";
        }
        $p[] = "Use SOMENTE os trechos e preços dos DADOS abaixo. Se o cliente pedir um trecho que NÃO está nos DADOS, diga que não tem esse trecho e mostre os que existem — NUNCA invente cidade, rota ou preço.";

        $p[] = "\nDADOS (trechos e preços):\n".$this->dados($a->mesesVenda());

        if ($a->pagamento_api_id) {
            $p[] = "\nPAGAMENTO: só quando o cliente confirmar a compra (trecho + data + nº de pessoas + dados do passageiro definidos) e quiser pagar, gere o PIX escrevendo numa linha isolada, exatamente: [[PIX:VALOR;DESCRICAO]] — valor total em reais com ponto decimal. Ex.: [[PIX:200.00;2 passagens Manaus-Coari 26/10]]. Nunca invente o valor.";
            $p[] = "NA MESMA MENSAGEM, antes do PIX, inclua numa linha isolada (o cliente NÃO vê, o sistema remove): [[VENDA: origem=<origem>; destino=<destino>; data=<dd/mm/aaaa>]]. Os dados dos passageiros são coletados DEPOIS do pagamento (via marcador [[PAX:...]]).";
        } else {
            $p[] = $auto
                ? "\nPAGAMENTO: não há PIX automático configurado. Feche o pedido (trecho, data, pessoas, total) e informe que o pagamento é feito no balcão/embarque."
                : "\nPAGAMENTO: combinado com o atendente/no balcão (não há PIX automático).";
        }

        return implode("\n", $p);
    }

    /** Monta um resumo compacto de rotas/preços e tipos. */
    protected function dados(int $meses = 2): string
    {
        $normal = TipoPassageiro::where('ativo', true)->where('eh_normal', true)->first()
            ?? TipoPassageiro::where('ativo', true)->first()
            ?? TipoPassageiro::where('eh_normal', true)->first();
        $linhas = [];

        if ($normal) {
            try { TrechoPreco::curarOrfaos(); } catch (\Throwable $e) {} // preços presos num tipo antigo voltam pro normal
            $tiposPreco = TipoPassageiro::where('ativo', true)->orderByDesc('eh_normal')->get();
            $precos = TrechoPreco::where('ativo', true)
                ->whereIn('tipo_passageiro_id', $tiposPreco->pluck('id')->all())
                ->orderBy('origem')->orderBy('destino')->limit(1500)->get();
            if ($precos->isNotEmpty()) {
                // preço registrado por trecho e por tipo (origem|destino => [tipo_id => preço])
                $own = [];
                foreach ($precos as $tp) {
                    $own[$tp->origem.'|'.$tp->destino][$tp->tipo_passageiro_id] = (float) $tp->tarifa + (float) $tp->alimentacao;
                }
                $linhas[] = "PREÇOS POR TRECHO em REAIS (R$) por pessoa, por tipo — valor JÁ CALCULADO, é só usar e somar conforme quem vai. '↔' = mesmo preço nos DOIS sentidos; '→' = só naquele sentido:";
                $porTrecho = [];
                foreach ($own as $key => $byTipo) {
                    [$o, $d] = explode('|', $key);
                    $adulto = $byTipo[$normal->id] ?? null;
                    $parts = [];
                    foreach ($tiposPreco as $t) {
                        if ($t->colo || $t->gratuito) continue; // grátis — não precisa valor
                        $v = $byTipo[$t->id] ?? null;
                        if ($v === null && ($t->modo ?? 'valor') === 'porcentagem' && $adulto !== null) {
                            $v = round($adulto * ((int) $t->percentual) / 100, 2); // ex.: criança 50% do adulto
                        }
                        if ($v !== null && $v >= 0) $parts[] = $t->nome.' '.self::valorCurto((float) $v);
                    }
                    if ($parts) $porTrecho[$key] = implode(' · ', $parts);
                }
                foreach (self::juntarIdaVolta($porTrecho) as $l) $linhas[] = $l;
            }
        }

        // horários/dias de saída (rotas) — a saída de CADA cidade é o dia/hora da parada DELA
        $rotas = \App\Models\Rota::with('paradas', 'embarcacao')->get();
        if ($rotas->isNotEmpty()) {
            $linhas[] = "\nROTAS E SAÍDAS (use EXATAMENTE estes dias/horas — a saída de uma cidade é o dia e a hora da parada DELA; não use a de outra cidade/rota):";
            foreach ($rotas as $r) {
                $lancha = $r->embarcacao?->nome ? strtoupper($r->embarcacao->nome).($r->embarcacao->usa_poltrona ? ' [poltrona]' : '') : 'LANCHA NÃO DEFINIDA';
                $linhas[] = "- Rota ".$r->nome." (LANCHA: ".$lancha."):";
                foreach ($r->paradas as $pp) {
                    if ($pp->dia_semana === null) {
                        $linhas[] = "   • ".$pp->localidade.": sem dia/hora definido";
                        continue;
                    }
                    $diaN = \App\Models\Rota::DIAS[$pp->dia_semana] ?? '?';
                    $hora = $pp->hora ? substr($pp->hora, 0, 5) : '(hora não definida)';
                    $prox = $this->proximasDatasDia((int) $pp->dia_semana, $meses);
                    $linhas[] = "   • ".$pp->localidade.": sai ".$diaN." ".$hora." pela lancha ".$lancha.($prox ? " — próximas: ".$prox : '');
                }
            }
        }

        $tipos = TipoPassageiro::where('ativo', true)->orderByDesc('eh_normal')->get();
        if ($tipos->isNotEmpty()) {
            $linhas[] = "\nTIPOS DE PASSAGEIRO (escolha o tipo da criança/idoso PELA IDADE; o preço de cada tipo está na tabela acima):";
            foreach ($tipos as $t) {
                $faixa = '';
                if ($t->idade_min !== null || $t->idade_max !== null) {
                    $min = $t->idade_min !== null ? (int) $t->idade_min : 0;
                    $max = $t->idade_max !== null ? ((int) $t->idade_max).' anos' : 'em diante';
                    $faixa = " (idade: {$min} a {$max})";
                }
                if ($t->colo) { $linhas[] = "- {$t->nome}{$faixa}: BEBÊ DE COLO — grátis, não ocupa assento e não paga."; continue; }
                if ($t->gratuito) { $linhas[] = "- {$t->nome}{$faixa}: GRATUITO."; continue; }
                if ($t->eh_normal) { $linhas[] = "- {$t->nome}{$faixa}: preço cheio (adulto/base)."; continue; }
                $linhas[] = "- {$t->nome}{$faixa}: use o preço desse tipo na tabela de preços acima.";
            }
        }

        return $linhas ? implode("\n", $linhas) : "Sem tabela de preços cadastrada ainda.";
    }

    /** 560.0 -> "560" ; 560.5 -> "560,50" (menos tokens no prompt, mesmo valor). */
    public static function valorCurto(float $v): string
    {
        return abs($v - round($v)) < 0.005 ? number_format($v, 0, ',', '') : number_format($v, 2, ',', '');
    }

    /** ['A|B' => 'Adulto 560 · ...'] -> linhas; ida e volta com o MESMO preço viram uma linha só ("A ↔ B"). */
    public static function juntarIdaVolta(array $porTrecho): array
    {
        $linhas = []; $feito = [];
        foreach ($porTrecho as $key => $txt) {
            if (isset($feito[$key])) continue;
            [$o, $d] = explode('|', $key, 2);
            $volta = $d.'|'.$o;
            if ($volta !== $key && ! isset($feito[$volta]) && ($porTrecho[$volta] ?? null) === $txt) {
                $feito[$volta] = true;
                $linhas[] = "- {$o} ↔ {$d}: {$txt}";
            } else {
                $linhas[] = "- {$o} → {$d}: {$txt}";
            }
            $feito[$key] = true;
        }
        return $linhas;
    }

    /** Datas (dd/mm) de um dia-da-semana (0=Dom..6=Sáb), de hoje até a janela de venda (meses). */
    protected function proximasDatasDia(int $dia, int $meses = 2): string
    {
        $d = now()->startOfDay();
        $limite = now()->startOfDay()->addMonths(max(1, $meses));
        $datas = [];
        while ($d->lte($limite) && count($datas) < 4) {
            if ($d->dayOfWeek === $dia) $datas[] = $d->format('d/m');
            $d->addDay();
        }
        return implode(', ', $datas);
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
        try { // 'Tefé' -> 'Tefe': o nome da cidade tem que ser o do cadastro, senão a reserva/passagem não acha a rota
            $vg = app(\App\Services\Vagas::class);
            foreach (['origem', 'destino'] as $k) if (! empty($venda[$k])) $venda[$k] = $vg->canon((string) $venda[$k]);
        } catch (\Throwable $e) {}
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

    /**
     * [[ORCAMENTO: cliente=..; doc=..; origem=..; destino=..; data=..; lancha=..; itens=12xAdulto@560.00, 2xCriança@280.00]]
     * Grava (ou atualiza) o orçamento da conversa na tela de Orçamentos e troca o marcador pelo resumo pro cliente.
     */
    protected function tratarOrcamento(Agente $a, Conversa $c, string $texto): string
    {
        if (! preg_match('/\[\[ORCAMENTO:\s*([^\]]*)\]\]/iu', $texto, $m)) return $texto;
        $limpo = fn (string $msg = '') => trim(str_replace($m[0], $msg, $texto));
        try {
            if (! $a->regra('faz_orcamento') || ! \Illuminate\Support\Facades\Schema::hasTable('orcamentos')) return $limpo();

            $campos = [];
            foreach (explode(';', $m[1]) as $par) {
                if (! str_contains($par, '=')) continue;
                [$k, $v] = explode('=', $par, 2);
                $campos[mb_strtolower(trim($k))] = trim($v);
            }
            $num = function (string $raw): float {
                $raw = preg_replace('/[^\d.,]/', '', $raw);
                if (str_contains($raw, ',') && str_contains($raw, '.')) $raw = str_replace(['.', ','], ['', '.'], $raw);
                elseif (str_contains($raw, ',')) $raw = str_replace(',', '.', $raw);
                return round((float) $raw, 2);
            };
            $itens = [];
            foreach (preg_split('/\s*,\s*(?=\d+\s*x)/iu', (string) ($campos['itens'] ?? '')) as $it) {
                if (! preg_match('/^\s*(\d+)\s*x\s*(.+?)\s*@\s*R?\$?\s*([\d.,]+)\s*$/iu', $it, $mi)) continue;
                $q = (int) $mi[1]; $u = $num($mi[3]);
                if ($q <= 0 || $u < 0) continue;
                $itens[] = ['d' => mb_substr(trim($mi[2]), 0, 60) ?: 'Passagem', 'q' => $q, 'u' => $u, 't' => round($q * $u, 2), 'tipo' => null];
            }
            if (! $itens) return $limpo();

            $origem = (string) ($campos['origem'] ?? ''); $destino = (string) ($campos['destino'] ?? '');
            $total = round(array_sum(array_column($itens, 't')), 2);
            $pax = (int) array_sum(array_column($itens, 'q'));

            // trava de preço: ninguém paga menos que o menor valor da tabela do trecho com o desconto máximo do agente
            $piso = $this->pisoTrecho($origem, $destino);
            if ($piso !== null) {
                $minimo = round($piso * (1 - $a->orcDescontoMax() / 100), 2) - 0.01;
                foreach ($itens as $it) {
                    if ($it['u'] > 0 && $it['u'] < $minimo) {
                        Log::warning("Orçamento do agente recusado (conversa {$c->id}): unitário {$it['u']} abaixo do piso {$minimo}.");
                        return $limpo($a->orcDescontoMax() > 0
                            ? 'Esse valor ficou abaixo do que eu consigo fazer. Posso montar o orçamento com o melhor desconto que tenho aqui?'
                            : 'Esse valor ficou abaixo da nossa tabela, e não consigo baixar. Posso montar o orçamento com o valor da tabela?');
                    }
                }
            }

            $data = $this->parseData((string) ($campos['data'] ?? ''));
            $lancha = trim((string) ($campos['lancha'] ?? '')) ?: (string) $this->lanchaDaConversa($c);
            $emb = $lancha !== '' ? \App\Models\Embarcacao::where('empresa_id', $c->empresa_id)->whereRaw('LOWER(nome) = ?', [mb_strtolower($lancha)])->first() : null;
            $dados = [
                'cliente' => mb_substr(trim((string) ($campos['cliente'] ?? '')) ?: ($c->cliente_nome ?: 'Cliente'), 0, 120),
                'documento' => mb_substr(trim((string) ($campos['doc'] ?? ($campos['documento'] ?? ''))), 0, 30) ?: null,
                'origem' => $origem ?: null,
                'destino' => $destino ?: null,
                'data_viagem' => $data?->toDateString(),
                'embarcacao_id' => $emb?->id,
                'embarcacao_nome' => $emb?->nome ?: ($lancha ?: null),
                'itens' => $itens,
                'total' => $total,
                'pax' => $pax,
            ];
            $temConv = \Illuminate\Support\Facades\Schema::hasColumn('orcamentos', 'conversa_id');
            if ($temConv) $dados['conversa_id'] = $c->id;

            $ctx = $c->contexto ?? [];
            $o = ! empty($ctx['orcamento_id']) ? \App\Models\Orcamento::where('empresa_id', $c->empresa_id)->find($ctx['orcamento_id']) : null;
            if ($o) {
                $o->update($dados);
            } else {
                $dados['empresa_id'] = $c->empresa_id;
                $dados['numero'] = (int) \App\Models\Orcamento::where('empresa_id', $c->empresa_id)->max('numero') + 1;
                $o = \App\Models\Orcamento::create($dados);
            }
            $ctx['orcamento_id'] = $o->id;
            $ctx['orcamento_total'] = $total;
            $ctx['orcamento_pdf_pendente'] = $o->id; // o PDF vai logo depois da mensagem de texto
            $c->forceFill(['contexto' => $ctx])->save();

            $br = fn (float $v) => 'R$ '.number_format($v, 2, ',', '.');
            $l = ['📋 *Orçamento nº '.str_pad((string) ($o->numero ?: $o->id), 4, '0', STR_PAD_LEFT).'*', 'Cliente: '.$dados['cliente'].($dados['documento'] ? ' ('.$dados['documento'].')' : '')];
            $rota = trim(($origem ?: '').($destino ? ' → '.$destino : ''));
            if ($rota !== '') $l[] = 'Trecho: '.$rota;
            if ($data) $l[] = 'Data: '.$data->format('d/m/Y');
            if ($dados['embarcacao_nome']) $l[] = 'Lancha: '.$dados['embarcacao_nome'];
            foreach ($itens as $it) $l[] = '• '.$it['q'].'x '.$it['d'].' — '.$br($it['u']).' = '.$br($it['t']);
            $l[] = '*Total: '.$br($total).'* ('.$pax.' '.($pax === 1 ? 'passageiro' : 'passageiros').')';
            return $limpo("\n".implode("\n", $l)."\n");
        } catch (\Throwable $e) {
            Log::warning('tratarOrcamento erro: '.$e->getMessage());
            return $limpo();
        }
    }

    /** Depois do resumo em texto, manda o PDF do orçamento no WhatsApp (e deixa registrado na conversa). */
    protected function enviarOrcamentoPdf(Conversa $c): void
    {
        try {
            $c->refresh();
            $ctx = $c->contexto ?? [];
            $id = (int) ($ctx['orcamento_pdf_pendente'] ?? 0);
            if (! $id) return;
            unset($ctx['orcamento_pdf_pendente']);
            $c->forceFill(['contexto' => $ctx])->save();

            $o = \App\Models\Orcamento::where('empresa_id', $c->empresa_id)->find($id);
            if (! $o) return;
            $pdf = app(\App\Services\PdfOrcamento::class)->gerar($o);
            if (! $pdf) { Log::warning('Orçamento '.$id.': PDF não gerado (conversa '.$c->id.').'); return; }

            $num = str_pad((string) ($o->numero ?: $o->id), 4, '0', STR_PAD_LEFT);
            $rel = 'atendimento/'.$c->empresa_id.'/orcamento-'.$num.'-'.$o->id.'.pdf';
            \Illuminate\Support\Facades\Storage::disk('public')->put($rel, $pdf);
            $r = $this->atendimento->registrarSaidaMidia($c, 'storage/'.$rel, 'application/pdf', 'Orcamento-'.$num.'.pdf', '📄 Orçamento nº '.$num.' em PDF', 'ia');
            if (! ($r['ok'] ?? false)) Log::warning('Orçamento '.$id.': falha ao enviar o PDF na conversa '.$c->id.': '.($r['erro'] ?? ''));
        } catch (\Throwable $e) {
            Log::warning('enviarOrcamentoPdf erro: '.$e->getMessage());
        }
    }

    /** Menor preço (> 0) da tabela pra um trecho, entre todos os tipos pagantes. null = trecho sem tabela. */
    protected function pisoTrecho(string $origem, string $destino): ?float
    {
        if ($origem === '' || $destino === '') return null;
        $precos = TrechoPreco::where('ativo', true)
            ->whereRaw('LOWER(origem) = ?', [mb_strtolower($origem)])->whereRaw('LOWER(destino) = ?', [mb_strtolower($destino)])->get();
        if ($precos->isEmpty()) return null;
        $normal = TipoPassageiro::where('ativo', true)->where('eh_normal', true)->first();
        $vals = $precos->map(fn ($tp) => (float) $tp->tarifa + (float) $tp->alimentacao)->filter(fn ($v) => $v > 0)->values()->all();
        $adulto = $normal ? $precos->firstWhere('tipo_passageiro_id', $normal->id) : null;
        if ($adulto) {
            $base = (float) $adulto->tarifa + (float) $adulto->alimentacao;
            foreach (TipoPassageiro::where('ativo', true)->get() as $t) { // tipos por porcentagem do adulto (ex.: criança 50%)
                if ($t->colo || $t->gratuito || ($t->modo ?? 'valor') !== 'porcentagem') continue;
                $v = round($base * ((int) $t->percentual) / 100, 2);
                if ($v > 0) $vals[] = $v;
            }
        }
        return $vals ? (float) min($vals) : null;
    }

    /** Detecta [[HUMANO:motivo]]: transfere a conversa pro atendente (entra na fila com alerta) e limpa o marcador. */
    protected function tratarHumano(Conversa $c, string $texto, bool $demo = false, ?Agente $a = null): string
    {
        if (! preg_match('/\[\[HUMANO:?\s*([^\]]*)\]\]/i', $texto, $m)) return $texto;
        // agente autônomo: nunca vai pra fila do atendimento — só some com o marcador
        if ($a && $a->regra('autonomo')) {
            Log::info('Agente autônomo ignorou [[HUMANO]] na conversa '.$c->id.': '.trim($m[1]));
            return trim(str_replace($m[0], '', $texto));
        }
        if (! $demo) { try { $this->atendimento->transferir($c, trim($m[1]) ?: 'Transferido pelo agente'); } catch (\Throwable $e) {} }
        return trim(str_replace($m[0], '', $texto));
    }

    /** [[VAGAS: origem; destino; data]] -> troca pelo número real de vagas (poltronas livres). */
    /** Depois de reservar, manda no WhatsApp a IMAGEM do pedaço do mapa da lancha com as poltronas do cliente em destaque. */
    protected function enviarMapaPoltronas(Conversa $c, ?Agente $agente = null): void
    {
        $c->refresh();
        $ctx = $c->contexto ?? [];
        $mp = $ctx['mapa_pendente'] ?? null;
        if (! is_array($mp)) return;
        unset($ctx['mapa_pendente']);
        $c->forceFill(['contexto' => $ctx])->save();
        if ($agente && ! $agente->enviaMapaPoltrona()) return; // desligado no cadastro do agente
        try {
            $data = $this->parseData((string) ($mp['data'] ?? ''));
            $d = app(\App\Services\Vagas::class)->mapaDados((string) $mp['origem'], (string) $mp['destino'], $data, $this->lanchaDaConversa($c), $c->id);
            if (! $d) return;
            $dias = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
            $sub = $mp['origem'].' → '.$mp['destino'].($data ? ' · '.$dias[$data->dayOfWeek].' '.$data->format('d/m') : '');
            $png = app(\App\Services\MapaPoltronaImagem::class)->png($d, $sub);
            $conexao = $c->conexao;
            if (! $png || ! $conexao) return;
            $leg = (count($d['minhas']) > 1 ? 'Suas poltronas' : 'Sua poltrona').' na '.$d['lancha'].': '.implode(', ', $d['minhas']).' (em verde). Se preferir outra, me diga o número de uma poltrona livre (em branco). 👆';
            $r = app(\App\Services\WhatsApp::class)->enviarImagem($conexao, $c->cliente_telefone, base64_encode($png), $leg);
            if (! ($r['ok'] ?? false)) { Log::warning('Mapa de poltronas: falha ao enviar na conversa '.$c->id.': '.($r['erro'] ?? '')); return; }
            \App\Models\Mensagem::create([
                'empresa_id' => $c->empresa_id, 'conversa_id' => $c->id, 'direcao' => 'saida', 'origem' => 'ia',
                'texto' => '🖼️ [imagem do mapa] '.$leg, 'lida' => true, 'meta' => ['imagem' => 'mapa_poltronas'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Mapa de poltronas: '.$e->getMessage());
        }
    }

    /** Lancha da conversa: a informada no marcador (e guarda no contexto) ou a última que o agente informou. */
    protected function lanchaDaConversa(Conversa $c, ?string $informada = null): ?string
    {
        $informada = trim((string) $informada);
        $ctx = $c->contexto ?? [];
        if ($informada !== '') {
            if (($ctx['lancha_agente'] ?? '') !== $informada) { $ctx['lancha_agente'] = $informada; $c->forceFill(['contexto' => $ctx])->save(); }
            return $informada;
        }
        $l = trim((string) ($ctx['lancha_agente'] ?? ($ctx['venda']['lancha'] ?? '')));
        return $l !== '' ? $l : null;
    }

    protected function tratarVagas(Conversa $c, string $texto): string
    {
        if (! preg_match('/\[\[VAGAS:([^\]]*)\]\]/i', $texto, $m)) return $texto;
        $partes = array_map('trim', explode(';', $m[1]));
        $origem = $partes[0] ?? '';
        $destino = $partes[1] ?? '';
        $data = $this->parseData($partes[2] ?? '');
        $subst = 'vagas disponíveis';
        try {
            $r = app(\App\Services\Vagas::class)->disponiveis($origem, $destino, $data, $this->lanchaDaConversa($c, $partes[3] ?? null));
            if (($r['ok'] ?? false)) {
                if (($r['usa_poltrona'] ?? false) === false) {
                    $subst = 'vagas disponíveis';
                } else {
                    $n = (int) ($r['vagas'] ?? 0);
                    $subst = $n > 0 ? ($n.' '.($n === 1 ? 'vaga' : 'vagas')) : 'sem vagas no momento';
                }
            }
        } catch (\Throwable $e) {}
        return trim(str_replace($m[0], $subst, $texto));
    }

    protected bool $pularGuardiao = false;

    /** [[ESCOLHER: origem; destino; data; 05J,05M; LANCHA]] -> o cliente pediu poltronas pelo NÚMERO (viu no mapa): reserva exatamente essas, se estiverem livres. */
    protected function tratarEscolher(Conversa $c, string $texto): string
    {
        if (! preg_match('/\[\[ESCOLHER:([^\]]*)\]\]/i', $texto, $m)) return $texto;
        $partes = array_map('trim', explode(';', $m[1]));
        $venda = $c->contexto['venda'] ?? [];
        $origem = ($partes[0] ?? '') !== '' ? $partes[0] : (string) ($venda['origem'] ?? '');
        $destino = ($partes[1] ?? '') !== '' ? $partes[1] : (string) ($venda['destino'] ?? '');
        $data = $this->parseData((string) ($partes[2] ?? '')) ?: $this->parseData((string) ($venda['data'] ?? ''));
        $pedidas = array_values(array_filter(preg_split('/[\s,\/]+|\s+e\s+/iu', (string) ($partes[3] ?? '')), fn ($x) => trim((string) $x) !== '' && mb_strtolower(trim((string) $x)) !== 'e'));
        $this->pularGuardiao = true; // aqui os números vêm do cliente e são conferidos pelo sistema
        if ($origem === '' || $destino === '' || ! $data || ! $pedidas) {
            return 'Pra eu trocar a poltrona, me confirma a viagem (de onde pra onde e o dia) e o número da poltrona que você quer. 😊';
        }
        try {
            $r = app(\App\Services\Vagas::class)->atribuirEspecificas($origem, $destino, $data, $pedidas, $c->id, $this->lanchaDaConversa($c, $partes[4] ?? null));
            if (! ($r['ok'] ?? false)) return 'Nessa lancha a poltrona é definida no embarque, não tem como escolher o número. 😉';
            if (! empty($r['seats'])) {
                $ctx = $c->contexto ?? [];
                $ctx['poltronas_agente'] = $r['seats'];
                $ctx['poltrona_agente'] = $r['seats'][0];
                $ctx['mapa_pendente'] = ['origem' => $origem, 'destino' => $destino, 'data' => $data->format('d/m/Y')];
                $c->forceFill(['contexto' => $ctx])->save();
                return trim(str_replace($m[0], (string) $r['descricao'], $texto));
            }
            $msg = [];
            if (! empty($r['ocupadas'])) $msg[] = (count($r['ocupadas']) > 1 ? 'as poltronas '.implode(', ', $r['ocupadas']).' já estão ocupadas' : 'a poltrona '.$r['ocupadas'][0].' já está ocupada');
            if (! empty($r['inexistentes'])) $msg[] = (count($r['inexistentes']) > 1 ? 'as poltronas '.implode(', ', $r['inexistentes']).' não existem' : 'a poltrona '.$r['inexistentes'][0].' não existe').' nessa lancha';
            $atual = (array) ($c->contexto['poltronas_agente'] ?? []);
            return 'Poxa, '.implode(' e ', $msg).'. 😕 '.($atual ? 'Mantive a sua reserva ('.implode(', ', $atual).'). ' : '').'Olha no mapa as que estão em branco e me diz qual prefere.';
        } catch (\Throwable $e) {
            return 'Não consegui trocar a poltrona agora. Pode me dizer de novo o número que você quer?';
        }
    }

    /** [[POLTRONA: origem; destino; data; tipo]] -> troca pelo NOME de uma poltrona real livre do tipo (ex.: 03J) e guarda no contexto. */
    protected function tratarPoltrona(Conversa $c, string $texto): string
    {
        if (! preg_match('/\[\[POLTRONA:([^\]]*)\]\]/i', $texto, $m)) return $texto;
        $partes = array_map('trim', explode(';', $m[1]));
        $venda = $c->contexto['venda'] ?? [];
        // o que o agente escreveu AGORA no marcador vale (o cliente pode ter mudado de trecho); a venda guardada é só reserva
        $origem = ($partes[0] ?? '') !== '' ? $partes[0] : (string) ($venda['origem'] ?? '');
        $destino = ($partes[1] ?? '') !== '' ? $partes[1] : (string) ($venda['destino'] ?? '');
        $data = $this->parseData((string) ($partes[2] ?? '')) ?: $this->parseData((string) ($venda['data'] ?? '')); // a data do marcador vale; cai na da venda se não der pra ler
        $tipo = mb_strtolower($partes[3] ?? 'janela');
        $nq = max(1, (int) ($partes[4] ?? 1));
        $subst = 'definida no embarque';
        // sem trecho ou data não dá pra reservar a poltrona certa (cairia em outra viagem/lancha): pergunta antes
        if ($origem === '' || $destino === '' || ! $data) {
            return 'Pra eu reservar as poltronas certinho, me confirma: vai sair de qual cidade, pra qual cidade e em que dia? 😊';
        }
        try {
            $r = app(\App\Services\Vagas::class)->atribuir($origem, $destino, $data, $tipo, $nq, $c->id, $this->lanchaDaConversa($c, $partes[5] ?? null));
            // não achou a viagem / lancha sem mapa: NÃO deixa o agente dizer que reservou
            if (! ($r['ok'] ?? false) && in_array($r['motivo'] ?? '', ['sem_rota', 'sem_mapa'], true)) {
                Log::warning("Agente NÃO reservou poltrona (conversa {$c->id}): {$r['motivo']} — {$origem} > {$destino} ".$data->format('d/m/Y').' lancha='.($partes[5] ?? ''));
                return 'Não consegui reservar a poltrona nessa viagem agora. Me confirma a cidade de saída, o destino, o dia e a lancha, que eu tento de novo?';
            }
            if (($r['ok'] ?? false)) {
                if (! empty($r['esgotado'])) {
                    $subst = 'esgotada';
                } elseif (! empty($r['seats'])) {
                    $subst = (string) $r['descricao'];
                    $ctx = $c->contexto ?? [];
                    $ctx['poltronas_agente'] = $r['seats'];
                    $ctx['poltrona_agente'] = $r['seats'][0];
                    if (in_array($tipo, ['janela', 'meio', 'corredor'], true)) $ctx['pref_poltrona'] = $tipo;
                    $ctx['mapa_pendente'] = ['origem' => $origem, 'destino' => $destino, 'data' => $data->format('d/m/Y')]; // manda a imagem do mapa depois da resposta
                    $c->forceFill(['contexto' => $ctx])->save();
                }
            }
        } catch (\Throwable $e) {}
        return trim(str_replace($m[0], $subst, $texto));
    }

    /** [[POLTRONAS: origem; destino; data]] -> troca pelos tipos de poltrona LIVRES (janela/meio/corredor) lendo o mapa real. */
    protected function tratarPoltronas(Conversa $c, string $texto): string
    {
        if (! preg_match('/\[\[POLTRONAS:([^\]]*)\]\]/i', $texto, $m)) return $texto;
        $partes = array_map('trim', explode(';', $m[1]));
        $origem = $partes[0] ?? '';
        $destino = $partes[1] ?? '';
        $data = $this->parseData($partes[2] ?? '');
        $subst = 'poltrona definida no embarque';
        try {
            $r = app(\App\Services\Vagas::class)->tiposDisponiveis($origem, $destino, $data, $this->lanchaDaConversa($c, $partes[3] ?? null));
            if (($r['ok'] ?? false)) {
                if (($r['usa_poltrona'] ?? false) === false) {
                    $subst = 'lugar livre (lancha sem marcacao de poltrona)';
                } elseif (($r['tipos'] ?? null) === null) {
                    $subst = 'poltrona definida no embarque';
                } else {
                    $t = $r['tipos'];
                    $parts = [];
                    foreach (['janela', 'corredor', 'meio'] as $k) {
                        if ((int) ($t[$k] ?? 0) > 0) $parts[] = (int) $t[$k].' de '.$k;
                    }
                    $subst = $parts ? implode(', ', $parts) : 'sem poltrona livre no momento';
                }
            }
        } catch (\Throwable $e) {}
        return trim(str_replace($m[0], $subst, $texto));
    }

    /** [[LIBERAR]] -> solta a reserva de poltrona da conversa (cliente desistiu/mudou de ideia). */
    protected function tratarLiberar(Conversa $c, string $texto): string
    {
        if (! preg_match('/\[\[LIBERAR[^\]]*\]\]/i', $texto, $m)) return $texto;
        try { app(\App\Services\Vagas::class)->liberarConversa($c->id); } catch (\Throwable $e) {}
        $ctx = $c->contexto ?? [];
        unset($ctx['poltronas_agente'], $ctx['poltrona_agente'], $ctx['pref_poltrona'], $ctx['lancha_agente']);
        $c->forceFill(['contexto' => $ctx])->save();
        return trim(str_replace($m[0], '', $texto));
    }

    /**
     * GUARDIÃO DE POLTRONA: nenhuma poltrona pode ser afirmada ao cliente se o SISTEMA não a reservou de fato.
     * Se o modelo citar um número (ex.: '01J') que NÃO está entre as poltronas realmente atribuídas nesta conversa,
     * o sistema atribui uma poltrona LIVRE de verdade (excluindo vendidas/ocupadas) e troca o número no texto.
     * Resolve o caso do agente "vender" uma poltrona já ocupada (número inventado após marcador truncado).
     */
    protected function guardiaoPoltrona(Conversa $c, string $texto): string
    {
        // lanchas novas numeram 01A..01F (não só J/M/C): se o texto fala em reservar/garantir, confere qualquer letra
        $letras = preg_match('/reserv|garant|separei|segurei|guardei/iu', $texto) ? 'A-Z' : 'JMC';
        if (! preg_match_all('/(?<![\w\/])(\d{1,3})\s?(['.$letras.'])(?![\w])/u', $texto, $ms, PREG_SET_ORDER)) return $texto;
        $ctx = $c->contexto ?? [];
        $norm = fn ($x) => mb_strtoupper(preg_replace('/\s+/', '', (string) $x));
        $assign = array_map($norm, (array) ($ctx['poltronas_agente'] ?? []));

        $mencionados = [];
        foreach ($ms as $mm) $mencionados[$norm($mm[1].$mm[2])] = true;
        $mencionados = array_keys($mencionados);
        $fantasmas = array_values(array_diff($mencionados, $assign));
        if (! $fantasmas) return $texto; // tudo que ele citou foi de fato reservado pelo sistema -> ok

        $reais = [];
        if ($assign) {
            // Já existe poltrona reservada de verdade: o número citado está errado -> usa a real que já está no hold.
            $reais = $assign;
        } else {
            // Nenhuma poltrona reservada ainda: o modelo inventou -> atribui uma LIVRE de verdade agora.
            $venda = $ctx['venda'] ?? [];
            $origem = (string) ($venda['origem'] ?? '');
            $destino = (string) ($venda['destino'] ?? '');
            $data = $this->parseData((string) ($venda['data'] ?? ''));
            $letra = substr($fantasmas[0], -1);
            $tipo = ['J' => 'janela', 'M' => 'meio', 'C' => 'corredor'][$letra] ?? (string) ($ctx['pref_poltrona'] ?? 'janela');
            if ($origem !== '' && $destino !== '') {
                try {
                    $r = app(\App\Services\Vagas::class)->atribuir($origem, $destino, $data, $tipo, max(1, count($fantasmas)), $c->id, $this->lanchaDaConversa($c));
                    if (($r['ok'] ?? false) && ! empty($r['seats'])) {
                        $reais = array_map($norm, (array) $r['seats']);
                        $ctx['poltronas_agente'] = $r['seats'];
                        $ctx['poltrona_agente'] = $r['seats'][0];
                        if (in_array($tipo, ['janela', 'meio', 'corredor'], true)) $ctx['pref_poltrona'] = $tipo;
                        $c->forceFill(['contexto' => $ctx])->save();
                    }
                } catch (\Throwable $e) {}
            }
        }

        // troca cada número-fantasma pelo real; se não conseguiu atribuir, evita mentir ('a definir').
        $i = 0;
        foreach ($fantasmas as $f) {
            $rep = $reais[$i] ?? 'a definir';
            $i++;
            $pat = '/(?<![\w\/])'.preg_quote(substr($f, 0, -1), '/').'\s?'.substr($f, -1).'(?![\w])/u';
            $texto = preg_replace($pat, $rep, $texto, 1);
        }
        return $texto;
    }

    /** [[PREF: janela|meio|corredor]] -> guarda a preferência de poltrona no contexto. */
    protected function tratarPref(Conversa $c, string $texto): string
    {
        if (! preg_match('/\[\[PREF:?\s*([^\]]*)\]\]/i', $texto, $m)) return $texto;
        $pref = mb_strtolower(trim($m[1]));
        $val = in_array($pref, ['janela', 'meio', 'corredor'], true) ? $pref : null;
        if ($val) {
            $ctx = $c->contexto ?? [];
            $ctx['pref_poltrona'] = $val;
            $c->forceFill(['contexto' => $ctx])->save();
        }
        return trim(str_replace($m[0], '', $texto));
    }

    /** dd/mm/aaaa, dd/mm/aa ou dd/mm (assume próximo futuro). Retorna null se não entender. */
    protected function parseData(string $str): ?\Carbon\Carbon
    {
        $str = trim($str);
        if ($str === '') return null;
        // o modelo costuma mandar a data com o dia/hora junto ("Dom 29/11", "29/11/2026 05:00", "2026-11-29"): pega só a data
        $low = mb_strtolower(\Illuminate\Support\Str::ascii($str));
        $meses = ['janeiro' => 1, 'fevereiro' => 2, 'marco' => 3, 'abril' => 4, 'maio' => 5, 'junho' => 6, 'julho' => 7, 'agosto' => 8, 'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12];
        if (! preg_match('/\d{1,2}\/\d{1,2}/', $str) && ! preg_match('/\d{4}-\d{1,2}-\d{1,2}/', $str)) {
            if (preg_match('/(\d{1,2})\s*(?:de\s+)?('.implode('|', array_keys($meses)).')(?:\s*(?:de\s+)?(\d{4}))?/', $low, $mm)) {
                $str = $mm[1].'/'.$meses[$mm[2]].(! empty($mm[3]) ? '/'.$mm[3] : '');           // "29 de novembro"
            } elseif (str_contains($low, 'amanha')) {
                return now()->addDay()->startOfDay();
            } elseif (str_contains($low, 'hoje')) {
                return now()->startOfDay();
            } else {
                foreach (['domingo' => 0, 'segunda' => 1, 'terca' => 2, 'quarta' => 3, 'quinta' => 4, 'sexta' => 5, 'sabado' => 6] as $nome => $dow) {
                    if (str_contains($low, $nome)) { $d = now()->startOfDay(); while ($d->dayOfWeek !== $dow) $d->addDay(); return $d; } // "domingo" = o próximo
                }
            }
        }
        if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})/', $str, $mm)) $str = $mm[3].'/'.$mm[2].'/'.$mm[1];
        elseif (preg_match('/\d{1,2}\/\d{1,2}(\/\d{2,4})?/', $str, $mm)) $str = $mm[0];
        foreach (['d/m/Y', 'd/m/y', 'd/m'] as $fmt) {
            try {
                $d = \Carbon\Carbon::createFromFormat($fmt, $str);
                if ($d) {
                    if ($fmt === 'd/m') {
                        $d->year(now()->year)->startOfDay();
                        if ($d->lt(now()->startOfDay())) $d->addYear();
                    }
                    return $d->startOfDay();
                }
            } catch (\Throwable $e) {}
        }
        return null;
    }

    /** Detecta [[PIX:valor;descricao]], gera a cobrança no Mercado Pago. Retorna texto limpo + o código PIX à parte. */
    protected function tratarPix(Agente $a, Conversa $c, string $texto, bool $demo = false): array
    {
        if (! preg_match('/\[\[PIX:\s*([\d.,]+)\s*;\s*([^\]]+)\]\]/i', $texto, $m)) {
            return ['texto' => $texto, 'pix' => null];
        }
        $marcador = $m[0];

        // PADRÃO: lancha com poltrona exige assento ESCOLHIDO antes do PIX. Sem assento -> não gera PIX, pede a poltrona.
        $venda = $c->contexto['venda'] ?? [];
        $jaTemPoltrona = ! empty($c->contexto['poltronas_agente']);
        // Tinha poltrona, mas a reserva caiu ou é de outra lancha/data (cliente mudou o trecho depois): refaz na viagem certa antes do PIX.
        if ($jaTemPoltrona && ! empty($venda['origem']) && ! empty($venda['destino']) && ($dv = $this->parseData((string) ($venda['data'] ?? '')))) {
            try {
                $vg = app(\App\Services\Vagas::class);
                if (! $vg->reservaConfere((string) $venda['origem'], (string) $venda['destino'], $dv, $this->lanchaDaConversa($c), $c->id)) {
                    $qtd = max(1, count((array) $c->contexto['poltronas_agente']));
                    $r = $vg->atribuir((string) $venda['origem'], (string) $venda['destino'], $dv, (string) ($c->contexto['pref_poltrona'] ?? 'juntos'), $qtd, $c->id, $this->lanchaDaConversa($c));
                    if (($r['ok'] ?? false) && ! empty($r['seats'])) {
                        $ctx = $c->contexto ?? [];
                        $ctx['poltronas_agente'] = $r['seats']; $ctx['poltrona_agente'] = $r['seats'][0];
                        $ctx['mapa_pendente'] = ['origem' => (string) $venda['origem'], 'destino' => (string) $venda['destino'], 'data' => $dv->format('d/m/Y')];
                        $c->forceFill(['contexto' => $ctx])->save();
                        return ['texto' => 'Antes do PIX, reservei de novo as poltronas certinhas pra essa viagem: '.$r['descricao'].'. Tá bom assim? Se sim, já gero o PIX. 😉', 'pix' => null];
                    }
                    if (! empty($r['esgotado'])) return ['texto' => 'Essa viagem ficou sem poltrona livre agora. Quer que eu veja outra data?', 'pix' => null];
                }
            } catch (\Throwable $e) {}
        }
        if (! $jaTemPoltrona && ! empty($venda['origem']) && ! empty($venda['destino'])) {
            try {
                $tp = app(\App\Services\Vagas::class)->tiposDisponiveis($venda['origem'], $venda['destino'], $this->parseData($venda['data'] ?? ''), $this->lanchaDaConversa($c));
                if (($tp['ok'] ?? false) && ($tp['usa_poltrona'] ?? false) === true && is_array($tp['tipos'] ?? null)) {
                    $livres = [];
                    foreach (['janela', 'corredor', 'meio'] as $k) if ((int) ($tp['tipos'][$k] ?? 0) > 0) $livres[] = $k;
                    if ($livres) {
                        $msg = 'Antes de gerar o PIX, escolhe a poltrona: temos '.implode(', ', $livres).'. Qual você prefere?';
                        return ['texto' => trim(str_replace($marcador, '', $texto))."\n".$msg, 'pix' => null];
                    }
                }
            } catch (\Throwable $e) {}
        }

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
            $motivo = trim((string) ($r['erro'] ?? '')) ?: 'erro desconhecido';
            Log::warning('Agente PIX falhou: '.$motivo);
            if ($a->regra('autonomo')) { // autônomo: não transfere — pede pra tentar de novo
                try { $cx = $c->contexto ?? []; $cx['pix_erro'] = ['motivo' => mb_substr($motivo, 0, 300), 'em' => now()->format('d/m H:i')]; $c->forceFill(['contexto' => $cx])->save(); } catch (\Throwable $e) {}
                return ['texto' => trim(str_replace($marcador, "\nDeu um probleminha pra gerar o PIX agora. Me chama de novo daqui a uns minutinhos que eu gero pra você, tá?", $texto)), 'pix' => null];
            }
            if (! $demo) { try { $this->atendimento->transferir($c, 'Falha ao gerar PIX: '.$motivo); } catch (\Throwable $e) {} }
            return ['texto' => trim(str_replace($marcador, "\nSó um instante que um atendente vai finalizar seu PIX aqui, tá?", $texto)), 'pix' => null];
        }

        if (! empty($c->contexto['pix_erro'])) { $cx = $c->contexto; unset($cx['pix_erro']); $c->forceFill(['contexto' => $cx])->save(); } // PIX saiu: some o aviso de falha
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
