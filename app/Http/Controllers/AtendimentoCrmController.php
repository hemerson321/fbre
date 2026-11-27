<?php

namespace App\Http\Controllers;

use App\Console\Commands\EnviarAgendadas;
use App\Models\AtendAgendada;
use App\Models\AtendEtiqueta;
use App\Models\AtendResposta;
use App\Models\Conversa;
use App\Models\WhatsappConexao;
use App\Services\Atendimento;
use App\Services\WhatsApp;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Atendimento — modo operador: CRM Kanban, etiquetas, respostas rápidas / funil, agendamento, envio em massa e status. */
class AtendimentoCrmController extends Controller
{
    protected function dono(Conversa $c): void { abort_unless($c->empresa_id === Tenant::id(), 404); }
    protected function soAdmin(): void { abort_unless(auth()->user()->isAdmin() || auth()->user()->isSuper(), 403, 'Só o administrador pode usar envio em massa e status.'); }

    protected function guardarArquivo(Request $request, string $campo = 'arquivo'): ?array
    {
        if (! $request->hasFile($campo)) return null;
        $f = $request->file($campo);
        return ['arquivo' => 'storage/'.$f->store('atendimento/'.Tenant::id(), 'public'), 'mime' => (string) ($f->getMimeType() ?: 'application/octet-stream'), 'nome' => mb_substr($f->getClientOriginalName(), 0, 150)];
    }

    // ---------------- Kanban ----------------
    public function kanban(Request $request)
    {
        $etq = $request->integer('etiqueta') ?: null;
        $conversas = \App\Models\AtendTela::filtrar(Conversa::with('atendente'), \App\Models\AtendTela::atualId(auth()->user()), \App\Support\Tenant::id())
            ->where(fn ($q) => $q->where('status', '!=', 'fechada')->orWhere('updated_at', '>=', now()->subDays(7)))
            ->orderByRaw('COALESCE(ultima_msg_em, created_at) desc')->limit(400)->get()
            ->filter(fn ($c) => ! $etq || in_array($etq, array_map('intval', (array) $c->etiquetas), true));
        return view('atendimento.kanban', [
            'colunas' => Conversa::FUNIL,
            'porFunil' => $conversas->groupBy(fn ($c) => $c->funil ?: 'novo'),
            'etiquetas' => AtendEtiqueta::orderBy('nome')->get()->keyBy('id'),
            'etq' => $etq,
        ]);
    }

    public function moverFunil(Request $request, Conversa $conversa)
    {
        $this->dono($conversa);
        $data = $request->validate(['funil' => 'required|in:'.implode(',', array_keys(Conversa::FUNIL))]);
        $conversa->update(['funil' => $data['funil']]);
        return $request->wantsJson() ? response()->json(['ok' => true]) : back();
    }

    public function etiquetaToggle(Request $request, Conversa $conversa)
    {
        $this->dono($conversa);
        $id = (int) $request->input('etiqueta_id');
        abort_unless(AtendEtiqueta::whereKey($id)->exists(), 404);
        $atuais = array_map('intval', (array) $conversa->etiquetas);
        $atuais = in_array($id, $atuais, true) ? array_values(array_diff($atuais, [$id])) : array_merge($atuais, [$id]);
        $conversa->forceFill(['etiquetas' => array_values(array_unique($atuais))])->save();
        return $request->wantsJson() ? response()->json(['ok' => true, 'etiquetas' => $conversa->etiquetas]) : back();
    }

    // ---------------- Página de ferramentas ----------------
    public function ferramentas()
    {
        return view('atendimento.ferramentas', [
            'respostas' => AtendResposta::orderBy('ordem')->orderBy('titulo')->get(),
            'etiquetas' => AtendEtiqueta::orderBy('nome')->get(),
            'agendadas' => AtendAgendada::with('conversa')->orderByDesc('enviar_em')->limit(150)->get(),
            'pendentes' => AtendAgendada::where('status', 'pendente')->count(),
            'conexoes' => WhatsappConexao::where('ativo', true)->orderBy('nome')->get(),
            'funis' => Conversa::FUNIL,
            'adm' => auth()->user()->isAdmin() || auth()->user()->isSuper(),
        ]);
    }

