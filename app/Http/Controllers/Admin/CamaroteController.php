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
        return view('admin.camarotes', [
            'camarotes' => Camarote::with('precos')->orderBy('ordem')->orderBy('nome')->get(),
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
        $vistos = [];
        foreach ($linhas as $l) {
            $o = trim((string) ($l['origem'] ?? ''));
            $d = trim((string) ($l['destino'] ?? ''));
            $raw = (string) ($l['valor'] ?? '0');
            if (str_contains($raw, ',') && str_contains($raw, '.')) { $raw = str_replace(['.', ','], ['', '.'], $raw); }
            elseif (str_contains($raw, ',')) { $raw = str_replace(',', '.', $raw); }
            $v = (float) preg_replace('/[^\d.]/', '', $raw);
            if ($o === '' || $d === '' || $o === $d) continue;
            if ($v <= 0) continue;
            CamarotePreco::updateOrCreate(
                ['empresa_id' => Tenant::id(), 'camarote_id' => $camarote->id, 'origem' => $o, 'destino' => $d],
                ['valor' => $v]
            );
            $vistos[] = mb_strtolower($o.'|'.$d);
        }
        // remove os trechos que sumiram da tela
        foreach ($camarote->precos()->get() as $pre) {
            if (! in_array(mb_strtolower($pre->origem.'|'.$pre->destino), $vistos, true)) $pre->delete();
        }
        return back()->with('ok', 'Preços do '.$camarote->nome.' salvos.');
    }
}
