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
            'llm_escalonar_id' => 'nullable|integer',
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
            'meses_venda' => 'nullable|integer|min:1|max:12',
            'ocioso_aviso_min' => 'nullable|integer|min:0|max:120',
            'ocioso_encerrar_min' => 'nullable|integer|min:1|max:60',
            'ocioso_pix_min' => 'nullable|integer|min:1|max:240',
        ]);

        // Token OpenAI do áudio: em branco no editar = mantém o atual (igual aos tokens secretos do app).
        $audioTokNovo  = trim((string) $request->input('regras.audio_token'));
        $audioTokAtual = ($agente && $agente->exists) ? (string) ($agente->regras['audio_token'] ?? '') : '';
        $audioToken    = $audioTokNovo !== '' ? $audioTokNovo : ($audioTokAtual ?: null);

        $payload = [
            'nome' => $data['nome'],
            'modulo' => $data['modulo'],
            'llm_provedor_id' => $data['llm_provedor_id'] ?: null,
            'llm_escalonar_id' => $data['llm_escalonar_id'] ?? null ?: null,
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
                'autonomo' => (bool) ($request->input('regras.autonomo')),
                'faz_orcamento' => (bool) ($request->input('regras.faz_orcamento')),
                'orc_min_pessoas' => max(2, min(200, (int) ($request->input('regras.orc_min_pessoas') ?: 8))),
                'orc_desconto_max' => max(0, min(50, (int) $request->input('regras.orc_desconto_max'))),
                'nunca_inventa' => (bool) ($request->input('regras.nunca_inventa')),
                'checkin_auto' => (bool) ($request->input('regras.checkin_auto')),
                'mapa_imagem' => (bool) ($request->input('regras.mapa_imagem')),
                'audio_modo' => in_array($request->input('regras.audio_modo'), ['texto', 'audio', 'audio_texto'], true) ? $request->input('regras.audio_modo') : 'texto',
                'responde_audio' => $request->input('regras.audio_modo', 'texto') !== 'texto',
                'inclui_comida' => (bool) $request->input('regras.inclui_comida'),
                'audio_token' => $audioToken,
                'audio_voz' => $request->input('regras.audio_voz') ?: 'alloy',
                'meses_venda' => (int) ($request->input('meses_venda') ?: 2),
                'ocioso_aviso_min' => $request->filled('ocioso_aviso_min') ? max(0, (int) $request->input('ocioso_aviso_min')) : 2,
                'ocioso_encerrar_min' => max(1, (int) ($request->input('ocioso_encerrar_min') ?: 1)),
                'ocioso_pix_min' => max(1, (int) ($request->input('ocioso_pix_min') ?: 30)),
                'limite_cliente_dia' => $request->filled('regras.limite_cliente_dia') ? max(0, min(1000, (int) $request->input('regras.limite_cliente_dia'))) : 40,
                'limite_total_dia' => max(0, min(200000, (int) $request->input('regras.limite_total_dia'))),
                'limite_msg' => mb_substr(trim((string) $request->input('regras.limite_msg')), 0, 300),
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

    /** Testa a API de áudio da OpenAI (valida o token e gera uma voz de teste). */
    public function testarAudio(Request $request, Agente $agente)
    {
        abort_unless($agente->empresa_id === Tenant::id(), 404);
        $tok = trim((string) $request->input('token'));
        if ($tok === '') $tok = trim((string) ($agente->regras['audio_token'] ?? ''));
        if ($tok === '') {
            return response()->json(['ok' => false, 'msg' => '❌ Sem token. Cole o token no campo (ou salve o agente) e teste de novo.']);
        }

        // 1) Token válido? (lista de modelos)
        try {
            $m = \Illuminate\Support\Facades\Http::timeout(20)->withToken($tok)->get('https://api.openai.com/v1/models');
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => '❌ Não conectou na OpenAI: '.$e->getMessage()]);
        }
        if ($m->status() === 401) {
            return response()->json(['ok' => false, 'msg' => '❌ Token inválido (401). Crie uma chave nova em platform.openai.com/api-keys e cole aqui.']);
        }
        if (! $m->successful()) {
            return response()->json(['ok' => false, 'msg' => '❌ OpenAI recusou ('.$m->status().'): '.mb_substr($m->body(), 0, 160)]);
        }

        // 2) Gera uma voz de teste (valida acesso a áudio + crédito)
        $voz = (string) ($agente->regras['audio_voz'] ?? 'alloy') ?: 'alloy';
        try {
            $t = \Illuminate\Support\Facades\Http::timeout(30)->withToken($tok)->post('https://api.openai.com/v1/audio/speech', [
                'model' => 'tts-1', 'voice' => $voz, 'input' => 'Teste de voz do agente. Está funcionando.', 'response_format' => 'mp3',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => '❌ Erro ao gerar voz: '.$e->getMessage()]);
        }
        if ($t->successful() && strlen((string) $t->body()) > 500) {
            $kb = (int) round(strlen((string) $t->body()) / 1024);
            return response()->json(['ok' => true, 'msg' => "✅ Tudo certo! Token válido e voz gerada ({$kb} KB). O áudio vai funcionar."]);
        }
        $err = mb_strtolower((string) $t->body());
        if (str_contains($err, 'insufficient_quota') || str_contains($err, 'quota') || str_contains($err, 'billing')) {
            return response()->json(['ok' => false, 'msg' => '❌ A chave é válida, mas o projeto está SEM crédito/billing ativo na OpenAI. Adicione crédito e teste de novo.']);
        }
        if (str_contains($err, 'model')) {
            return response()->json(['ok' => false, 'msg' => '❌ Essa chave não tem acesso ao modelo de voz (tts-1). Gere a chave com permissões "All".']);
        }
        return response()->json(['ok' => false, 'msg' => '❌ Falhou ao gerar voz ('.$t->status().'): '.mb_substr($t->body(), 0, 160)]);
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
