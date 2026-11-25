<?php

namespace App\Http\Controllers;

use App\Models\Embarcacao;
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

        return view('orcamentos.index', compact('empresa', 'localidades', 'embarcacoes', 'tipos', 'trechoPrecos', 'taxas', 'usaAlim', 'usaEmbarque', 'linhas', 'datasPorEmb'));
    }

    public function gerar(Request $request)
    {
        $empresa = Tenant::empresa();
        $logo = ($empresa->logo && is_file(public_path($empresa->logo))) ? asset($empresa->logo) : asset('img/ajato-logo.png');

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

        return view('docs.orcamento', [
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
        ]);
    }
}
