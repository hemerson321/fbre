<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agente;
use App\Models\LlmProvedor;
use App\Models\PagamentoApi;
use App\Models\WhatsappConexao;
use App\Services\AgenteBot;
use App\Support\Modulos;
use App\Support\Tenant;
use Illuminate\Http\Request;

class AgenteController extends Controller
{
    public function index()
    {
        return view('admin.agentes', [
            'agentes' => Agente::with('llm', 'conexao', 'pagamentoApi')->orderBy('nome')->get(),
            'llms' => LlmProvedor::where('ativo', true)->orderBy('nome')->get(),
            'conexoes' => WhatsappConexao::where('ativo', true)->orderBy('nome')->get(),
            'apis' => PagamentoApi::where('ativo', true)->orderBy('nome')->get(),
            'modulos' => Modulos::LISTA,
        ]);
    }

    public function salvar(Request $request, ?Agente $agente = null)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:80',
            'modulo' => 'required|string|max:20',
            'llm_provedor_id' => 'nullable|integer',
            'whatsapp_conexao_id' => 'nullable|integer',
            'pagamento_api_id' => 'nullable|integer',
            'ativo' => 'nullable|boolean',
            'persona' => 'nullable|string|max:4000',
            'assumir_modo' => 'required|in:sempre,apos_min,fora_horario',
            'assumir_min' => 'nullable|integer|min:0|max:120',
            'recupera_funil' => 'nullable|boolean',
            'hr_modo' => 'required|in:24h,faixa',
            'hr_inicio' => 'nullable|string|max:5',
            'hr_fim' => 'nullable|string|max:5',
            'hr_dias' => 'nullable|array',
            'regras' => 'nullable|array',
        ]);

        $payload = [
            'nome' => $data['nome'],
            'modulo' => $data['modulo'],
            'llm_provedor_id' => $data['llm_provedor_id'] ?: null,
            'whatsapp_conexao_id' => $data['whatsapp_conexao_id'] ?: null,
            'pagamento_api_id' => $data['pagamento_api_id'] ?: null,
            'ativo' => $request->boolean('ativo', true),
            'persona' => $data['persona'] ?? null,
            'assumir_modo' => $data['assumir_modo'],
            'assumir_min' => (int) ($data['assumir_min'] ?? 2),
            'recupera_funil' => $request->boolean('recupera_funil'),
            'regras' => [
                'desligado' => (bool) ($request->input('regras.desligado')),
                'nao_revela_robo' => (bool) ($request->input('regras.nao_revela_robo')),
                'sensiveis_humano' => (bool) ($request->input('regras.sensiveis_humano')),
                'nunca_inventa' => (bool) ($request->input('regras.nunca_inventa')),
            ],
            'horario' => [
                'modo' => $data['hr_modo'],
                'inicio' => $data['hr_inicio'] ?: '08:00',
                'fim' => $data['hr_fim'] ?: '18:00',
                'dias' => array_map('intval', $data['hr_dias'] ?? [1, 2, 3, 4, 5, 6, 7]),
            ],
        ];

        if ($agente && $agente->exists) {
            abort_unless($agente->empresa_id === Tenant::id(), 404);
            $agente->update($payload);
            return back()->with('ok', 'Agente atualizado.');
        }
        Agente::create($payload);
        return back()->with('ok', 'Agente criado.');
    }

    public function testar(Request $request, Agente $agente, AgenteBot $bot)
    {
        abort_unless($agente->empresa_id === Tenant::id(), 404);
        $msg = trim((string) $request->input('msg')) ?: 'Oi, quero uma passagem';
        $r = $bot->responderTeste($agente, $msg);
        return back()->with($r['ok'] ? 'ok' : 'erro',
            $r['ok'] ? ('🤖 Resposta do agente: '.$r['texto']) : ('Falhou: '.($r['erro'] ?? 'erro')));
    }

    public function excluir(Agente $agente)
    {
        abort_unless($agente->empresa_id === Tenant::id(), 404);
        $agente->delete();
        return back()->with('ok', 'Agente removido.');
    }
}