    // ---------------- Respostas rápidas / funil de mensagens ----------------
    public function respostaSalvar(Request $request, ?AtendResposta $resposta = null)
    {
        $data = $request->validate([
            'titulo' => 'required|string|max:120',
            'categoria' => 'nullable|string|max:60',
            'passos' => 'required|array|min:1|max:12',
            'passos.*.texto' => 'nullable|string|max:4000',
            'passos.*.espera' => 'nullable|integer|min:0|max:3600',
            'passos.*.arquivo_atual' => 'nullable|string|max:255',
            'passos.*.mime_atual' => 'nullable|string|max:80',
            'passos.*.nome_atual' => 'nullable|string|max:160',
            'passos.*.arquivo' => 'nullable|file|max:51200',
        ]);
        $passos = [];
        foreach ($data['passos'] as $i => $p) {
            $arq = null;
            if ($request->hasFile("passos.$i.arquivo")) {
                $f = $request->file("passos.$i.arquivo");
                $arq = ['arquivo' => 'storage/'.$f->store('atendimento/'.Tenant::id(), 'public'), 'mime' => (string) ($f->getMimeType() ?: 'application/octet-stream'), 'nome' => mb_substr($f->getClientOriginalName(), 0, 150)];
            } elseif (! empty($p['arquivo_atual']) && str_starts_with($p['arquivo_atual'], 'storage/atendimento/'.Tenant::id().'/')) {
                $arq = ['arquivo' => $p['arquivo_atual'], 'mime' => (string) ($p['mime_atual'] ?? ''), 'nome' => (string) ($p['nome_atual'] ?? '')];
            }
            $texto = trim((string) ($p['texto'] ?? ''));
            if ($texto === '' && ! $arq) continue;
            $passos[] = ['texto' => $texto, 'espera' => (int) ($p['espera'] ?? 0)] + ($arq ?: ['arquivo' => null, 'mime' => null, 'nome' => null]);
        }
        if (! $passos) return back()->with('erro', 'Escreva pelo menos uma mensagem.')->withInput();
        $campos = ['titulo' => $data['titulo'], 'categoria' => trim((string) ($data['categoria'] ?? '')) ?: null, 'passos' => $passos, 'ativo' => true];
        if ($resposta && $resposta->exists) $resposta->update($campos);
        else AtendResposta::create($campos + ['empresa_id' => Tenant::id(), 'ordem' => (int) AtendResposta::max('ordem') + 1]);
        return back()->with('ok', count($passos) > 1 ? 'Funil de mensagens salvo ('.count($passos).' passos).' : 'Resposta rápida salva.');
    }

    public function respostaExcluir(AtendResposta $resposta)
    {
        $resposta->delete();
        return back()->with('ok', 'Excluído.');
    }

    /** Dispara uma resposta rápida / funil numa conversa: 1º passo sai na hora, os outros são agendados com a espera de cada um. */
    public function respostaUsar(Request $request, Conversa $conversa, AtendResposta $resposta, Atendimento $at, WhatsApp $wa)
    {
        $this->dono($conversa);
        if ($conversa->status !== 'humano') $at->assumirHumano($conversa, auth()->id());
        $lote = (string) Str::uuid();
        $quando = now();
        $erros = 0;
        $passos = array_values((array) $resposta->passos);
        foreach ($passos as $i => $p) {
            $quando = $quando->copy()->addSeconds($i === 0 ? 0 : max(2, (int) ($p['espera'] ?? 0)));
            $a = new AtendAgendada([
                'empresa_id' => Tenant::id(), 'conversa_id' => $conversa->id, 'tipo' => 'mensagem', 'texto' => (string) ($p['texto'] ?? ''),
                'arquivo' => $p['arquivo'] ?? null, 'mime' => $p['mime'] ?? null, 'nome_arquivo' => $p['nome'] ?? null,
                'enviar_em' => $quando, 'status' => 'pendente', 'lote' => $lote, 'lote_nome' => 'Funil: '.$resposta->titulo, 'user_id' => auth()->id(),
            ]);
            if ($i === 0) { // 1º passo vai direto, sem passar pela fila
                $r = EnviarAgendadas::enviar($a, $at, $wa);
                if (! ($r['ok'] ?? false)) $erros++;
                continue;
            }
            $a->save();
        }
        $n = count($passos);
        if ($request->header('X-Requested-With') === 'fetch' || $request->wantsJson()) {
            return response()->json(['ok' => $erros === 0, 'erro' => $erros ? 'A primeira mensagem nao foi enviada. Confira a conexao do WhatsApp.' : null, 'n' => $n]);
        }
        if ($erros) return back()->with('erro', 'A primeira mensagem não foi enviada. Confira a conexão do WhatsApp.');
        return $n > 1 ? back()->with('ok', 'Funil iniciado: '.$n.' mensagens (as próximas saem sozinhas).') : back();
    }

