<?php

namespace App\Http\Controllers;

use App\Models\Conversa;
use App\Services\Atendimento;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Caixa de atendimento do WhatsApp (humano). O agente IA entra na próxima etapa.
 */
class AtendimentoController extends Controller
{
    public function index(Request $request)
    {
        $filtro = $request->query('status', 'ativas');
        $q = Conversa::query()->with('atendente');
        $q = match ($filtro) {
            'aguardando' => $q->where('status', 'aguardando'),
            'humano'     => $q->where('status', 'humano'),
            'fechadas'   => $q->where('status', 'fechada'),
            default      => $q->where('status', '!=', 'fechada'),
        };
        $conversas = $q->orderByRaw('COALESCE(ultima_msg_em, created_at) desc')->limit(100)->get();

        return view('atendimento.index', [
            'conversas' => $conversas,
            'filtro' => $filtro,
            'aguardando' => Conversa::where('status', 'aguardando')->count(),
            'precisaHumano' => $this->precisaHumanoCount(),
            'conversa' => null,
            'vendaResumo' => null,
        ]);
    }

    public function show(Conversa $conversa)
    {
        abort_unless($conversa->empresa_id === Tenant::id(), 404);
        $conversa->update(['nao_lidas' => 0]);
        $conversa->load('mensagens.autor', 'atendente');

        $lista = Conversa::where('status', '!=', 'fechada')
            ->orderByRaw('COALESCE(ultima_msg_em, created_at) desc')->limit(100)->get();

        return view('atendimento.index', [
            'conversas' => $lista,
            'filtro' => 'ativas',
            'aguardando' => Conversa::where('status', 'aguardando')->count(),
            'precisaHumano' => $this->precisaHumanoCount(),
            'conversa' => $conversa,
            'vendaResumo' => $this->resumoVenda($conversa),
            'respostas' => \App\Models\AtendResposta::where('ativo', true)->orderBy('ordem')->orderBy('titulo')->get(),
            'etiquetasTodas' => \App\Models\AtendEtiqueta::orderBy('nome')->get(),
            'agendadasConv' => \App\Models\AtendAgendada::where('conversa_id', $conversa->id)->where('status', 'pendente')->orderBy('enviar_em')->get(),
        ]);
    }

    public function responder(Request $request, Conversa $conversa, Atendimento $at)
    {
        abort_unless($conversa->empresa_id === Tenant::id(), 404);
        $data = $request->validate(['texto' => 'nullable|string|max:4000', 'arquivo' => 'nullable|file|max:51200']);
        $texto = trim((string) ($data['texto'] ?? ''));
        $ajax = $request->header('X-Requested-With') === 'fetch' || $request->wantsJson();
        $resp = function (array $r) use ($ajax) {
            $ok = (bool) ($r['ok'] ?? false);
            if ($ajax) return response()->json(['ok' => $ok, 'erro' => $ok ? null : ($r['erro'] ?? 'erro')]);
            return back()->with($ok ? 'ok' : 'erro', $ok ? null : ('Não enviou: '.($r['erro'] ?? 'erro')));
        };
        if ($texto === '' && ! $request->hasFile('arquivo')) {
            return $ajax ? response()->json(['ok' => false, 'erro' => 'Escreva uma mensagem ou anexe um arquivo.'], 422) : back()->with('erro', 'Escreva uma mensagem ou anexe um arquivo.');
        }
        if ($conversa->status !== 'humano') {
            $at->assumirHumano($conversa, auth()->id());
        }
        if ($request->hasFile('arquivo')) {
            // foto, vídeo, áudio ou documento — vai com o texto como legenda
            $f = $request->file('arquivo');
            $caminho = 'storage/'.$f->store('atendimento/'.Tenant::id(), 'public');
            return $resp($at->registrarSaidaMidia($conversa, $caminho, (string) ($f->getMimeType() ?: 'application/octet-stream'), mb_substr($f->getClientOriginalName(), 0, 150), $texto, 'humano', auth()->id()));
        }
        return $resp($at->registrarSaida($conversa, $texto, 'humano', auth()->id()));
    }

    public function assumir(Conversa $conversa, Atendimento $at)
    {
        abort_unless($conversa->empresa_id === Tenant::id(), 404);
        $at->assumirHumano($conversa, auth()->id());
        return back()->with('ok', 'Você assumiu o atendimento.');
    }

    public function reiniciar(Conversa $conversa)
    {
        abort_unless($conversa->empresa_id === Tenant::id(), 404);
        $ultimo = (int) $conversa->mensagens()->max('id');
        // limpa TUDO que o agente guardou (venda, poltronas, passageiros, orçamento...) — só fica o que identifica a conversa de teste
        $ctx = array_intersect_key($conversa->contexto ?? [], ['demo' => 1, 'agente_id' => 1]);
        $ctx['reinicio_id'] = $ultimo + 1; // agente ignora tudo que veio ate aqui
        $ctx['limite_zerado_id'] = $ultimo + 1; // libera o limite de mensagens (proteção contra abuso) — só um operador faz isso
        $conversa->forceFill([
            'contexto' => $ctx,
            'funil' => 'novo',
            'status' => 'aguardando',
            'ia_ativa' => false,
            'precisa_humano' => false,
            'assumida_por' => null,
        ])->save();
        try { app(\App\Services\Vagas::class)->liberarConversa($conversa->id); } catch (\Throwable $e) {}
        return back()->with('ok', 'Sessão reiniciada — o agente vai atender como novo a partir da próxima mensagem.');
    }

