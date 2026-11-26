<?php

namespace App\Http\Controllers;

use App\Models\Embarcacao;
use App\Models\Passagem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Dashboard de viagens (Passagens › Financeiro): quem mais viaja — lanchas, rotas, cidades e passageiros.
 * Base: DATA DA VIAGEM das passagens válidas (não canceladas / não transferidas); bebê de colo não conta.
 */
class DashboardViagensController extends Controller
{
    public function index(Request $request)
    {
        $u = auth()->user();
        $periodos = ['30d' => 'Últimos 30 dias', '3m' => '3 meses', '6m' => '6 meses', '12m' => '12 meses', 'ano' => 'Este ano'];
        $periodo = (string) $request->input('periodo', '6m');
        $hoje = Carbon::today();
        try {
            if ($request->filled('inicio') && $request->filled('fim')) {
                $ini = Carbon::parse($request->input('inicio'))->startOfDay();
                $fim = Carbon::parse($request->input('fim'))->endOfDay();
                $periodo = 'custom';
            } else {
                if (! isset($periodos[$periodo])) $periodo = '6m';
                $ini = match ($periodo) {
                    '30d' => $hoje->copy()->subDays(29),
                    '3m' => $hoje->copy()->subMonthsNoOverflow(3)->addDay(),
                    '12m' => $hoje->copy()->subMonthsNoOverflow(12)->addDay(),
                    'ano' => $hoje->copy()->startOfYear(),
                    default => $hoje->copy()->subMonthsNoOverflow(6)->addDay(),
                };
                $fim = $hoje->copy()->endOfDay();
            }
        } catch (\Throwable $e) {
            $periodo = '6m'; $ini = $hoje->copy()->subMonthsNoOverflow(6)->addDay(); $fim = $hoje->copy()->endOfDay();
        }
        if ($ini->gt($fim)) [$ini, $fim] = [$fim->copy()->startOfDay(), $ini->copy()->endOfDay()];

        $transportes = Embarcacao::orderBy('nome')->get(['id', 'nome']);
        $embId = (int) $request->input('transporte_id') ?: null;
        if ($embId && ! $transportes->firstWhere('id', $embId)) $embId = null;
        $emb = $embId ? $transportes->firstWhere('id', $embId) : null;

        $passagens = Passagem::where('cancelada', false)->where('transferida', false)
            ->whereNotNull('data_viagem')
            ->whereDate('data_viagem', '>=', $ini->toDateString())
            ->whereDate('data_viagem', '<=', $fim->toDateString())
            ->when($embId, fn ($q) => $q->where('embarcacao_id', $embId))
            ->when(! $u->relatorioTodos(), fn ($q) => $q->where('user_id', $u->id))
            ->with(['passageiros:id,passagem_id,nome,documento,papel,cancelado'])
            ->get(['id', 'embarcacao_id', 'origem', 'destino', 'data_viagem', 'total']);

        $nomeEmb = $transportes->pluck('nome', 'id');
        $cid = fn ($c) => mb_convert_case(mb_strtolower(trim((string) $c)), MB_CASE_TITLE, 'UTF-8');
        $meses = ['', 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
        $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

        $totPax = 0; $receita = 0.0;
        $viagensSet = []; $lancha = []; $rotas = []; $destinos = []; $porMes = []; $porDia = array_fill(0, 7, 0);
        $pessoas = []; $viagens = [];

        foreach ($passagens as $p) {
            $pax = $p->passageiros->filter(fn ($x) => ! $x->cancelado && ($x->papel ?? '') !== 'colo');
            $n = $pax->count();
            if ($n === 0) continue;
            $d = Carbon::parse($p->data_viagem);
            $dia = $d->toDateString();
            $eid = (int) $p->embarcacao_id;
            $vk = $eid.'|'.$dia;
            $viagensSet[$vk] = true;
            $totPax += $n; $receita += (float) $p->total;

            $ln = $eid ? (string) ($nomeEmb[$eid] ?? 'Transporte '.$eid) : 'Sem transporte';
            $lancha[$ln] ??= ['pax' => 0, 'v' => []];
            $lancha[$ln]['pax'] += $n; $lancha[$ln]['v'][$dia] = true;

            $o = $cid($p->origem); $de = $cid($p->destino);
            if ($o !== '' && $de !== '') { $rk = $o.' → '.$de; $rotas[$rk] = ($rotas[$rk] ?? 0) + $n; }
            if ($de !== '') $destinos[$de] = ($destinos[$de] ?? 0) + $n;

            $mk = $d->format('Y-m');
            $porMes[$mk] ??= ['pax' => 0, 'v' => []];
            $porMes[$mk]['pax'] += $n; $porMes[$mk]['v'][$vk] = true;
            $porDia[(int) $d->dayOfWeek] += $n;

            $viagens[$vk] ??= ['data' => $dia, 'lancha' => $ln, 'pax' => 0, 'pass' => 0, 'receita' => 0.0];
            $viagens[$vk]['pax'] += $n; $viagens[$vk]['pass']++; $viagens[$vk]['receita'] += (float) $p->total;

            foreach ($pax as $x) {
                $nome = trim((string) $x->nome);
                if ($nome === '') continue;
                $doc = preg_replace('/\D+/', '', (string) $x->documento);
                $pk = strlen($doc) >= 5 ? 'd'.$doc : 'n'.mb_strtoupper(preg_replace('/\s+/', ' ', $nome));
                $pessoas[$pk] ??= ['nome' => mb_strtoupper($nome), 'qtd' => 0, 'ultima' => $dia];
                $pessoas[$pk]['qtd']++;
                if ($dia > $pessoas[$pk]['ultima']) $pessoas[$pk]['ultima'] = $dia;
            }
        }

        $nViagens = count($viagensSet);

        // Lanchas (ordenadas por nº de viagens, depois passageiros)
        $lanchas = collect($lancha)->map(fn ($v, $k) => ['nome' => $k, 'viagens' => count($v['v']), 'pax' => $v['pax']])
            ->sort(fn ($a, $b) => [$b['viagens'], $b['pax']] <=> [$a['viagens'], $a['pax']])->values()->take(10);

        // Pizza das rotas: top 6 + "Outras"
        arsort($rotas);
        $rotasTop = array_slice($rotas, 0, 6, true);
        $outras = array_sum($rotas) - array_sum($rotasTop);
        if ($outras > 0) $rotasTop['Outras rotas'] = $outras;

        arsort($destinos);
        $destinosTop = array_slice($destinos, 0, 8, true);

        ksort($porMes);
        $mesLabels = []; $mesPax = []; $mesViagens = [];
        foreach ($porMes as $k => $v) {
            [$y, $m] = explode('-', $k);
            $mesLabels[] = $meses[(int) $m].'/'.substr($y, 2); $mesPax[] = $v['pax']; $mesViagens[] = count($v['v']);
        }

        $topPessoas = collect($pessoas)->sortByDesc('qtd')->take(10)->values();
        $topViagens = collect($viagens)->sort(fn ($a, $b) => [$b['pax'], $b['pass']] <=> [$a['pax'], $a['pass']])->take(10)->map(fn ($v) => ['data' => $v['data'], 'lancha' => $v['lancha'], 'pax' => $v['pax'], 'pass' => $v['pass']])->values();

        $melhorMes = null;
        if ($mesPax) { $i = array_search(max($mesPax), $mesPax, true); $melhorMes = ['label' => $mesLabels[$i], 'pax' => $mesPax[$i], 'viagens' => $mesViagens[$i]]; }
        $diaTop = $totPax ? $dias[array_search(max($porDia), $porDia, true)] : null;

        $graf = [
            'lanchas' => ['labels' => $lanchas->pluck('nome'), 'viagens' => $lanchas->pluck('viagens'), 'pax' => $lanchas->pluck('pax')],
            'rotas' => ['labels' => array_keys($rotasTop), 'valores' => array_values($rotasTop)],
            'destinos' => ['labels' => array_keys($destinosTop), 'valores' => array_values($destinosTop)],
            'meses' => ['labels' => $mesLabels, 'pax' => $mesPax, 'viagens' => $mesViagens],
            'dias' => ['labels' => $dias, 'valores' => array_values($porDia)],
        ];

        return view('passagens.dashboard', [
            'periodos' => $periodos, 'periodo' => $periodo, 'ini' => $ini, 'fim' => $fim,
            'transportes' => $transportes, 'embId' => $embId, 'emb' => $emb,
            'dv' => [
                'embNome' => $emb?->nome, 'totPax' => $totPax, 'nViagens' => $nViagens, 'nDestinos' => count($destinos), 'nLanchas' => count($lancha),
                'mediaPax' => $nViagens ? round($totPax / $nViagens, 1) : 0,
                'lanchaTop' => $lanchas->first(), 'rotaTop' => $rotas ? ['nome' => array_key_first($rotas), 'pax' => reset($rotas)] : null,
                'destinoTop' => $destinos ? ['nome' => array_key_first($destinos), 'pax' => reset($destinos)] : null,
                'melhorMes' => $melhorMes, 'diaTop' => $diaTop,
                'pessoas' => $topPessoas, 'viagens' => $topViagens, 'graf' => $graf,
            ],
        ]);
    }
}
