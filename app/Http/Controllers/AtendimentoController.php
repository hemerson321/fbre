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
        ]);
    }

    public function responder(Request $request, Conversa $conversa, Atendimento $at)
    {
        abort_unless($conversa->empresa_id === Tenant::id(), 404);
        $data = $request->validate(['texto' => 'required|string|max:4000']);
        if ($conversa->status !== 'humano') {
            $at->assumirHumano($conversa, auth()->id());
        }
        $r = $at->registrarSaida($conversa, $data['texto'], 'humano', auth()->id());
        return back()->with($r['ok'] ? 'ok' : 'erro', $r['ok'] ? null : ('Não enviou: '.($r['erro'] ?? 'erro')));
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
        $ctx = $conversa->contexto ?? [];
        $ctx['reinicio_id'] = $ultimo + 1; // agente ignora tudo que veio ate aqui
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
        if (! Schema::hasColumn('conversas', 'precisa_humano')) return response()->json(['precisa' => 0, 'itens' => []]);
        $itens = Conversa::where('precisa_humano', true)->where('status', '!=', 'fechada')
            ->orderByDesc('transferida_em')->limit(50)->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'nome' => $c->cliente_nome ?: $c->cliente_telefone,
                'telefone' => $c->cliente_telefone,
                'motivo' => $c->transferida_motivo,
            ]);
        return response()->json(['precisa' => $itens->count(), 'itens' => $itens]);
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
            ]);
        if ($msgs->isNotEmpty()) {
            $conversa->update(['nao_lidas' => 0]);
        }
        return response()->json(['mensagens' => $msgs, 'status' => $conversa->status]);
    }
}