    // ---------------- Importar respostas rápidas de um backup (.json) ----------------
    /**
     * Converte o backup da extensão de atendimento (chave "respostasRapidasAcao") em respostas do sistema.
     * Cada item vira uma resposta; item com várias ações vira funil. Devolve [['titulo','passos'=>[['texto','espera','img'=>dataURI|null]]]].
     * Os NOMES/atalhos originais ficam criptografados no backup — o título é montado com o começo da mensagem.
     */
    public static function converterBackup(array $d): array
    {
        $out = []; $usados = []; $semTexto = 0;
        foreach ((array) ($d['respostasRapidasAcao'] ?? []) as $item) {
            $passos = [];
            foreach ((array) ($item['acao'] ?? []) as $a) {
                $pr = $a['propriedades'] ?? [];
                if (is_string($pr)) { $j = json_decode($pr, true); $pr = is_array($j) ? $j : []; }
                $espera = max(0, min(3600, (int) ($pr['aguarde'] ?? 0)));
                $tipo = (string) ($a['type'] ?? '');
                if ($tipo === 'txt') {
                    $t = trim((string) ($pr['mensagem'] ?? ''));
                    if ($t !== '') $passos[] = ['texto' => mb_substr($t, 0, 4000), 'espera' => $espera, 'img' => null];
                } elseif ($tipo === 'image') {
                    $b = (string) ($pr['base64'] ?? '');
                    if (str_starts_with($b, 'data:image/')) $passos[] = ['texto' => mb_substr(trim((string) ($pr['mensagem'] ?? '')), 0, 4000), 'espera' => $espera, 'img' => $b];
                } elseif ($tipo === 'pix') {
                    $k = trim((string) ($pr['key'] ?? ''));
                    if ($k !== '') $passos[] = ['texto' => "*PIX*\nChave: ".$k.(! empty($pr['name']) ? "\nFavorecido: ".trim((string) $pr['name']) : ''), 'espera' => $espera, 'img' => null];
                } elseif ($tipo === 'linkPreview') {
                    $t = trim(implode("\n", array_filter([trim((string) ($pr['title'] ?? '')), trim((string) ($pr['description'] ?? '')), trim((string) ($pr['link'] ?? ''))])));
                    if ($t !== '') $passos[] = ['texto' => $t, 'espera' => $espera, 'img' => null];
                }
                // demais tipos (ex.: mover no CRM da extensão) não têm equivalente aqui
            }
            $passos = array_slice($passos, 0, 12);
            if (! $passos) continue;

            // título: 1ª linha com letras/números da 1ª mensagem, sem os * _ ~ do WhatsApp
            // (se a 1ª linha for curta/genérica — "SAIDA DAS LANCHAS" — junta a 2ª pra dar pra diferenciar: "... · MANAUS X TEFÉ")
            $titulo = ''; $pegas = 0;
            foreach ($passos as $p) {
                foreach (preg_split('/\R/u', (string) $p['texto']) as $ln) {
                    $ln = trim(preg_replace('/\s+/u', ' ', preg_replace('/[*_~`]+/u', '', $ln)));
                    if (! preg_match('/[\p{L}\p{N}]/u', $ln)) continue;
                    $titulo = $titulo === '' ? $ln : $titulo.' · '.$ln;
                    if (++$pegas >= 2 || mb_strlen($titulo) >= 30) break 2;
                }
            }
            if ($titulo === '') $titulo = 'Imagem '.(++$semTexto);
            $titulo = mb_substr($titulo, 0, 70);
            $base = $titulo; $n = 1;
            while (isset($usados[mb_strtolower($titulo)])) $titulo = mb_substr($base, 0, 64).' ('.(++$n).')';
            $usados[mb_strtolower($titulo)] = true;

            $todoTexto = implode("\n", array_column($passos, 'texto'));
            $out[] = ['titulo' => $titulo, 'categoria' => self::categoriaAuto($todoTexto), 'passos' => $passos];
        }
        // mesma cidade escrita com e sem acento ("Tefe"/"Tefé") vira UMA categoria (fica a grafia com acento)
        $melhor = [];
        foreach ($out as $it) {
            $k = mb_strtolower(\Illuminate\Support\Str::ascii($it['categoria']));
            if (! isset($melhor[$k]) || strlen($it['categoria']) > strlen($melhor[$k])) $melhor[$k] = $it['categoria'];
        }
        foreach ($out as $i => $it) $out[$i]['categoria'] = $melhor[mb_strtolower(\Illuminate\Support\Str::ascii($it['categoria']))];
        return $out;
    }

