<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PagamentoApi;
use App\Services\MercadoPago;
use App\Support\Modulos;
use App\Support\Tenant;
use Illuminate\Http\Request;

class PagamentoController extends Controller
{
    public function index()
    {
        return view('admin.pagamentos', [
            'apis' => PagamentoApi::orderBy('nome')->get(),
            'empresa' => Tenant::empresa(),
            'modulos' => Modulos::LISTA,
        ]);
    }

    public function salvar(Request $request, ?PagamentoApi $api = null)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:80',
            'provider' => 'required|in:mercadopago',
            'ativo' => 'nullable|boolean',
            'access_token' => 'nullable|string|max:500',
            'public_key' => 'nullable|string|max:255',
            'webhook_secret' => 'nullable|string|max:255',
        ]);
        $data['ativo'] = $request->boolean('ativo', true);

        if ($api && $api->exists) {
            abort_unless($api->empresa_id === Tenant::id(), 404);
            // não apaga o token se vier vazio (campo secreto deixado em branco)
            if (blank($data['access_token'] ?? null)) {
                unset($data['access_token']);
            }
            $api->update($data);
            return back()->with('ok', 'API de pagamento atualizada.');
        }
        PagamentoApi::create($data);
        return back()->with('ok', 'API de pagamento criada.');
    }

    public function excluir(PagamentoApi $api)
    {
        abort_unless($api->empresa_id === Tenant::id(), 404);
        $api->delete();
        return back()->with('ok', 'API removida.');
    }

    public function testar(PagamentoApi $api, MercadoPago $mp)
    {
        abort_unless($api->empresa_id === Tenant::id(), 404);
        $r = $mp->validar($api);
        return back()->with($r['ok'] ? 'ok' : 'erro',
            $r['ok'] ? ('Conectado à conta: '.$r['conta']) : ('Falhou: '.($r['erro'] ?? 'erro')));
    }

    /** Atrela uma API a cada módulo e liga/desliga o PIX dinâmico do módulo. */
    public function atribuir(Request $request)
    {
        $empresa = Tenant::empresa();
        $config = $empresa->config ?? [];
        foreach (array_keys(Modulos::LISTA) as $m) {
            $config['pgto_api_'.$m] = $request->input('pgto_api.'.$m) ?: null;
            $config['pix_'.$m] = $request->boolean('pix.'.$m);
        }
        $empresa->forceFill(['config' => $config])->save();
        return back()->with('ok', 'Pagamento por módulo atualizado.');
    }
}