    public function fechar(Conversa $conversa)
    {
        abort_unless($conversa->empresa_id === Tenant::id(), 404);
        $conversa->update(['status' => 'fechada', 'atendida' => true, 'nao_lidas' => 0, 'funil' => 'finalizado']);
        try { app(\App\Services\Vagas::class)->liberarConversa($conversa->id); } catch (\Throwable $e) {}
        return redirect()->route('atendimento.index')->with('ok', 'Pedido finalizado.');
    }

    /** Polling do painel: conversas que a IA transferiu e aguardam atendente (p/ bip + lista). */
    protected function precisaHumanoCount(): int
    {
        if (! Schema::hasColumn('conversas', 'precisa_humano')) return 0;
        return Conversa::where('precisa_humano', true)->where('status', '!=', 'fechada')->count();
    }

    public function alertas()
    {
        if (! Schema::hasColumn('conversas', 'precisa_humano')) return response()->json(['precisa' => 0, 'itens' => [], 'hash' => $this->listaHash()]);
        $itens = Conversa::where('precisa_humano', true)->where('status', '!=', 'fechada')
            ->orderByDesc('transferida_em')->limit(50)->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'nome' => $c->cliente_nome ?: $c->cliente_telefone,
                'telefone' => $c->cliente_telefone,
                'motivo' => $c->transferida_motivo,
            ]);
        return response()->json(['precisa' => $itens->count(), 'itens' => $itens, 'hash' => $this->listaHash()]);
    }

    /** Impressão digital barata da lista (muda quando chega msg, assume, lê, abre/fecha conversa). */
    protected function listaHash(): string
    {
        $a = Conversa::where('status', '!=', 'fechada')
            ->selectRaw("COUNT(*) c, COALESCE(MAX(ultima_msg_em), '') m, COALESCE(SUM(nao_lidas), 0) n, COALESCE(MAX(updated_at), '') u")
            ->first();
        return md5(($a->c ?? 0).'|'.($a->m ?? '').'|'.($a->n ?? 0).'|'.($a->u ?? ''));
    }

    /** Lista de conversas renderizada (p/ atualizar sozinho sem recarregar a página). */
    public function lista(Request $request)
    {
        $filtro = $request->query('status', 'ativas');
        $q = Conversa::query()->with('atendente');
        $q = match ($filtro) {
            'aguardando' => $q->where('status', 'aguardando'),
            'humano'     => $q->where('status', 'humano'),
            'fechadas'   => $q->where('status', 'fechada'),
            default      => $q->where('status', '!=', 'fechada'),
        };
        $conversas = $q->orderByRaw('COALESCE(ultima_msg_em, created_at) desc')->limit(100)->get();
        $selId = $request->integer('sel') ?: null;
        $sel = $selId ? $conversas->firstWhere('id', $selId) : null;

        return response()->json([
            'hash' => $this->listaHash(),
            'html' => view('atendimento._lista', ['conversas' => $conversas, 'sel' => $sel])->render(),
        ]);
    }

    /** Resumo da venda que o agente/operador montou nesta conversa (pro painel ao vivo). */
    public function resumoVenda(Conversa $conversa): array
    {
        $ctx = $conversa->contexto ?? [];
        $venda = $ctx['venda'] ?? [];
        $pax = $venda['passageiros'] ?? ($ctx['passageiros'] ?? []);
        return [
            'origem' => $venda['origem'] ?? null,
            'destino' => $venda['destino'] ?? null,
            'data' => $venda['data'] ?? null,
            'passageiros' => is_array($pax) ? (count($pax) ?: null) : null,
            'pref' => $ctx['pref_poltrona'] ?? null,
            'poltronas' => array_values((array) ($ctx['poltronas_agente'] ?? [])),
            'funil' => $conversa->funilLabel(),
            'cliente' => $conversa->nomeExibicao(),
        ];
    }

    /** Polling: mensagens novas desde um id (JSON). */
    public function mensagens(Request $request, Conversa $conversa)
    {
        abort_unless($conversa->empresa_id === Tenant::id(), 404);
        $desde = (int) $request->query('desde', 0);
        $msgs = $conversa->mensagens()->where('id', '>', $desde)->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'direcao' => $m->direcao,
                'origem' => $m->origem,
                'texto' => $m->texto,
                'hora' => $m->created_at?->format('H:i'),
                'autor' => $m->autor?->name,
                'arquivo' => ! empty($m->meta['arquivo']) ? asset($m->meta['arquivo']) : null,
                'mime' => $m->meta['mime'] ?? null,
                'nome' => $m->meta['nome'] ?? null,
            ]);
        if ($msgs->isNotEmpty()) {
            $conversa->update(['nao_lidas' => 0]);
        }
        return response()->json(['mensagens' => $msgs, 'status' => $conversa->status, 'venda' => $this->resumoVenda($conversa)]);
    }
}
