<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClienteFaturado;
use Illuminate\Http\Request;

class ClienteFaturadoController extends Controller
{
    /** Detecta CPF (11) ou CNPJ (14) pela quantidade de dígitos. */
    protected function docTipo(?string $doc): ?string
    {
        $d = preg_replace('/\D/', '', (string) $doc);
        if (strlen($d) === 14) return 'CNPJ';
        if (strlen($d) >= 11) return 'CPF';
        return null;
    }

    protected function filtro($q)
    {
        return fn ($w) => $w->where('nome', 'like', "%$q%")
            ->orWhere('empresa', 'like', "%$q%")
            ->orWhere('documento', 'like', "%$q%");
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $clientes = ClienteFaturado::when($q !== '', fn ($x) => $x->where($this->filtro($q)))
            ->orderBy('nome')->paginate(30)->withQueryString();
        return view('admin.clientes-faturado', ['clientes' => $clientes, 'q' => $q]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:120',
            'empresa' => 'nullable|string|max:120',
            'documento' => 'nullable|string|max:20',
            'telefone' => 'nullable|string|max:30',
        ]);
        ClienteFaturado::create([
            'nome' => $data['nome'],
            'empresa' => $data['empresa'] ?? null,
            'documento' => $data['documento'] ?? null,
            'doc_tipo' => $this->docTipo($data['documento'] ?? null),
            'telefone' => $data['telefone'] ?? null,
            'ativo' => true,
        ]);
        return back()->with('ok', 'Cliente faturado cadastrado.');
    }

    public function update(Request $request, ClienteFaturado $cliente)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:120',
            'empresa' => 'nullable|string|max:120',
            'documento' => 'nullable|string|max:20',
            'telefone' => 'nullable|string|max:30',
            'ativo' => 'nullable|boolean',
        ]);
        $cliente->update([
            'nome' => $data['nome'],
            'empresa' => $data['empresa'] ?? null,
            'documento' => $data['documento'] ?? null,
            'doc_tipo' => $this->docTipo($data['documento'] ?? null),
            'telefone' => $data['telefone'] ?? null,
            'ativo' => $request->boolean('ativo', true),
        ]);
        return back()->with('ok', 'Cliente atualizado.');
    }

    public function destroy(ClienteFaturado $cliente)
    {
        // se já foi usado em alguma passagem, só desativa (preserva histórico)
        $usado = \App\Models\Passagem::where('cliente_faturado_id', $cliente->id)->exists();
        if ($usado) {
            $cliente->update(['ativo' => false]);
            return back()->with('erro', 'Cliente já usado em vendas — foi desativado em vez de excluído (preserva o histórico).');
        }
        $cliente->delete();
        return back()->with('ok', 'Cliente removido.');
    }

    /** Busca pra venda (JSON). */
    public function buscar(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 1) return [];
        return ClienteFaturado::where('ativo', true)->where($this->filtro($q))
            ->orderBy('nome')->limit(10)->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'nome' => $c->nome, 'empresa' => $c->empresa,
                'documento' => $c->documento, 'doc_tipo' => $c->doc_tipo, 'telefone' => $c->telefone,
                'rotulo' => $c->rotulo(),
            ]);
    }

    /** Cadastro rápido direto da venda (JSON). */
    public function criarRapido(Request $request)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:120',
            'empresa' => 'nullable|string|max:120',
            'documento' => 'nullable|string|max:20',
            'telefone' => 'nullable|string|max:30',
        ]);
        $c = ClienteFaturado::create([
            'nome' => $data['nome'],
            'empresa' => $data['empresa'] ?? null,
            'documento' => $data['documento'] ?? null,
            'doc_tipo' => $this->docTipo($data['documento'] ?? null),
            'telefone' => $data['telefone'] ?? null,
            'ativo' => true,
        ]);
        return ['ok' => true, 'cliente' => [
            'id' => $c->id, 'nome' => $c->nome, 'empresa' => $c->empresa,
            'documento' => $c->documento, 'doc_tipo' => $c->doc_tipo, 'rotulo' => $c->rotulo(),
        ]];
    }
}
