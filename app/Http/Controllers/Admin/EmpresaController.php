<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\WhatsappConexao;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmpresaController extends Controller
{
    // ---------- admin da empresa: edita a própria ----------
    public function editar()
    {
        return view('admin.empresa', [
            'empresa' => Tenant::empresa(),
            'conexoesWa' => WhatsappConexao::where('ativo', true)->orderBy('nome')->get(),
            'tiposPassageiro' => \App\Models\TipoPassageiro::where('empresa_id', Tenant::id())->where('ativo', true)->orderBy('eh_normal', 'desc')->orderBy('ordem')->orderBy('nome')->get(['id', 'nome', 'eh_normal', 'idade_min', 'idade_max']),
        ]);
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
            'label_taxa_embarque' => 'nullable|string|max:40',
            'label_alimentacao' => 'nullable|string|max:40',
            'wa_conexao' => 'nullable|array',
            'wa_conexao.*' => 'nullable|integer',
            'cores_por_modulo' => 'nullable|boolean',
            'comprador_passagem' => 'nullable|boolean',
            'ia_provider' => 'nullable|in:claude,openai',
            'ia_token' => 'nullable|string|max:255',
            'ia_modelo' => 'nullable|string|max:80',
            'cores_modulo' => 'nullable|array',
            'cores_modulo.*' => 'nullable|string|max:9',
        ]);

        // Componentes da passagem (opcionais): taxa de servico e alimentacao
        $config = $empresa->config ?? [];
        $config['usa_taxa_embarque'] = $request->boolean('usa_taxa_embarque');
        $config['usa_alimentacao'] = $request->boolean('usa_alimentacao');
        $config['usa_tarifa_transporte'] = $request->boolean('usa_tarifa_transporte');
        $config['usa_desconto_tabela'] = $request->boolean('usa_desconto_tabela');
        $config['comprador_passagem'] = $request->boolean('comprador_passagem');
        $config['usa_cancel_transporte'] = $request->boolean('usa_cancel_transporte');
        $config['cancel_antes_pct'] = max(0, min(100, (int) $request->input('cancel_antes_pct', 10)));
        $config['cancel_depois_pct'] = max(0, min(100, (int) $request->input('cancel_depois_pct', 30)));
        $config['gratuito_qtd'] = max(0, min(999, (int) $request->input('gratuito_qtd', 2)));
        $fusosOk = ['America/Manaus', 'America/Sao_Paulo', 'America/Belem', 'America/Rio_Branco', 'America/Boa_Vista', 'America/Porto_Velho', 'America/Cuiaba', 'America/Fortaleza', 'America/Recife'];
        $fuso = (string) $request->input('fuso');
        $config['fuso'] = in_array($fuso, $fusosOk, true) ? $fuso : 'America/Manaus';
        $config['label_taxa_embarque'] = trim((string) $request->input('label_taxa_embarque')) ?: 'Taxa de embarque';
        $config['label_alimentacao'] = trim((string) $request->input('label_alimentacao')) ?: 'Alimentação';
        foreach (array_keys(\App\Support\Modulos::LISTA) as $mk) {
            $config['wa_conexao_'.$mk] = $request->input('wa_conexao.'.$mk) ?: null;
        }
        $config['cores_por_modulo'] = $request->boolean('cores_por_modulo');
        // cor de cada módulo (só a cor; quais módulos a empresa tem é definido pelo super no cadastro)
        $cores = [];
        foreach (array_keys(\App\Support\Modulos::LISTA) as $mk) {
            $c = (string) $request->input('cores_modulo.'.$mk);
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $c)) $cores[$mk] = strtolower($c);
        }
        $config['cores_modulo'] = $cores;
        $config['meudanfe_token'] = trim((string) $request->input('meudanfe_token')) ?: null;
        $config['ia_provider'] = $request->input('ia_provider') ?: 'claude';
        $config['ia_token'] = trim((string) $request->input('ia_token')) ?: null;
        $config['ia_modelo'] = trim((string) $request->input('ia_modelo')) ?: null;
        $data['config'] = $config;
        unset($data['label_taxa_embarque'], $data['label_alimentacao'], $data['wa_conexao'], $data['cores_por_modulo'], $data['comprador_passagem'], $data['ia_provider'], $data['ia_token'], $data['ia_modelo'], $data['cores_modulo']);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $data['logo'] = 'storage/'.$path;
            // limpa cache de icones p/ regenerar a partir da nova logo
            unset($config['icon_192'], $config['icon_512']);
            $data['config'] = $config;
        }

        try {
            $empresa->update($data);

        // Classificação por idade (padrão da empresa): grava as faixas em cada tipo.
        foreach ((array) $request->input('faixa', []) as $tid => $fx) {
            $tp = \App\Models\TipoPassageiro::where('empresa_id', $empresa->id)->find((int) $tid);
            if (! $tp) continue;
            $mn = ($fx['min'] ?? '') === '' ? null : max(0, min(120, (int) $fx['min']));
            $mx = ($fx['max'] ?? '') === '' ? null : max(0, min(120, (int) $fx['max']));
            $tp->update(['idade_min' => $mn, 'idade_max' => $mx]);
        }
            if ($request->hasFile('logo')) {
                $empresa->refresh()->iconesPwa(); // gera icones quadrados do PWA
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['salvar' => 'Erro ao salvar: '.$e->getMessage()])->withInput();
        }
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
        $request->merge([
            'admin_login' => strtolower(trim((string) $request->input('admin_login'))),
            'dominio' => $this->normalizarDominio($request->input('dominio')),
        ]);
        $data = $request->validate([
            'nome' => 'required|string|max:150',
            'nome_fantasia' => 'nullable|string|max:100',
            'cnpj' => 'nullable|string|max:20',
            'validade' => 'nullable|date',
            'dominio' => 'nullable|string|max:120|unique:empresas,dominio',
            'modulos_ativos' => 'nullable|array',
            'modulos_ativos.*' => 'nullable|string|max:20',
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
            'dominio' => $data['dominio'] ?? null,
            'validade' => $data['validade'] ?? null,
            'recursos' => ['print_agent'],
            'config' => [
                'modulos_ativos' => array_values(array_intersect(
                    array_keys(\App\Support\Modulos::LISTA),
                    (array) $request->input('modulos_ativos', [])
                )),
                'usa_revendedores' => $request->boolean('usa_revendedores'),
                'revendedor_multi' => $request->boolean('revendedor_multi'),
                'usa_desconto' => $request->boolean('usa_desconto'),
            ],
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

    /** Define/atualiza o domínio próprio de uma empresa (ex.: ajatopassagem.com.br). */
    public function dominio(Request $request, Empresa $empresa)
    {
        $request->merge(['dominio' => $this->normalizarDominio($request->input('dominio'))]);
        $request->validate(['dominio' => 'nullable|string|max:120|unique:empresas,dominio,'.$empresa->id]);
        $empresa->update(['dominio' => $request->input('dominio')]);

        return back()->with('ok', $request->input('dominio')
            ? "Domínio de {$empresa->nomeExibicao()}: {$request->input('dominio')}"
            : "Domínio de {$empresa->nomeExibicao()} removido.");
    }

    /** Deixa só o host: tira http(s)://, caminho e www. */
    protected function normalizarDominio(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') return null;
        $v = preg_replace('#^https?://#i', '', $v);
        $v = preg_replace('#/.*$#', '', $v);
        $v = preg_replace('/^www\./i', '', $v);
        $v = strtolower(trim($v));
        return $v !== '' ? $v : null;
    }

    public function toggle(Empresa $empresa)
    {
        $empresa->update(['ativo' => ! $empresa->ativo]);
        return back()->with('ok', $empresa->ativo ? 'Empresa ativada.' : 'Empresa desativada.');
    }

    /** Edição rápida dos dados básicos de uma empresa (pelo painel super). */
    public function editarSuper(Request $request, Empresa $empresa)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:150',
            'nome_fantasia' => 'nullable|string|max:100',
            'cnpj' => 'nullable|string|max:20',
            'modulos_ativos' => 'nullable|array',
            'modulos_ativos.*' => 'nullable|string|max:20',
        ]);
        $config = $empresa->config ?? [];
        $config['modulos_ativos'] = array_values(array_intersect(
            array_keys(\App\Support\Modulos::LISTA),
            (array) $request->input('modulos_ativos', [])
        ));
        $config['usa_revendedores'] = $request->boolean('usa_revendedores');
        $config['revendedor_multi'] = $request->boolean('revendedor_multi');
        $config['usa_desconto'] = $request->boolean('usa_desconto');
        $data['config'] = $config;
        unset($data['modulos_ativos']);
        $empresa->update($data);

        return back()->with('ok', "Dados de {$empresa->nomeExibicao()} atualizados.");
    }

    /** Exclui uma empresa (só sem movimento). Remove a empresa e seus usuários. */
    public function excluir(Request $request, Empresa $empresa)
    {
        $u = auth()->user();
        if ($empresa->id === $u->empresa_id) {
            return back()->withErrors(['excluir' => 'Você não pode excluir a sua própria empresa.']);
        }
        if ((int) $request->session()->get('empresa_ativa') === $empresa->id) {
            return back()->withErrors(['excluir' => 'Saia da empresa (botão "sair da empresa") antes de excluí-la.']);
        }
        $temEncomenda = $empresa->encomendas()->exists();
        $temPassagem = \App\Models\Passagem::withoutGlobalScopes()->where('empresa_id', $empresa->id)->exists();
        if ($temEncomenda || $temPassagem) {
            return back()->withErrors(['excluir' => 'Essa empresa tem movimento (encomendas/passagens). Use "Bloquear" em vez de excluir.']);
        }

        $nome = $empresa->nomeExibicao();
        \Illuminate\Support\Facades\DB::transaction(function () use ($empresa) {
            User::where('empresa_id', $empresa->id)->delete();
            $empresa->delete();
        });

        return back()->with('ok', "Empresa {$nome} excluída.");
    }

    public function entrar(Request $request, Empresa $empresa)
    {
        $request->session()->put('empresa_ativa', $empresa->id);
        return redirect()->route('encomendas.index')->with('ok', "Você está operando como {$empresa->nomeExibicao()}.");
    }

    /** Entra na empresa e abre direto a tela de Usuários (permissões), pelo painel super. */
    public function entrarUsuarios(Request $request, Empresa $empresa)
    {
        $request->session()->put('empresa_ativa', $empresa->id);
        return redirect()->route('admin.usuarios.index')->with('ok', "Permissões de usuários de {$empresa->nomeExibicao()}.");
    }

    public function sair(Request $request)
    {
        $request->session()->forget('empresa_ativa');
        return redirect()->route('super.empresas');
    }
}