    /**
     * Categoria sugerida pelo ASSUNTO da mensagem (as categorias originais vêm criptografadas no backup).
     * Regras simples por palavra-chave; o operador pode trocar depois.
     */
    public static function categoriaAuto(string $texto): string
    {
        $t = mb_strtolower(\Illuminate\Support\Str::ascii($texto));
        if (trim($t) === '') return 'Imagens';
        $tem = fn (array $ps) => (bool) array_filter($ps, fn ($p) => str_contains($t, $p));
        if ($tem(['compra confirmada', 'compra foi concluida', 'compra concluida', 'pagamento confirmado', 'passagem emitida'])) return 'Compra confirmada';
        if ($tem(['festival', 'bumbodromo', 'caprichoso', 'garantido'])) return 'Festival de Parintins';
        if ($tem(['nome completo', 'rg ou cpf', 'd.nasc', 'dados do passageiro', 'data de nascimento'])) return 'Dados do passageiro';
        if ($tem(['pix', 'forma de pagamento', 'cartao', 'chave:', 'transferencia', 'comprovante de pagamento'])) return 'Pagamento';
        if ($tem(['urna', 'esquife', 'pets', ' pet ', 'animal', 'encomenda', 'bagagem', 'carga', 'volume'])) return 'Encomendas, bagagem e pets';
        if ($tem(['saida das lanchas', 'saidas das lanchas', 'primeiro horario', 'horarios de saida'])) {
            // separa por trecho: "MANAUS X TEFÉ" -> "Saídas · Manaus x Tefé"
            if (preg_match('/([\p{Lu}][\p{Lu}. ]{2,24}?)\s+X\s+([\p{Lu}][\p{Lu}. ]{2,24})/u', preg_replace('/[*_~]+/u', '', $texto), $m)) {
                // agrupa pela cidade de ORIGEM (o destino já aparece no nome da resposta)
                $o = mb_convert_case(mb_strtolower(trim(preg_replace('/\s+/u', ' ', str_replace('.', '', $m[1])))), MB_CASE_TITLE, 'UTF-8');
                $k = mb_strtolower(\Illuminate\Support\Str::ascii($o));
                if (str_starts_with($k, 'benj')) $o = 'Benjamin Constant';
                elseif (str_starts_with($k, 'santo ant') || str_starts_with($k, 'sto ant')) $o = 'Santo Antônio do Içá';
                elseif (str_starts_with($k, 'sao paulo') || str_starts_with($k, 's paulo')) $o = 'São Paulo de Olivença';
                return mb_substr('Saídas · de '.$o, 0, 60);
            }
            return 'Saídas das lanchas';
        }
        if (preg_match('/manaus\s*(x|➝|-|→|>|para)\s*\w+/u', $t) || $tem(['r$', ',00', 'valor da passagem', 'tarifa'])) return 'Rotas e preços';
        if ($tem(['horario de atendimento', 'estamos fechados', 'seg a sex', 'segunda a sexta', 'feriado'])) return 'Horário de atendimento';
        if ($tem(['avenida', 'endereco', 'localizacao', 'em frente', 'porto do ajato', 'manaus moderna', 'maps'])) return 'Endereço';
        if ($tem(['atencao', 'importante', 'informamos', 'aviso', 'comunicado'])) return 'Avisos';
        if (mb_strlen($t) <= 120 && $tem(['ola', 'bom dia', 'boa tarde', 'boa noite', 'obrigad', 'como podemos ajudar', 'tudo bem', 'aguarde', 'um momento', 'qual ', 'para qual'])) return 'Saudações e perguntas';
        return 'Geral';
    }

