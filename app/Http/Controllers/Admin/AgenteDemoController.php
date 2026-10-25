<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agente;
use App\Models\Cobranca;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Services\AgenteBot;
use App\Services\Atendimento;
use App\Services\ConfirmaPagamento;
use App\Support\Tenant;
use Illuminate\Http\Request;

/**
 * Demo / chat de teste do agente: simula uma conversa de cliente SEM usar o WhatsApp real
 * e SEM gerar cobrança no Mercado Pago. Serve para testar qualquer agente criado.
 */
class AgenteDemoController extends Controller
{
    /** Conversa fixa de demonstração de cada agente (telefone fake, sem conexão de WhatsApp). */
    protected function demo(Agente $agente): Conversa
    {
        $conv = Conversa::firstOrNew([
            'empresa_id' => $agente->empresa_id,
            'modulo' => $agente->modulo,
            'cliente_telefone' => 'demo-'.$agente->id,
        ]);
        if (! $conv->exists) {
            $conv->cliente_nome = 'Cliente Demo';
            $conv->status = 'ia';
            $conv->funil = 'novo';
            $conv->atendida = true;
            $conv->ia_ativa = true;
            $conv->whatsapp_conexao_id = null;
            $conv->contexto = ['demo' => true, 'agente_id' => $agente->id];
            $conv->save();
        } elseif (($conv->contexto['agente_id'] ?? null) !== $agente->id) {
            $ctx = $conv->contexto ?? [];
            $ctx['agente_id'] = $agente->id;
            $ctx['demo'] = true;
            $conv->forceFill(['contexto' => $ctx])->save();
        }
        return $conv;
    }

    protected function serializar(Conversa $conv, int $desde = 0): array
    {
        return $conv->mensagens()->reorder()->where('id', '>', $desde)->orderBy('id')->get()
            ->map(fn (Mensagem $m) => [
                'id' => $m->id,
                'mine' => $m->direcao === 'entrada',
                'origem' => $m->origem,
                'texto' => (string) $m->texto,
                'pix' => $m->origem === 'ia' && preg_match('/^(DEMO-PIX-|000201)/', trim((string) $m->texto)) === 1,
                'hora' => optional($m->created_at)->format('H:i'),
            ])->values()->all();
    }

    public function chat(Agente $agente)
    {
        abort_unless($agente->empresa_id === Tenant::id(), 404);
        $conv = $this->demo($agente);
        return view('admin.agente_demo', [
            'agente' => $agente->load('llm', 'conexao', 'pagamentoApi'),
            'conversa' => $conv,
            'mensagens' => $this->serializar($conv),
        ]);
    }

    public function mensagens(Agente $agente, Request $request)
    {
        abort_unless($agente->empresa_id === Tenant::id(), 404);
        $conv = $this->demo($agente);
        return response()->json([
            'ok' => true,
            'funil' => $conv->fresh()->funil,
            'mensagens' => $this->serializar($conv, (int) $request->input('desde', 0)),
        ]);
    }

    public function enviar(Request $request, Agente $agente, AgenteBot $bot)
    {
        abort_unless($agente->empresa_id === Tenant::id(), 404);
        $texto = trim((string) $request->input('texto'));
        if ($texto === '') return response()->json(['ok' => false, 'erro' => 'vazio']);

        $conv = $this->demo($agente);
        $desde = (int) $conv->mensagens()->max('id');

        Mensagem::create([
            'empresa_id' => $conv->empresa_id,
            'conversa_id' => $conv->id,
            'direcao' => 'entrada',
            'origem' => 'cliente',
            'texto' => $texto,
            'lida' => true,
        ]);
        $conv->forceFill(['cliente_respondeu_em' => now(), 'ultima_msg_texto' => mb_substr($texto, 0, 255), 'ultima_msg_origem' => 'cliente', 'ultima_msg_em' => now()])->save();

        $bot->processar($conv, $agente, true);

        return response()->json([
            'ok' => true,
            'funil' => $conv->fresh()->funil,
            'mensagens' => $this->serializar($conv, $desde),
        ]);
    }

    public function foto(Request $request, Agente $agente, AgenteBot $bot)
    {
        abort_unless($agente->empresa_id === Tenant::id(), 404);
        $file = $request->file('foto');
        if (! $file || ! $file->isValid()) return response()->json(['ok' => false, 'erro' => 'arquivo']);

        $conv = $this->demo($agente);
        $desde = (int) $conv->mensagens()->max('id');

        $mime = $file->getMimeType() ?: 'image/jpeg';
        $base64 = base64_encode(file_get_contents($file->getRealPath()));

        Mensagem::create([
            'empresa_id' => $conv->empresa_id,
            'conversa_id' => $conv->id,
            'direcao' => 'entrada',
            'origem' => 'cliente',
            'texto' => '📷 (foto do documento enviada)',
            'lida' => true,
        ]);

        $bot->processarFoto($conv, $base64, $mime, $agente, true);

        return response()->json([
            'ok' => true,
            'funil' => $conv->fresh()->funil,
            'mensagens' => $this->serializar($conv, $desde),
        ]);
    }

    /** Simula "o PIX caiu": marca a última cobrança demo pendente como paga e dispara o fluxo. */
    public function pagar(Agente $agente, ConfirmaPagamento $confirma)
    {
        abort_unless($agente->empresa_id === Tenant::id(), 404);
        $conv = $this->demo($agente);
        $desde = (int) $conv->mensagens()->max('id');

        $cob = Cobranca::where('referencia_tipo', Conversa::class)
            ->where('referencia_id', $conv->id)
            ->where('status', 'pendente')
            ->latest('id')->first();

        if (! $cob) {
            return response()->json(['ok' => false, 'erro' => 'Nenhum PIX pendente nesta conversa. Peça uma passagem até gerar o PIX.']);
        }

        $confirma->aplicar($cob);

        return response()->json([
            'ok' => true,
            'funil' => $conv->fresh()->funil,
            'mensagens' => $this->serializar($conv, $desde),
        ]);
    }

    /** Zera a conversa de demonstração (mensagens + cobranças demo). */
    public function reiniciar(Agente $agente)
    {
        abort_unless($agente->empresa_id === Tenant::id(), 404);
        $conv = $this->demo($agente);
        $conv->mensagens()->delete();
        Cobranca::where('referencia_tipo', Conversa::class)->where('referencia_id', $conv->id)
            ->where('provider', 'demo')->delete();
        $conv->forceFill([
            'funil' => 'novo',
            'status' => 'ia',
            'contexto' => ['demo' => true, 'agente_id' => $agente->id],
            'ultima_msg_texto' => null, 'ultima_msg_origem' => null,
        ])->save();
        return response()->json(['ok' => true]);
    }
}
