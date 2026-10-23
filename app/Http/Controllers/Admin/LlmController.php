<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LlmProvedor;
use App\Services\LLM;
use App\Support\Tenant;
use Illuminate\Http\Request;

class LlmController extends Controller
{
    public function index()
    {
        return view('admin.llm', ['provedores' => LlmProvedor::orderBy('nome')->get()]);
    }

    public function salvar(Request $request, ?LlmProvedor $provedor = null)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:80',
            'provider' => 'required|in:claude,openai',
            'token' => 'nullable|string|max:500',
            'modelo' => 'nullable|string|max:80',
            'base_url' => 'nullable|string|max:255',
            'ativo' => 'nullable|boolean',
        ]);
        $data['ativo'] = $request->boolean('ativo', true);

        if ($provedor && $provedor->exists) {
            abort_unless($provedor->empresa_id === Tenant::id(), 404);
            if (blank($data['token'] ?? null)) unset($data['token']);
            $provedor->update($data);
            return back()->with('ok', 'Provedor atualizado.');
        }
        LlmProvedor::create($data);
        return back()->with('ok', 'Provedor criado.');
    }

    public function excluir(LlmProvedor $provedor)
    {
        abort_unless($provedor->empresa_id === Tenant::id(), 404);
        $provedor->delete();
        return back()->with('ok', 'Provedor removido.');
    }

    public function testar(LlmProvedor $provedor, LLM $llm)
    {
        abort_unless($provedor->empresa_id === Tenant::id(), 404);
        $r = $llm->validar($provedor);
        return back()->with($r['ok'] ? 'ok' : 'erro',
            $r['ok'] ? ('IA respondendo (modelo: '.($r['modelo'] ?: '—').')') : ('Falhou: '.($r['erro'] ?? 'erro')));
    }
}
