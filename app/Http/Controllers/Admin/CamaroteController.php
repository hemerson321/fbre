<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Camarote;
use App\Models\CamarotePreco;
use App\Models\Rota;
use App\Support\Tenant;
use Illuminate\Http\Request;

/** Catálogo de suítes/camarotes: tipos (capacidade, descrição, adicional) e preço por trecho. */
class CamaroteController extends Controller
{
    public function index()
    {
        $rotas = Rota::with('paradas')->orderBy('nome')->get()->map(fn ($r) => [
            'id' => $r->id,
            'nome' => $r->nome,
            'cidades' => $r->paradas->pluck('localidade')->filter()->values(),
        ])->values();

        return view('admin.camarotes', [
            'camarotes' => Camarote::with('precos')->orderBy('ordem')->orderBy('nome')->get(),
            'rotas' => $rotas,
            'localidades' => Rota::todasLocalidades(),
        ]);
    }

    public function salvar(Request $request, ?Camarote $camarote = null)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:60',
            'capacidade' => 'required|integer|min:1|max:50',
            'descricao' => 'nullable|string|max:1000',
            'valor_adicional' => 'nullable|numeric|min:0',
            'ativo' => 'nullable|boolean',
        ]);
        $payload = [
            'nome' => $data['nome'],
            'capacidade' => (int) $data['capacidade'],
            'descricao' => $data['descricao'] ?? null,
            'valor_adicional' => (float) ($data['valor_adicional'] ?? 0),
            'ativo' => $request->boolean('ativo'),
        ];

        if ($camarote && $camarote->exists) {
            abort_unless($camarote->empresa_id === Tenant::id(), 404);
            $camarote->update($payload);
            return back()->with('ok', 'Suíte/Camarote atualizado.');
        }
        Camarote::create($payload);
        return back()->with('ok', 'Suíte/Camarote criado.');
    }

    public function excluir(Camarote $camarote)
    {
        abort_unless($camarote->empresa_id === Tenant::id(), 404);
        $camarote->delete();
        return back()->with('ok', 'Suíte/Camarote removido.');
    }

    /** Salva a tabela de preços por trecho (origem→destino→R$) de um camarote. */
    public function salvarPrecos(Request $request, Camarote $camarote)
    {
        abort_unless($camarote->empresa_id === Tenant::id(), 404);
        $linhas = $request->input('precos', []);
        $salvos = 0;
        foreach ($linhas as $l) {
            $o = trim((string) ($l['origem'] ?? ''));
            $d = trim((string) ($l['destino'] ?? ''));
            $raw = (string) ($l['valor'] ?? '');
            if (str_contains($raw, ',') && str_contains($raw, '.')) { $raw = str_replace(['.', ','], ['', '.'], $raw); }
            elseif (str_contains($raw, ',')) { $raw = str_replace(',', '.', $raw); }
            $v = (float) preg_replace('/[^\d.]/', '', $raw);
            if ($o === '' || $d === '' || $o === $d) continue;

            if ($v > 0) {
                CamarotePreco::updateOrCreate(
                    ['empresa_id' => Tenant::id(), 'camarote_id' => $camarote->id, 'origem' => $o, 'destino' => $d],
                    ['valor' => $v]
                );
                $salvos++;
            } else {
                // valor zerado/limpo = remove esse trecho (não mexe nos trechos de outras rotas)
                CamarotePreco::where('camarote_id', $camarote->id)->where('origem', $o)->where('destino', $d)->delete();
            }
        }
        return back()->with('ok', "Preços do {$camarote->nome} salvos ({$salvos} trecho(s)).");
    }
}
