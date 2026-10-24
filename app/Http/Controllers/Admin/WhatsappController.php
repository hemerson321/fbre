<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConexao;
use App\Services\WhatsApp;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class WhatsappController extends Controller
{
    public function index()
    {
        return view('admin.whatsapp', [
            'conexoes' => WhatsappConexao::orderBy('nome')->get(),
            'empresa' => Tenant::empresa(),
        ]);
    }

    public function salvar(Request $request, ?WhatsappConexao $conexao = null)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:80',
            'tipo' => 'required|in:nao_oficial,oficial',
            'ativo' => 'nullable|boolean',
            // nao oficial
            'base_url' => 'nullable|string|max:255',
            'instancia' => 'nullable|string|max:120',
            'api_key' => 'nullable|string|max:255',
            // oficial
            'phone_number_id' => 'nullable|string|max:60',
            'token' => 'nullable|string',
            'business_id' => 'nullable|string|max:60',
            'api_version' => 'nullable|string|max:12',
        ]);
        $data['ativo'] = $request->boolean('ativo', true);
        $data['api_version'] = $data['api_version'] ?: 'v21.0';

        if ($conexao && $conexao->exists) {
            $conexao->update($data);
            return back()->with('ok', 'Conexão atualizada.');
        }

        // Não oficial: usa o Servidor Evolution da empresa e cria a instância sozinho
        if (($data['tipo'] ?? '') === 'nao_oficial') {
            $empresa = Tenant::empresa();
            $data['base_url'] = ($data['base_url'] ?? '') ?: $empresa->config('evo_url');
            $data['api_key'] = ($data['api_key'] ?? '') ?: $empresa->config('evo_key');
            if (empty($data['instancia'])) {
                $data['instancia'] = Str::slug($data['nome']).'-'.Str::lower(Str::random(4));
            }
        }

        $nova = WhatsappConexao::create($data);

        if ($nova->tipo === 'nao_oficial') {
            if (! $nova->base_url || ! $nova->api_key) {
                return back()->with('erro', 'Conexão criada, mas configure o Servidor Evolution (URL + chave global) no topo para criar a instância automaticamente.');
            }
            $r = app(WhatsApp::class)->evoCriar($nova, route('wa.webhook', $nova->id));
            return back()->with($r['ok'] ? 'ok' : 'erro', $r['ok']
                ? 'Conexão criada e instância gerada no Evolution. Agora clique em “Conectar (QR)”.'
                : ('Conexão criada, mas falhou criar no Evolution: '.($r['erro'] ?? 'erro')));
        }
        return back()->with('ok', 'Conexão criada.');
    }

    public function excluir(WhatsappConexao $conexao, WhatsApp $wa)
    {
        abort_unless($conexao->empresa_id === Tenant::id(), 404);
        if ($conexao->tipo === 'nao_oficial' && $conexao->base_url && $conexao->api_key) {
            $wa->evoDeletar($conexao); // best-effort: limpa a instância no Evolution
        }
        $conexao->delete();
        return back()->with('ok', 'Conexão removida.');
    }

    /** Salva o Servidor Evolution da empresa (URL base + chave global) — usado p/ criar instâncias sozinho. */
    public function servidor(Request $request)
    {
        $data = $request->validate([
            'evo_url' => 'nullable|string|max:255',
            'evo_key' => 'nullable|string|max:255',
        ]);
        $empresa = Tenant::empresa();
        $cfg = $empresa->config ?? [];
        $cfg['evo_url'] = trim((string) ($data['evo_url'] ?? '')) !== '' ? rtrim(trim($data['evo_url']), '/') : null;
        if (filled($data['evo_key'] ?? null)) $cfg['evo_key'] = trim($data['evo_key']); // mantém se vazio
        $empresa->forceFill(['config' => $cfg])->save();
        return back()->with('ok', 'Servidor Evolution salvo.');
    }

    public function criar(WhatsappConexao $conexao, WhatsApp $wa)
    {
        abort_unless($conexao->empresa_id === Tenant::id(), 404);
        $r = $wa->evoCriar($conexao, route('wa.webhook', $conexao->id));
        return back()->with($r['ok'] ? 'ok' : 'erro',
            $r['ok'] ? 'Instância pronta e webhook configurado. Clique em “Conectar (QR)”.' : ('Falhou: '.($r['erro'] ?? 'erro')));
    }

    public function conectar(WhatsappConexao $conexao, WhatsApp $wa)
    {
        abort_unless($conexao->empresa_id === Tenant::id(), 404);
        // garante que a instância existe + webhook, depois pede o QR
        $wa->evoCriar($conexao, route('wa.webhook', $conexao->id));
        $r = $wa->evoConectar($conexao);
        // se não veio QR, tenta mais uma vez (o Evolution às vezes demora a gerar)
        if (($r['ok'] ?? false) && empty($r['base64']) && empty($r['code'])) {
            usleep(1200000);
            $r = $wa->evoConectar($conexao);
        }
        return response()->json($r);
    }

    public function diagnostico(WhatsappConexao $conexao, WhatsApp $wa)
    {
        abort_unless($conexao->empresa_id === Tenant::id(), 404);
        $webhookEsperado = route('wa.webhook', $conexao->id);
        $cfg = $wa->evoVerWebhook($conexao);
        $hit = Cache::get('wa_hit_'.$conexao->id);
        return response()->json([
            'webhook_esperado' => $webhookEsperado,
            'webhook_no_evolution' => $cfg,
            'ultimo_recebido' => $hit,
            'status' => $wa->evoStatus($conexao),
        ]);
    }

    public function reconfigurar(WhatsappConexao $conexao, WhatsApp $wa)
    {
        abort_unless($conexao->empresa_id === Tenant::id(), 404);
        $r = $wa->evoWebhook($conexao, route('wa.webhook', $conexao->id));
        return back()->with($r['ok'] ? 'ok' : 'erro', $r['ok'] ? 'Webhook reconfigurado.' : ('Falhou: '.($r['erro'] ?? 'erro')));
    }

    public function status(WhatsappConexao $conexao, WhatsApp $wa)
    {
        abort_unless($conexao->empresa_id === Tenant::id(), 404);
        return response()->json($wa->evoStatus($conexao));
    }

    public function desconectar(WhatsappConexao $conexao, WhatsApp $wa)
    {
        abort_unless($conexao->empresa_id === Tenant::id(), 404);
        $r = $wa->evoDesconectar($conexao);
        return back()->with($r['ok'] ? 'ok' : 'erro', $r['ok'] ? 'Desconectado.' : ('Falhou: '.($r['erro'] ?? 'erro')));
    }

    public function testar(Request $request, WhatsappConexao $conexao, WhatsApp $wa)
    {
        abort_unless($conexao->empresa_id === Tenant::id(), 404);
        $data = $request->validate(['telefone' => 'required|string|max:30']);
        $r = $wa->enviarTexto($conexao, $data['telefone'], 'Teste de conexão FBRE ✅ ('.$conexao->nome.')');
        return back()->with($r['ok'] ? 'ok' : 'erro', $r['ok'] ? 'Mensagem de teste enviada!' : ('Falhou: '.($r['erro'] ?? 'erro')));
    }
}
