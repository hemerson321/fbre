<?php

namespace App\Http\Controllers;

use App\Models\Embarcacao;
use App\Models\Passagem;
use App\Models\Rota;
use App\Models\TipoPassageiro;
use App\Models\TrechoPreco;
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
        $tipos = TipoPassageiro::where('ativo', true)
            ->orderBy('eh_normal', 'desc')->orderBy('nome')->get(['id', 'nome', 'eh_normal']);
        // Preços por trecho (tarifa + alimentação) para o prefill do valor unitário.
        $precos = TrechoPreco::where('ativo', true)
            ->get(['origem', 'destino', 'tipo_passageiro_id', 'tarifa', 'alimentacao'])
            ->map(fn ($t) => [
                'o' => (string) $t->origem,
                'd' => (string) $t->destino,
                't' => (int) $t->tipo_passageiro_id,
                'v' => round((float) $t->tarifa + (float) $t->alimentacao, 2),
            ])->values();

        $embNomes = Embarcacao::pluck('nome', 'id');

        // Linhas (rota -> paradas em ordem -> transporte) p/ resolver o transporte por origem/destino.
        $linhas = Rota::paraSelecao()->map(fn ($r) => [
            'emb'     => (int) $r->embarcacao_id,
            'nome'    => (string) ($embNomes[$r->embarcacao_id] ?? ''),
            'paradas' => $r->paradas->pluck('localidade')->filter()->values()->all(),
        ])->filter(fn ($l) => $l['emb'] > 0 && count($l['paradas']) >= 2)->values();

        // Datas de viagem já cadastradas no sistema, por transporte (próximas).
        $datasPorEmb = Passagem::where('empresa_id', Tenant::id())
            ->where('cancelada', false)->whereNotNull('data_viagem')
            ->whereDate('data_viagem', '>=', now()->toDateString())
            ->orderBy('data_viagem')->get(['embarcacao_id', 'data_viagem'])
            ->groupBy('embarcacao_id')
            ->map(fn ($g) => $g->pluck('data_viagem')
                ->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->toDateString())
                ->unique()->values()->all())
            ->toArray();

        return view('orcamentos.index', compact('empresa', 'localidades', 'embarcacoes', 'tipos', 'precos', 'linhas', 'datasPorEmb'));
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
