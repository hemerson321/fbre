<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmpresaController extends Controller
{
    // ---------- admin da empresa: edita a própria ----------
    public function editar()
    {
        return view('admin.empresa', ['empresa' => Tenant::empresa()]);
    }

    public function salvar(Request $request)
    {
        $empresa = Tenant::empresa();
        $data = $request->validate([
            'nome' => 'required|string|max:150',
            'nome_fantasia' => 'nullable|string|max:100',
            'cnpj' => 'nullable|string|max:20',
            'telefone' => 'nullable|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
            'email' => 'nullable|email',
            'endereco' => 'nullable|string|max:200',
            'cidade' => 'nullable|string|max:80',
            'uf' => 'nullable|string|max:2',
            'pix' => 'nullable|string|max:150',
            'cor_primaria' => 'required|string|max:9',
            'cor_secundaria' => 'required|string|max:9',
            'rodape_comprovante' => 'nullable|string|max:500',
            'logo' => 'nullable|image|max:2048',
            'label_taxa_servico' => 'nullable|string|max:40',
            'label_alimentacao' => 'nullable|string|max:40',
        ]);

        // Componentes da passagem (opcionais): taxa de servico e alimentacao
        $config = $empresa->config ?? [];
        $config['usa_taxa_servico'] = $request->boolean('usa_taxa_servico');
        $config['usa_alimentacao'] = $request->boolean('usa_alimentacao');
        $config['label_taxa_servico'] = trim((string) $request->input('label_taxa_servico')) ?: 'Taxa de serviço';
        $config['label_alimentacao'] = trim((string) $request->input('label_alimentacao')) ?: 'Alimentação';
        $data['config'] = $config;
        unset($data['label_taxa_servico'], $data['label_alimentacao']);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $data['logo'] = 'storage/'.$path;
        }

        $empresa->update($data);
        return back()->with('ok', 'Dados da empresa salvos.');
    }

    // ---------- super-admin ----------
    public function index()
    {
        $empresas = Empresa::withCount('users', 'encomendas')->orderBy('nome')->get();
        return view('admin.empresas', ['empresas' => $empresas, 'ativa' => session('empresa_ativa')]);
    }

    public function store(Request $request)
    {
        $request->merge(['admin_login' => strtolower(trim((string) $request->input('admin_login')))]);
        $data = $request->validate([
            'nome' => 'required|string|max:150',
            'nome_fantasia' => 'nullable|string|max:100',
            'cnpj' => 'nullable|string|max:20',
            'validade' => 'nullable|date',
            'admin_nome' => 'required|string|max:100',
            'admin_login' => 'required|string|min:3|max:40|regex:/^[a-z0-9._-]+$/|unique:users,login',
            'admin_senha' => 'required|string|min:6',
        ]);

        $slug = Str::slug($data['nome_fantasia'] ?: $data['nome']);
        $slug = Str::limit($slug, 50, '');
        $i = 1; $base = $slug;
        while (Empresa::where('slug', $slug)->exists()) { $slug = $base.'-'.(++$i); }

        $empresa = Empresa::create([
            'nome' => $data['nome'],
            'nome_fantasia' => $data['nome_fantasia'] ?? null,
            'cnpj' => $data['cnpj'] ?? null,
            'slug' => $slug,
            'validade' => $data['validade'] ?? null,
            'recursos' => ['print_agent'],
        ]);

        User::create([
            'empresa_id' => $empresa->id,
            'name' => $data['admin_nome'],
            'login' => $data['admin_login'],
            'password' => $data['admin_senha'],
            'admin' => true,
            'perm_cadastra' => true, 'perm_baixa' => true, 'perm_caixa' => true,
        ]);

        // dados base
        Tenant::set($empresa->id);
        foreach (['Escritório', 'Porto'] as $s) \App\Models\Setor::create(['nome' => $s]);
        foreach (['Caixa pequena', 'Caixa média', 'Caixa grande'] as $p) \App\Models\Produto::create(['nome' => $p, 'valor_padrao' => 0]);
        Tenant::set(auth()->user()->empresa_id);

        return back()->with('ok', "Empresa {$empresa->nomeExibicao()} criada.");
    }

    public function toggle(Empresa $empresa)
    {
        $empresa->update(['ativo' => ! $empresa->ativo]);
        return back()->with('ok', $empresa->ativo ? 'Empresa ativada.' : 'Empresa desativada.');
    }

    public function entrar(Request $request, Empresa $empresa)
    {
        $request->session()->put('empresa_ativa', $empresa->id);
        return redirect()->route('encomendas.index')->with('ok', "Você está operando como {$empresa->nomeExibicao()}.");
    }

    public function sair(Request $request)
    {
        $request->session()->forget('empresa_ativa');
        return redirect()->route('super.empresas');
    }
}
