<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Embarcacao;
use App\Models\Estacao;
use App\Models\Localidade;
use App\Models\Produto;
use App\Models\Rota;
use App\Models\Setor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Cadastros auxiliares: embarcações, localidades, setores, produtos, estações de impressão. */
class CadastroController extends Controller
{
    protected array $tipos = [
        'embarcacoes' => Embarcacao::class,
        'localidades' => Localidade::class,
        'setores' => Setor::class,
        'produtos' => Produto::class,
    ];

    public function index()
    {
        return view('admin.cadastros', [
            'embarcacoes' => Embarcacao::orderBy('nome')->get(),
            'setores' => Setor::orderBy('nome')->get(),
            'produtos' => Produto::orderBy('nome')->get(),
            'estacoes' => Estacao::orderBy('nome')->get(),
            'rotas' => \App\Support\Tenant::empresa()->temRecurso('viagens') ? Rota::with(['paradas', 'embarcacao'])->orderBy('nome')->get() : collect(),
            'localidadesNomes' => Rota::todasLocalidades(),
            'dias' => Rota::DIAS,
        ]);
    }

    public function store(Request $request, string $tipo)
    {
        abort_unless(isset($this->tipos[$tipo]), 404);
        $data = $request->validate([
            'nome' => 'required|string|max:120',
            'valor_padrao' => 'nullable|numeric|min:0',
        ]);
        $m = $this->tipos[$tipo];
        $m::create($tipo === 'produtos' ? $data : ['nome' => $data['nome']]);
        return back()->with('ok', 'Cadastrado.');
    }

    public function destroy(string $tipo, int $id)
    {
        abort_unless(isset($this->tipos[$tipo]) || $tipo === 'estacoes', 404);
        $m = $tipo === 'estacoes' ? Estacao::class : $this->tipos[$tipo];
        $reg = $m::findOrFail($id);
        if ($tipo === 'setores') { $reg->delete(); }
        else { $reg->update(['ativo' => false]); }
        return back()->with('ok', 'Removido.');
    }

    public function novaEstacao(Request $request)
    {
        $data = $request->validate(['nome' => 'required|string|max:60']);
        $e = Estacao::create(['nome' => $data['nome'], 'token' => Str::random(48)]);
        return back()->with('ok', "Estação criada. Token: {$e->token}")->with('token', $e->token);
    }
}
