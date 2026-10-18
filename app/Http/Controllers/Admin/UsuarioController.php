<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Embarcacao;
use App\Models\Rota;
use App\Models\Setor;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    protected function query()
    {
        return User::where('empresa_id', Tenant::id())->where('super_admin', false);
    }

    public function index()
    {
        return view('admin.usuarios', [
            'usuarios' => $this->query()->orderBy('name')->get(),
            'setores' => Setor::orderBy('nome')->pluck('nome'),
        ]);
    }

    protected function dadosForm(User $u): array
    {
        return [
            'u' => $u,
            'setores' => Setor::orderBy('nome')->pluck('nome'),
            'cidades' => Rota::todasLocalidades(),
            'embarcacoes' => Embarcacao::where('ativo', true)->orderBy('nome')->get(),
        ];
    }

    public function create()
    {
        return view('admin.usuario-form', $this->dadosForm(new User));
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $data['empresa_id'] = Tenant::id();
        User::create($data);
        return redirect()->route('admin.usuarios.index')->with('ok', 'Usuário criado.');
    }

    public function edit(User $usuario)
    {
        abort_unless($usuario->empresa_id === Tenant::id(), 404);
        return view('admin.usuario-form', $this->dadosForm($usuario));
    }

    public function update(Request $request, User $usuario)
    {
        abort_unless($usuario->empresa_id === Tenant::id(), 404);
        $data = $this->validar($request, $usuario);
        if (empty($data['password'])) unset($data['password']);
        $usuario->update($data);
        return redirect()->route('admin.usuarios.index')->with('ok', 'Usuário atualizado.');
    }

    public function destroy(User $usuario)
    {
        abort_unless($usuario->empresa_id === Tenant::id(), 404);
        abort_if($usuario->id === auth()->id(), 422, 'Você não pode se excluir.');
        $usuario->update(['ativo' => false]);
        return back()->with('ok', 'Usuário desativado.');
    }

    protected function validar(Request $request, ?User $u = null): array
    {
        $request->merge(['login' => strtolower(trim((string) $request->input('login')))]);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'login' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'login')->ignore($u?->id)],
            'password' => [$u ? 'nullable' : 'required', 'string', 'min:6'],
            'setor' => 'nullable|string|max:60',
            'papel' => 'nullable|in:operador_barco,agencia_externa,gerente',
            'admin' => 'nullable|boolean',
            'perm_cadastra' => 'nullable|boolean',
            'perm_baixa' => 'nullable|boolean',
            'perm_caixa' => 'nullable|boolean',
            'caixa_todos' => 'nullable|boolean',
            'perm_filtro' => 'nullable|boolean',
            'perm_relatorio' => 'nullable|boolean',
            'relatorio_todos' => 'nullable|boolean',
            'perm_imprimir' => 'nullable|boolean',
            'perm_retroativo' => 'nullable|boolean',
            'ativo' => 'nullable|boolean',
            'loc_permitidas' => 'nullable|array',
            'loc_permitidas.*' => 'string|max:80',
            'setores_permitidos' => 'nullable|array',
            'setores_permitidos.*' => 'string|max:60',
            'transportes_permitidos' => 'nullable|array',
            'transportes_permitidos.*' => 'integer',
            'embarcacao_principal_id' => 'nullable|integer',
        ], ['login.regex' => 'O login deve ter só letras minúsculas, números, ponto, traço ou underline.']);
        foreach (['admin', 'perm_cadastra', 'perm_baixa', 'perm_caixa', 'caixa_todos', 'perm_filtro', 'perm_relatorio', 'relatorio_todos', 'perm_imprimir', 'perm_retroativo', 'ativo'] as $b) {
            $data[$b] = $request->boolean($b);
        }
        // listas vazias viram null (= tudo liberado)
        foreach (['loc_permitidas', 'setores_permitidos', 'transportes_permitidos'] as $lista) {
            $data[$lista] = ! empty($data[$lista]) ? array_values($data[$lista]) : null;
        }
        $data['embarcacao_principal_id'] = $data['embarcacao_principal_id'] ?? null;
        if (! $u) $data['ativo'] = true;
        return $data;
    }
}
