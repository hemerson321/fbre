<?php

namespace App\Http\Controllers;

use App\Models\Embarcacao;
use App\Models\Orcamento;
use App\Models\Passagem;
use App\Models\Rota;
use App\Models\TipoPassageiro;
use App\Models\TrechoPreco;
use App\Models\Viagem;
use App\Support\Tenant;
use Illuminate\Http\Request;

/**
 * Orçamentos de passagens (Ferramentas): monta o orçamento, gera PDF ou compartilha no WhatsApp.
 * Não persiste nada — é só geração do documento.
 */
class OrcamentoController extends Controller
{
    public function index()
    {
        try {
        $empresa = Tenant::empresa();
        $localidades = TrechoPreco::where('ativo', true)->get(['origem', 'destino'])
            ->flatMap(fn ($t) => [$t->origem, $t->destino])
            ->merge(Rota::todasLocalidades())
            ->filter()->unique()->sort()->values();
        $embarcacoes = Embarcacao::where('ativo', true)->orderBy('nome')->get(['id', 'nome']);
        // Tipos com todos os campos de preço (igual ao cadastro de passagem).
        $colBase = ['id', 'nome', 'percentual', 'pct_sobre', 'paga_taxa', 'modo', 'eh_normal', 'tarifa_padrao', 'alimentacao_padrao'];
        $existentes = \Illuminate\Support\Facades\Schema::getColumnListing('tipos_passageiro');
        $cols = array_values(array_intersect($colBase, $existentes)) ?: ['id', 'nome', 'percentual', 'paga_taxa', 'modo', 'eh_normal'];
        $tipos = TipoPassageiro::where('ativo', true)->orderBy('eh_normal', 'desc')->orderBy('nome')->get($cols);
        $trechoPrecos = TrechoPreco::where('ativo', true)->get(['origem', 'destino', 'tipo_passageiro_id', 'tarifa', 'alimentacao', 'integral']);
        $taxas = \App\Models\TaxaEmbarque::where('ativo', true)->get(['origem', 'destino', 'taxa']);
        $usaAlim = (bool) $empresa->config('usa_alimentacao', true);
        $usaEmbarque = (bool) $empresa->config('usa_taxa_embarque', false);

        $embNomes = Embarcacao::pluck('nome', 'id');

        // Linhas (rota -> paradas em ordem -> transporte) p/ resolver o transporte por origem/destino.
        $linhas = Rota::paraSelecao()->map(fn ($r) => [
            'emb'     => (int) $r->embarcacao_id,
            'nome'    => (string) ($embNomes[$r->embarcacao_id] ?? ''),
            'paradas' => $r->paradas->pluck('localidade')->filter()->values()->all(),
        ])->filter(fn ($l) => $l['emb'] > 0 && count($l['paradas']) >= 2)->values();

        // Viagens PRÉ-PROGRAMADAS (cadastro de viagens), por transporte — próximas e ativas.
        $datasPorEmb = Viagem::where('empresa_id', Tenant::id())
            ->whereIn('status', ['planejada', 'em_andamento'])
            ->whereNotNull('data_partida')
            ->whereDate('data_partida', '>=', '2000-01-01')
            ->with('rota:id,nome')
            ->orderBy('data_partida')
            ->get(['id', 'embarcacao_id', 'data_partida', 'nome', 'rota_id'])
            ->groupBy('embarcacao_id')
            ->map(fn ($g) => $g->map(fn ($v) => [
                'd'   => \Illuminate\Support\Carbon::parse($v->data_partida)->toDateString(),
                'rot' => (string) ($v->nome ?: ($v->rota->nome ?? '')),
            ])->unique('d')->values()->all())
            ->toArray();

        $orcList = \Illuminate\Support\Facades\Schema::hasTable('orcamentos')
            ? Orcamento::where('empresa_id', Tenant::id())->with('user:id,name')->orderByDesc('id')->limit(50)->get()->map(fn ($o) => $this->orcJson($o))->values()
            : collect();

        return response(view('orcamentos.index', compact('empresa', 'localidades', 'embarcacoes', 'tipos', 'trechoPrecos', 'taxas', 'usaAlim', 'usaEmbarque', 'linhas', 'datasPorEmb', 'orcList'))->render());
        } catch (\Throwable $e) {
            report($e);
            if (auth()->user()?->admin) {
                return response('Erro no orçamento (builder): '.class_basename($e).': '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine(), 500);
            }
            throw $e;
        }
    }

