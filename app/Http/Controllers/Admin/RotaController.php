<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Embarcacao;
use App\Models\Localidade;
use App\Models\Rota;
use Illuminate\Http\Request;

class RotaController extends Controller
{
    public function index()
    {
        // rotas agora vivem dentro de "Cadastros gerais"
        return redirect()->route('admin.cadastros');
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $rota = Rota::create(['nome' => $data['nome'], 'embarcacao_id' => $data['embarcacao_id'] ?? null, 'descricao' => $data['descricao'] ?? null]);
        $this->salvarParadas($rota, $data['paradas']);
        return back()->with('ok', "Rota {$rota->nome} criada.");
    }

    public function update(Request $request, Rota $rota)
    {
        abort_unless($rota->empresa_id === \App\Support\Tenant::id(), 404);
        $data = $this->validar($request);
        $rota->update(['nome' => $data['nome'], 'embarcacao_id' => $data['embarcacao_id'] ?? null, 'descricao' => $data['descricao'] ?? null]);
        $rota->paradas()->delete();
        $this->salvarParadas($rota, $data['paradas']);
        return back()->with('ok', 'Rota atualizada.');
    }

    public function toggle(Rota $rota)
    {
        abort_unless($rota->empresa_id === \App\Support\Tenant::id(), 404);
        $rota->update(['ativo' => ! $rota->ativo]);
        return back()->with('ok', $rota->ativo ? 'Rota ativada.' : 'Rota desativada.');
    }

    protected function validar(Request $request): array
    {
        return $request->validate([
            'nome' => 'required|string|max:80',
            'embarcacao_id' => 'nullable|integer',
            'descricao' => 'nullable|string|max:255',
            'paradas' => 'required|array|min:2',
            'paradas.*.localidade' => 'required|string|max:80',
            'paradas.*.dia_semana' => 'nullable|integer|between:0,6',
            'paradas.*.hora' => 'nullable',
        ]);
    }

    protected function salvarParadas(Rota $rota, array $paradas): void
    {
        $ordem = 1;
        foreach ($paradas as $p) {
            if (empty($p['localidade'])) continue;
            $rota->paradas()->create([
                'ordem' => $ordem++,
                'localidade' => $p['localidade'],
                'dia_semana' => ($p['dia_semana'] ?? '') === '' ? null : (int) $p['dia_semana'],
                'hora' => $p['hora'] ?: null,
            ]);
        }
    }
}
