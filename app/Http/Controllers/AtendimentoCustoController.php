<?php

namespace App\Http\Controllers;

use App\Models\AgenteUso;
use App\Models\Conversa;
use App\Support\IaPrecos;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/** Atendimento — Custo da IA (só administrador): tokens e R$ por conversa + simulação mensal. */
class AtendimentoCustoController extends Controller
{
    public const SIMULAR = [300, 500, 1000];

    protected function soAdmin(): void
    {
        abort_unless(auth()->user()->podeCustoIa(), 403, 'Seu usuário não tem a permissão "Custo da IA".');
    }

    public function index(Request $request)
    {
        $this->soAdmin();
        $empresa = Tenant::empresa();
        $cfg = (array) ($empresa?->config('ia_custos') ?? []);
        $dolar = (float) ($cfg['dolar'] ?? 0) > 0 ? (float) $cfg['dolar'] : IaPrecos::DOLAR_PADRAO;
        $ajustes = (array) ($cfg['precos'] ?? []);

        $dias = in_array((int) $request->query('dias'), [7, 30, 90], true) ? (int) $request->query('dias') : 30;
        $ini = now()->subDays($dias)->startOfDay();
        $migrado = Schema::hasTable('agente_usos');

        $porConversa = [];
        $modelos = [];
        if ($migrado) {
            $linhas = AgenteUso::where('created_at', '>=', $ini)->whereNotNull('conversa_id')
                ->selectRaw('conversa_id, modelo, COUNT(*) as chamadas, SUM(tok_in) as tin, SUM(tok_out) as tout, SUM(tok_cache_r) as tcr, SUM(tok_cache_w) as tcw, MIN(created_at) as ini, MAX(created_at) as fim')
                ->groupBy('conversa_id', 'modelo')->get();

            foreach ($linhas as $l) {
                $modelo = (string) $l->modelo;
                $preco = IaPrecos::de($modelo, $ajustes);
                $tok = ['in' => (int) $l->tin, 'out' => (int) $l->tout, 'cache_r' => (int) $l->tcr, 'cache_w' => (int) $l->tcw];
                $usd = IaPrecos::custoUsd($tok, $preco);

                $m = $modelos[$modelo] ?? ['modelo' => $modelo, 'preco' => $preco, 'chamadas' => 0, 'tokens' => 0, 'usd' => 0.0];
                $m['chamadas'] += (int) $l->chamadas;
                $m['tokens'] += array_sum($tok);
                $m['usd'] += $usd;
                $modelos[$modelo] = $m;

                $c = $porConversa[$l->conversa_id] ?? ['id' => (int) $l->conversa_id, 'chamadas' => 0, 'in' => 0, 'out' => 0, 'cache' => 0, 'usd' => 0.0, 'modelos' => [], 'ini' => null, 'fim' => null, 'semPreco' => false];
                $c['chamadas'] += (int) $l->chamadas;
                $c['in'] += $tok['in'] + $tok['cache_w'];
                $c['cache'] += $tok['cache_r'];
                $c['out'] += $tok['out'];
                $c['usd'] += $usd;
                $c['modelos'][] = $modelo;
                if (! $preco['conhecido']) $c['semPreco'] = true;
                if ($c['ini'] === null || (string) $l->ini < $c['ini']) $c['ini'] = (string) $l->ini;
                if ($c['fim'] === null || (string) $l->fim > $c['fim']) $c['fim'] = (string) $l->fim;
                $porConversa[$l->conversa_id] = $c;
            }
        }

        $convs = $porConversa ? Conversa::whereIn('id', array_keys($porConversa))->get()->keyBy('id') : collect();
        $lista = [];
        foreach ($porConversa as $id => $c) {
            $cv = $convs[$id] ?? null;
            if (! $cv) continue; // conversa de outra empresa ou apagada
            $demo = (bool) (($cv->contexto['demo'] ?? false)) || str_starts_with((string) $cv->cliente_telefone, 'demo-');
            $brl = $c['usd'] * $dolar;
            $lista[] = [
                'id' => $id,
                'nome' => $demo ? 'Conversa de teste (demo)' : ($cv->cliente_nome ?: 'Sem nome'),
                'telefone' => $demo ? '' : (string) $cv->cliente_telefone,
                'demo' => $demo,
                'status' => (string) $cv->status,
                'chamadas' => $c['chamadas'],
                'in' => $c['in'], 'out' => $c['out'], 'cache' => $c['cache'],
                'tokens' => $c['in'] + $c['out'] + $c['cache'],
                'usd' => $c['usd'], 'brl' => $brl,
                'modelos' => array_values(array_unique($c['modelos'])),
                'semPreco' => $c['semPreco'],
                'fim' => $c['fim'] ? Carbon::parse($c['fim'])->format('d/m H:i') : '',
                'fimOrd' => (string) $c['fim'],
                'sim' => array_map(fn ($n) => $brl * $n, self::SIMULAR),
            ];
        }
        usort($lista, fn ($a, $b) => strcmp($b['fimOrd'], $a['fimOrd']));

        // média: só conversas reais (se não houver nenhuma, usa as de teste pra já dar uma noção)
        $reais = array_values(array_filter($lista, fn ($c) => ! $c['demo']));
        $base = $reais ?: $lista;
        $n = count($base);
        $totalBrl = array_sum(array_column($base, 'brl'));
        $media = $n ? $totalBrl / $n : 0.0;
        $maior = $n ? max(array_column($base, 'brl')) : 0.0;
        $resumo = [
            'conversas' => $n,
            'soTeste' => ! $reais && $lista,
            'tokens' => array_sum(array_column($base, 'tokens')),
            'totalBrl' => $totalBrl,
            'media' => $media,
            'maior' => $maior,
            'simMedia' => array_map(fn ($q) => $media * $q, self::SIMULAR),
            'simMaior' => array_map(fn ($q) => $maior * $q, self::SIMULAR),
        ];

        return view('atendimento.custos', [
            'lista' => array_slice($lista, 0, 300),
            'resumo' => $resumo,
            'modelos' => array_values($modelos),
            'dolar' => $dolar,
            'dias' => $dias,
            'migrado' => $migrado,
            'simular' => self::SIMULAR,
        ]);
    }