    public function respostaImportar(Request $request)
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isSuper(), 403);
        $request->validate(['backup' => 'required|file|max:61440']);
        $d = json_decode((string) file_get_contents($request->file('backup')->getRealPath()), true);
        if (! is_array($d) || empty($d['respostasRapidasAcao'])) {
            return back()->with('erro', 'Não reconheci o arquivo. Envie o backup .json da extensão de atendimento (precisa ter as respostas rápidas).');
        }
        $itens = self::converterBackup($d);
        if (! $itens) return back()->with('erro', 'O backup não tem nenhuma resposta rápida que eu consiga importar.');

        // não duplica: pula o que já existe com o mesmo conteúdo (texto de cada passo)
        $assinatura = fn (array $passos) => md5(json_encode(array_map(fn ($p) => trim((string) ($p['texto'] ?? '')).'|'.(! empty($p['img'] ?? ($p['arquivo'] ?? null)) ? 'A' : ''), $passos)));
        $existentes = [];
        foreach (AtendResposta::get(['passos']) as $r) $existentes[$assinatura((array) $r->passos)] = true;

        $ordem = (int) AtendResposta::max('ordem');
        $novas = 0; $puladas = 0; $imgs = 0;
        foreach ($itens as $it) {
            $sig = $assinatura($it['passos']);
            if (isset($existentes[$sig])) { $puladas++; continue; }
            $existentes[$sig] = true;
            $passos = [];
            foreach ($it['passos'] as $p) {
                $arq = ['arquivo' => null, 'mime' => null, 'nome' => null];
                if (! empty($p['img']) && preg_match('#^data:(image/(jpeg|png|gif|webp));base64,(.+)$#s', $p['img'], $m)) {
                    $bin = base64_decode($m[3], true);
                    if ($bin !== false && strlen($bin) > 0 && strlen($bin) <= 16 * 1024 * 1024) {
                        $ext = $m[2] === 'jpeg' ? 'jpg' : $m[2];
                        $rel = 'atendimento/'.Tenant::id().'/imp-'.Str::uuid().'.'.$ext;
                        \Illuminate\Support\Facades\Storage::disk('public')->put($rel, $bin);
                        $arq = ['arquivo' => 'storage/'.$rel, 'mime' => $m[1], 'nome' => 'imagem.'.$ext];
                        $imgs++;
                    }
                }
                if (trim((string) $p['texto']) === '' && ! $arq['arquivo']) continue;
                $passos[] = ['texto' => (string) $p['texto'], 'espera' => (int) $p['espera']] + $arq;
            }
            if (! $passos) continue;
            AtendResposta::create(['empresa_id' => Tenant::id(), 'titulo' => $it['titulo'], 'categoria' => $it['categoria'] ?? 'Geral', 'passos' => $passos, 'ordem' => ++$ordem, 'ativo' => true]);
            $novas++;
        }
        return back()->with('ok', $novas.' resposta(s) importada(s)'.($imgs ? ' ('.$imgs.' com imagem)' : '').($puladas ? ' · '.$puladas.' já existiam e foram puladas' : '').'. Elas já entraram separadas por categoria (pelo assunto da mensagem) — o nome de cada uma é o começo da mensagem; renomeie ou troque de categoria as que quiser.');
    }

    // ---------------- Etiquetas ----------------
    public function etiquetaSalvar(Request $request, ?AtendEtiqueta $etiqueta = null)
    {
        $data = $request->validate(['nome' => 'required|string|max:60', 'cor' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        if ($etiqueta && $etiqueta->exists) $etiqueta->update($data);
        else AtendEtiqueta::create($data + ['empresa_id' => Tenant::id()]);
        return back()->with('ok', 'Etiqueta salva.');
    }

    public function etiquetaExcluir(AtendEtiqueta $etiqueta)
    {
        $id = (int) $etiqueta->id;
        foreach (Conversa::whereNotNull('etiquetas')->get() as $c) {
            $l = array_map('intval', (array) $c->etiquetas);
            if (in_array($id, $l, true)) $c->forceFill(['etiquetas' => array_values(array_diff($l, [$id]))])->save();
        }
        $etiqueta->delete();
        return back()->with('ok', 'Etiqueta excluída.');
    }

    // ---------------- Agendar mensagem numa conversa ----------------
    public function agendar(Request $request, Conversa $conversa)
    {
        $this->dono($conversa);
        $data = $request->validate([
            'texto' => 'nullable|string|max:4000',
            'enviar_em' => 'required|date',
            'arquivo' => 'nullable|file|max:51200',
        ]);
        $arq = $this->guardarArquivo($request);
        if (trim((string) ($data['texto'] ?? '')) === '' && ! $arq) return back()->with('erro', 'Escreva a mensagem ou anexe um arquivo.');
        $quando = Carbon::parse($data['enviar_em']);
        if ($quando->lt(now()->subMinute())) return back()->with('erro', 'Escolha uma data e hora no futuro.');
        AtendAgendada::create([
            'empresa_id' => Tenant::id(), 'conversa_id' => $conversa->id, 'tipo' => 'mensagem', 'texto' => trim((string) ($data['texto'] ?? '')),
            'arquivo' => $arq['arquivo'] ?? null, 'mime' => $arq['mime'] ?? null, 'nome_arquivo' => $arq['nome'] ?? null,
            'enviar_em' => $quando, 'status' => 'pendente', 'user_id' => auth()->id(),
        ]);
        return back()->with('ok', 'Mensagem agendada para '.$quando->format('d/m/Y H:i').'.');
    }

    public function agendadaCancelar(AtendAgendada $agendada)
    {
        if ($agendada->status === 'pendente') $agendada->update(['status' => 'cancelada']);
        return back()->with('ok', 'Agendamento cancelado.');
    }

    public function loteCancelar(Request $request)
    {
        $lote = (string) $request->input('lote');
        abort_if($lote === '', 404);
        $n = AtendAgendada::where('lote', $lote)->where('status', 'pendente')->update(['status' => 'cancelada']);
        return back()->with('ok', $n.' envio(s) pendente(s) cancelado(s).');
    }

    // ---------------- Envio em massa ----------------
    public function massa(Request $request)
    {
        $this->soAdmin();
        $data = $request->validate([
            'nome' => 'nullable|string|max:120',
            'texto' => 'nullable|string|max:4000',
            'arquivo' => 'nullable|file|max:51200',
            'publico' => 'required|in:etiqueta,funil,todos,numeros',
            'etiqueta_id' => 'nullable|integer',
            'funil' => 'nullable|string|max:30',
            'numeros' => 'nullable|string|max:20000',
            'enviar_em' => 'nullable|date',
            'intervalo' => 'required|integer|min:20|max:600',
            'whatsapp_conexao_id' => 'nullable|integer',
        ]);
        $arq = $this->guardarArquivo($request);
        if (trim((string) ($data['texto'] ?? '')) === '' && ! $arq) return back()->with('erro', 'Escreva a mensagem ou anexe um arquivo.')->withInput();

        $alvos = collect(); // [conversa_id|null, telefone, nome]
        if ($data['publico'] === 'numeros') {
            foreach (preg_split('/[\s,;]+/', (string) ($data['numeros'] ?? '')) as $n) {
                $d = preg_replace('/\D+/', '', (string) $n);
                if (strlen($d) >= 10) $alvos->push([null, $d, '']);
            }
        } else {
            $q = Conversa::query();
            if ($data['publico'] === 'funil') $q->where('funil', (string) ($data['funil'] ?? ''));
            foreach ($q->get(['id', 'cliente_telefone', 'cliente_nome', 'etiquetas']) as $c) {
                if ($data['publico'] === 'etiqueta' && ! in_array((int) ($data['etiqueta_id'] ?? 0), array_map('intval', (array) $c->etiquetas), true)) continue;
                $alvos->push([$c->id, (string) $c->cliente_telefone, (string) $c->cliente_nome]);
            }
        }
        $alvos = $alvos->unique(fn ($a) => $a[1])->values();
        if ($alvos->isEmpty()) return back()->with('erro', 'Nenhum contato encontrado para esse público.')->withInput();
        if ($alvos->count() > 500) return back()->with('erro', 'Máximo de 500 contatos por envio (para proteger o número). Divida em partes.')->withInput();

        $lote = (string) Str::uuid();
        $quando = ! empty($data['enviar_em']) ? Carbon::parse($data['enviar_em']) : now();
        if ($quando->lt(now())) $quando = now();
        $texto = trim((string) ($data['texto'] ?? ''));
        foreach ($alvos as $i => $a) {
            $primeiro = trim((string) explode(' ', trim($a[2]))[0]);
            AtendAgendada::create([
                'empresa_id' => Tenant::id(), 'conversa_id' => $a[0], 'telefone' => $a[1], 'tipo' => 'mensagem',
                'whatsapp_conexao_id' => $data['whatsapp_conexao_id'] ?? null,
                'texto' => str_replace(['{nome}', '{NOME}'], $primeiro, $texto),
                'arquivo' => $arq['arquivo'] ?? null, 'mime' => $arq['mime'] ?? null, 'nome_arquivo' => $arq['nome'] ?? null,
                // intervalo + variação aleatória: disparo "humano", menos risco de bloqueio
                'enviar_em' => $quando->copy()->addSeconds($i * (int) $data['intervalo'] + ($i ? random_int(0, 15) : 0)),
                'status' => 'pendente', 'lote' => $lote, 'lote_nome' => 'Massa: '.(trim((string) ($data['nome'] ?? '')) ?: now()->format('d/m H:i')), 'user_id' => auth()->id(),
            ]);
        }
        $fim = $quando->copy()->addSeconds($alvos->count() * (int) $data['intervalo']);
        return back()->with('ok', 'Envio em massa programado: '.$alvos->count().' contato(s), de '.$quando->format('d/m H:i').' até cerca de '.$fim->format('d/m H:i').'.');
    }

    // ---------------- Status (story) ----------------
    public function status(Request $request)
    {
        $this->soAdmin();
        $data = $request->validate([
            'texto' => 'nullable|string|max:700',
            'arquivo' => 'nullable|file|max:51200|mimes:jpg,jpeg,png,webp,mp4',
            'enviar_em' => 'nullable|date',
            'whatsapp_conexao_id' => 'nullable|integer',
        ]);
        $arq = $this->guardarArquivo($request);
        if (trim((string) ($data['texto'] ?? '')) === '' && ! $arq) return back()->with('erro', 'Escreva o texto do status ou envie uma imagem/vídeo.')->withInput();
        $quando = ! empty($data['enviar_em']) ? Carbon::parse($data['enviar_em']) : now();
        $agora = $quando->lt(now());
        AtendAgendada::create([
            'empresa_id' => Tenant::id(), 'tipo' => 'status', 'whatsapp_conexao_id' => $data['whatsapp_conexao_id'] ?? null,
            'texto' => trim((string) ($data['texto'] ?? '')), 'arquivo' => $arq['arquivo'] ?? null, 'mime' => $arq['mime'] ?? null, 'nome_arquivo' => $arq['nome'] ?? null,
            'enviar_em' => $agora ? now() : $quando, 'status' => 'pendente', 'lote_nome' => 'Status', 'user_id' => auth()->id(),
        ]);
        return back()->with('ok', 'Status programado para '.($agora ? 'agora (sai em até 1 minuto)' : $quando->format('d/m/Y H:i')).'.');
    }
}
