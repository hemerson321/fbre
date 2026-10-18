<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\PreCadastro;
use App\Models\Produto;
use App\Models\Rota;
use App\Support\Tenant;
use Illuminate\Http\Request;

/**
 * Pré-cadastro público (o cliente escaneia o QR e preenche sozinho).
 * Sem login — identifica a empresa pelo slug na URL.
 */
class PreCadastroPublicController extends Controller
{
    protected function empresa(string $slug): Empresa
    {
        $empresa = Empresa::where('slug', $slug)->where('ativo', true)->first();
        abort_unless($empresa, 404);
        Tenant::set($empresa->id);
        return $empresa;
    }

    public function form(string $slug)
    {
        $empresa = $this->empresa($slug);
        return view('publico.pre-cadastro', [
            'empresa' => $empresa,
            'cidades' => Rota::todasLocalidades(),
            'produtos' => Produto::where('ativo', true)->orderByDesc('usos')->orderBy('nome')->limit(12)->pluck('nome'),
        ]);
    }

    public function store(Request $slugReq, string $slug)
    {
        $empresa = $this->empresa($slug);

        $data = $slugReq->validate([
            'origem' => 'required|string|max:80',
            'destino' => 'required|string|max:80',
            'remetente_nome' => 'required|string|max:120',
            'remetente_telefone' => 'nullable|string|max:30',
            'destinatario_nome' => 'required|string|max:120',
            'destinatario_telefone' => 'nullable|string|max:30',
            'observacao' => 'nullable|string|max:500',
            'itens' => 'nullable|array|max:20',
            'itens.*.descricao' => 'required|string|max:120',
            'itens.*.quantidade' => 'nullable|numeric|min:0.01|max:9999',
        ]);

        $itens = collect($data['itens'] ?? [])
            ->map(fn ($i) => ['descricao' => trim($i['descricao']), 'quantidade' => (float) ($i['quantidade'] ?? 1)])
            ->filter(fn ($i) => $i['descricao'] !== '')->values()->all();

        $pre = PreCadastro::create([
            'empresa_id' => $empresa->id,
            'codigo' => PreCadastro::gerarCodigo($empresa->id),
            'origem' => $data['origem'],
            'destino' => $data['destino'],
            'remetente_nome' => $data['remetente_nome'],
            'remetente_telefone' => $data['remetente_telefone'] ?? null,
            'destinatario_nome' => $data['destinatario_nome'],
            'destinatario_telefone' => $data['destinatario_telefone'] ?? null,
            'itens' => $itens,
            'observacao' => $data['observacao'] ?? null,
            'status' => 'pendente',
        ]);

        return redirect()->route('pre.ok', ['slug' => $slug, 'codigo' => $pre->codigo]);
    }

    public function ok(string $slug, string $codigo)
    {
        $empresa = $this->empresa($slug);
        $pre = PreCadastro::where('codigo', $codigo)->firstOrFail();
        return view('publico.pre-ok', ['empresa' => $empresa, 'pre' => $pre, 'slug' => $slug]);
    }
}
