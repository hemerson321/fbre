<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Embarcacao;
use App\Models\Localidade;
use App\Models\Rota;
use App\Models\TabelaPreco;
use App\Models\TrechoPreco;
use App\Support\Tenant;
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
        $this->salvarPrecos($data['precos'] ?? [], $data['paradas']);
        return back()->with('ok', "Rota {$rota->nome} criada.");
    }

    public function update(Request $request, Rota $rota)
    {
        abort_unless($rota->empresa_id === \App\Support\Tenant::id(), 404);
        $data = $this->validar($request);
        $rota->update(['nome' => $data['nome'], 'embarcacao_id' => $data['embarcacao_id'] ?? null, 'descricao' => $data['descricao'] ?? null]);
        $rota->paradas()->delete();
        $this->salvarParadas($rota, $data['paradas']);
        $this->salvarPrecos($data['precos'] ?? [], $data['paradas']);
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
            'precos' => 'nullable|array',
            'precos.*.origem' => 'required_with:precos|string|max:80',
            'precos.*.destino' => 'required_with:precos|string|max:80',
            'precos.*.tipo_id' => 'required_with:precos|integer',
            'precos.*.tarifa' => 'nullable|numeric|min:0',
            'precos.*.alim' => 'nullable|numeric|min:0',
            'precos.*.integral' => 'nullable|numeric|min:0',
        ]);
    }

    /** Salva o preço por TIPO de cada trecho (origem→destino) em trecho_precos. */
    protected function salvarPrecos(array $precos, array $paradas = []): void
    {
        $empresaId = Tenant::id();

        // pares (origem,destino) da rota — para limpar antes de regravar (reflete o editor)
        $cidades = [];
        foreach ($paradas as $p) {
            $c = trim($p['localidade'] ?? '');
            if ($c !== '') $cidades[] = $c;
        }
        foreach ($cidades as $o) {
            foreach ($cidades as $d) {
                if (mb_strtolower($o) === mb_strtolower($d)) continue;
                TrechoPreco::where('empresa_id', $empresaId)
                    ->whereRaw('LOWER(origem)=?', [mb_strtolower($o)])
                    ->whereRaw('LOWER(destino)=?', [mb_strtolower($d)])
                    ->delete();
            }
        }

        foreach ($precos as $pr) {
            $o = trim($pr['origem'] ?? '');
            $d = trim($pr['destino'] ?? '');
            $tipoId = (int) ($pr['tipo_id'] ?? 0);
            $tarifa = (float) ($pr['tarifa'] ?? 0);
            $alim = (float) ($pr['alim'] ?? 0);
            $integral = (float) ($pr['integral'] ?? 0);
            if ($o === '' || $d === '' || $tipoId <= 0 || mb_strtolower($o) === mb_strtolower($d)) continue;
            if ($tarifa <= 0 && $alim <= 0 && $integral <= 0) continue;
            if ($integral <= 0) $integral = $tarifa + $alim;
            TrechoPreco::updateOrCreate(
                ['empresa_id' => $empresaId, 'origem' => $o, 'destino' => $d, 'tipo_passageiro_id' => $tipoId],
                ['tarifa' => $tarifa, 'alimentacao' => $alim, 'integral' => $integral, 'ativo' => true],
            );
        }
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
