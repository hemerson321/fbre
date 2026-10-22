<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConexao;
use App\Services\WhatsApp;
use App\Support\Tenant;
use Illuminate\Http\Request;

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
        WhatsappConexao::create($data);
        return back()->with('ok', 'Conexão criada.');
    }

    public function excluir(WhatsappConexao $conexao)
    {
        abort_unless($conexao->empresa_id === Tenant::id(), 404);
        $conexao->delete();
        return back()->with('ok', 'Conexão removida.');
    }

    public function testar(Request $request, WhatsappConexao $conexao, WhatsApp $wa)
    {
        abort_unless($conexao->empresa_id === Tenant::id(), 404);
        $data = $request->validate(['telefone' => 'required|string|max:30']);
        $r = $wa->enviarTexto($conexao, $data['telefone'], 'Teste de conexão FBRE ✅ ('.$conexao->nome.')');
        return back()->with($r['ok'] ? 'ok' : 'erro', $r['ok'] ? 'Mensagem de teste enviada!' : ('Falhou: '.($r['erro'] ?? 'erro')));
    }
}