    /** Zera as medições de tokens da empresa (pra começar um teste do zero). */
    public function zerar()
    {
        $this->soAdmin();
        abort_unless(Tenant::id(), 404);
        $n = Schema::hasTable('agente_usos') ? AgenteUso::where('empresa_id', Tenant::id())->delete() : 0;
        return back()->with('ok', 'Medições zeradas ('.$n.' registros apagados). A contagem recomeça na próxima resposta do agente.');
    }

    /** Salva a cotação do dólar e os preços corrigidos por modelo. */
    public function salvar(Request $request)
    {
        $this->soAdmin();
        $empresa = Tenant::empresa();
        abort_unless($empresa, 404);
        $num = fn ($v) => round((float) str_replace(',', '.', preg_replace('/[^\d.,]/', '', (string) $v)), 4);

        $precos = [];
        foreach ((array) $request->input('precos', []) as $modelo => $p) {
            $in = $num($p['in'] ?? 0); $out = $num($p['out'] ?? 0);
            if ($in > 0 || $out > 0) $precos[mb_substr((string) $modelo, 0, 80)] = ['in' => $in, 'out' => $out];
        }
        $dolar = $num($request->input('dolar'));

        $cfg = $empresa->config ?? [];
        $cfg['ia_custos'] = ['dolar' => $dolar > 0 ? $dolar : IaPrecos::DOLAR_PADRAO, 'precos' => $precos];
        $empresa->forceFill(['config' => $cfg])->save();

        return back()->with('ok', 'Cotação e preços atualizados.');
    }
}
