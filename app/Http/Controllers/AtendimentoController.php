<?php

namespace App\Http\Controllers;

use App\Models\Conversa;
use App\Services\Atendimento;
use App\Support\Tenant;
use Illuminate\Http\Request;

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

    public function fechar(Conversa $conversa)
    {
        abort_unless($conversa->empresa_id === Tenant::id(), 404);
        $conversa->update(['status' => 'fechada', 'atendida' => true, 'nao_lidas' => 0]);
        return redirect()->route('atendimento.index')->with('ok', 'Conversa fechada.');
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