    public function gerar(Request $request)
    {
        try {
        $empresa = Tenant::empresa();
        $logo = ($empresa->logo && is_file(public_path($empresa->logo))) ? asset($empresa->logo) : asset('img/ajato-logo.png');

        if ($request->filled('id')) {
            $o = \Illuminate\Support\Facades\Schema::hasTable('orcamentos') ? Orcamento::where('empresa_id', Tenant::id())->find($request->input('id')) : null;
            if ($o) {
                $itensO = array_map(fn ($it) => ['d' => $it['d'] ?? 'Passagem', 'q' => (int) ($it['q'] ?? 0), 'u' => (float) ($it['u'] ?? 0), 't' => round(((int) ($it['q'] ?? 0)) * ((float) ($it['u'] ?? 0)), 2)], (array) ($o->itens ?: []));
                return response(view('docs.orcamento', [
                    'empresa' => $empresa, 'logo' => $logo,
                    'cliente' => $o->cliente ?: 'Cliente', 'doc' => (string) $o->documento,
                    'origem' => $o->origem ?: '—', 'destino' => $o->destino ?: '—',
                    'dataViagem' => $o->data_viagem?->toDateString(),
                    'emb' => (string) $o->embarcacao_nome,
                    'itens' => $itensO, 'total' => (float) $o->total, 'pax' => (int) $o->pax,
                    'num' => str_pad((string) ($o->numero ?: $o->id), 4, '0', STR_PAD_LEFT),
                ])->render());
            }
        }

        $itens = json_decode((string) $request->query('itens', '[]'), true);
        if (! is_array($itens)) $itens = [];
        $itens = array_values(array_filter(array_map(function ($it) {
            $d = trim((string) ($it['d'] ?? ''));
            $q = (int) ($it['q'] ?? 0);
            $u = (float) ($it['u'] ?? 0);
            if ($q <= 0) return null;
            return ['d' => $d !== '' ? $d : 'Passagem', 'q' => $q, 'u' => $u, 't' => round($q * $u, 2)];
        }, $itens)));

        $total = round(array_sum(array_column($itens, 't')), 2);
        $pax = (int) array_sum(array_column($itens, 'q'));
        $num = preg_replace('/[^0-9A-Za-z-]/', '', (string) $request->query('num')) ?: date('ymdHi');

        return response(view('docs.orcamento', [
            'empresa'    => $empresa,
            'logo'       => $logo,
            'cliente'    => trim((string) $request->query('cli')) ?: 'Cliente',
            'doc'        => trim((string) $request->query('doc')),
            'origem'     => trim((string) $request->query('org')) ?: '—',
            'destino'    => trim((string) $request->query('dst')) ?: '—',
            'dataViagem' => $request->query('data'),
            'emb'        => trim((string) $request->query('emb')),
            'itens'      => $itens,
            'total'      => $total,
            'pax'        => $pax,
            'num'        => $num,
        ])->render());
        } catch (\Throwable $e) {
            report($e);
            if (auth()->user()?->admin) {
                return response('Erro no orçamento (PDF): '.class_basename($e).': '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine(), 500);
            }
            throw $e;
        }
    }

    public function salvar(Request $request)
    {
        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('orcamentos'), 503, 'Tabela de orçamentos ainda não migrada.');
        $itens = $this->normalizaItens($request->input('itens'));
        if (empty($itens)) {
            return response()->json(['ok' => false, 'erro' => 'Adicione ao menos um passageiro.'], 422);
        }
        $total = round(array_sum(array_column($itens, 't')), 2);
        $pax = (int) array_sum(array_column($itens, 'q'));
        $numero = (int) Orcamento::where('empresa_id', Tenant::id())->max('numero') + 1;
        $o = Orcamento::create([
            'empresa_id' => Tenant::id(),
            'numero' => $numero,
            'cliente' => trim((string) $request->input('cli')) ?: 'Cliente',
            'documento' => trim((string) $request->input('doc')) ?: null,
            'origem' => trim((string) $request->input('org')) ?: null,
            'destino' => trim((string) $request->input('dst')) ?: null,
            'data_viagem' => $request->input('data') ?: null,
            'embarcacao_id' => ((int) $request->input('emb_id')) ?: null,
            'embarcacao_nome' => trim((string) $request->input('emb')) ?: null,
            'itens' => $itens,
            'total' => $total,
            'pax' => $pax,
            'user_id' => auth()->id(),
        ]);
        $o->load('user:id,name');
        return response()->json(['ok' => true, 'orcamento' => $this->orcJson($o)]);
    }

    public function editar(Orcamento $orcamento)
    {
        abort_unless($orcamento->empresa_id === Tenant::id(), 404);
        $orcamento->load('user:id,name');
        return response()->json(['ok' => true, 'orcamento' => $this->orcJson($orcamento)]);
    }

    public function excluir(Orcamento $orcamento)
    {
        abort_unless($orcamento->empresa_id === Tenant::id(), 404);
        $orcamento->delete();
        return response()->json(['ok' => true]);
    }

    protected function normalizaItens($raw): array
    {
        $itens = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($itens)) $itens = [];
        return array_values(array_filter(array_map(function ($it) {
            $q = (int) ($it['q'] ?? 0);
            if ($q <= 0) return null;
            $u = (float) ($it['u'] ?? 0);
            $d = trim((string) ($it['d'] ?? ''));
            return ['d' => $d !== '' ? $d : 'Passagem', 'q' => $q, 'u' => $u, 't' => round($q * $u, 2), 'tipo' => $it['tipo'] ?? null];
        }, $itens)));
    }

    /** Nome mostrado na lista quando o orçamento foi criado pelo agente de IA (ex.: "🤖 Agente IA · Ana"). */
    protected function nomeAgente(): string
    {
        static $nome = null;
        if ($nome === null) {
            try {
                $n = \App\Models\Agente::where('modulo', 'passagem')->orderByDesc('ativo')->orderBy('id')->value('nome')
                    ?? \App\Models\Agente::orderByDesc('ativo')->orderBy('id')->value('nome');
            } catch (\Throwable $e) { $n = null; }
            $nome = '🤖 Agente IA'.($n ? ' · '.$n : '');
        }
        return $nome;
    }

    protected function orcJson(Orcamento $o): array
    {
        return [
            'id' => $o->id,
            'numero' => str_pad((string) ($o->numero ?: $o->id), 4, '0', STR_PAD_LEFT),
            'cliente' => $o->cliente,
            'documento' => $o->documento,
            'origem' => $o->origem,
            'destino' => $o->destino,
            'data' => $o->data_viagem?->toDateString(),
            'emb' => $o->embarcacao_nome,
            'emb_id' => $o->embarcacao_id,
            'total' => (float) $o->total,
            'pax' => (int) $o->pax,
            'operador' => $o->user?->name ?? (($o->conversa_id ?? null) ? $this->nomeAgente() : '—'),
            'quando' => $o->created_at?->format('d/m/Y H:i'),
            'itens' => $o->itens ?: [],
        ];
    }
}
