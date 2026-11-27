<?php

namespace App\Http\Controllers;

use App\Models\Agente;
use App\Models\AtendTela;
use App\Models\User;
use App\Models\WhatsappConexao;
use App\Support\Tenant;
use Illuminate\Http\Request;

/** Atendimento — cadastro das telas de atendimento (só administrador). */
class AtendTelaController extends Controller
{
    protected function soAdmin(): void
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isSuper(), 403, 'Só o administrador configura as telas de atendimento.');
    }

    public function index()
    {
        $this->soAdmin();
        $migrado = AtendTela::pronta();
        return view('atendimento.telas', [
            'migrado' => $migrado,
            'telas' => $migrado ? AtendTela::orderBy('nome')->get() : collect(),
            'conexoes' => WhatsappConexao::orderBy('id')->get(),
            'agentes' => Agente::orderBy('nome')->get(['id', 'nome', 'ativo']),
            'usuarios' => User::where('empresa_id', Tenant::id())->where(fn ($q) => $q->where('is_revendedor', false)->orWhereNull('is_revendedor'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function salvar(Request $request, ?AtendTela $tela = null)
    {
        $this->soAdmin();
        abort_unless(AtendTela::pronta(), 422, 'Rode php artisan migrate --force.');
        $data = $request->validate([
            'nome' => 'required|string|max:60',
            'conexao_ids' => 'nullable|array', 'conexao_ids.*' => 'integer',
            'atende' => 'required|in:ia,operadores',
            'agentes_modo' => 'nullable|in:todos,sel',
            'agente_ids' => 'nullable|array', 'agente_ids.*' => 'integer',
            'operadores_modo' => 'required|in:todos,sel',
            'user_ids' => 'nullable|array', 'user_ids.*' => 'integer',
        ]);
        // só aceita ids que são desta empresa
        $cx = WhatsappConexao::whereIn('id', $data['conexao_ids'] ?? [])->pluck('id')->map(fn ($i) => (int) $i)->all();
        $ag = Agente::whereIn('id', $data['agente_ids'] ?? [])->pluck('id')->map(fn ($i) => (int) $i)->all();
        $us = User::where('empresa_id', Tenant::id())->whereIn('id', $data['user_ids'] ?? [])->pluck('id')->map(fn ($i) => (int) $i)->all();

        $payload = [
            'nome' => trim($data['nome']),
            'conexao_ids' => $cx,
            'usa_ia' => $data['atende'] === 'ia',
            'agente_ids' => ($data['atende'] === 'ia' && ($data['agentes_modo'] ?? 'todos') === 'sel') ? $ag : [],
            'operadores_todos' => $data['operadores_modo'] === 'todos',
            'user_ids' => $data['operadores_modo'] === 'sel' ? $us : [],
            'ativo' => true,
        ];

        if ($tela && $tela->exists) {
            $tela->update($payload);
        } else {
            $tela = AtendTela::create($payload + ['empresa_id' => Tenant::id()]);
        }
        // um número só pode estar em UMA tela: sai das outras
        if ($cx) {
            foreach (AtendTela::where('id', '!=', $tela->id)->get() as $o) {
                $resto = array_values(array_diff($o->conexoes(), $cx));
                if (count($resto) !== count($o->conexoes())) $o->update(['conexao_ids' => $resto]);
            }
        }
        return back()->with('ok', 'Tela "'.$tela->nome.'" salva.'.($cx ? '' : ' Atenção: sem número de WhatsApp ela não recebe conversa nenhuma.'));
    }

    public function excluir(AtendTela $tela)
    {
        $this->soAdmin();
        $nome = $tela->nome;
        $tela->delete();
        return back()->with('ok', 'Tela "'.$nome.'" removida. As conversas dos números dela voltaram pra tela Padrão.');
    }
}
