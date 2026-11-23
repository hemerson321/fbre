<?php

namespace App\Services;

use App\Models\Embarcacao;
use App\Models\Passagem;
use App\Models\PoltronaBloqueada;
use App\Models\Rota;
use Carbon\Carbon;

/**
 * Calcula quantas vagas (poltronas livres) tem num trecho/data.
 * Reaproveita a mesma lógica de ocupação por segmento usada na venda.
 */
class Vagas
{
    /**
     * @return array{ok:bool, usa_poltrona?:bool, vagas?:?int, capacidade?:int, lancha?:string}
     */
    public function disponiveis(string $origem, string $destino, ?Carbon $data = null): array
    {
        $rota = $this->rotaDoTrecho($origem, $destino);
        if (! $rota || ! $rota->embarcacao) return ['ok' => false];
        $emb = $rota->embarcacao;

        if (! $emb->usa_poltrona) {
            // lancha sem poltrona marcada (rede): não controla assento
            return ['ok' => true, 'usa_poltrona' => false, 'vagas' => null, 'capacidade' => (int) $emb->poltronas_qtd, 'lancha' => (string) $emb->nome];
        }

        $dataStr = $data?->toDateString();
        $ocup = $this->ocupadas($emb->id, $dataStr, $rota->id, $origem, $destino)->count();
        $vagas = max(0, (int) $emb->poltronas_qtd - $ocup);

        return ['ok' => true, 'usa_poltrona' => true, 'vagas' => $vagas, 'capacidade' => (int) $emb->poltronas_qtd, 'lancha' => (string) $emb->nome];
    }

    /**
     * Lugares LIVRES por tipo (janela/meio/corredor) no trecho/data, lendo o mapa real da lancha.
     * @return array{ok:bool, usa_poltrona?:bool, tipos?:?array, lancha?:string}
     */
    public function tiposDisponiveis(string $origem, string $destino, ?Carbon $data = null): array
    {
        $rota = $this->rotaDoTrecho($origem, $destino);
        if (! $rota || ! $rota->embarcacao) return ['ok' => false];
        $emb = $rota->embarcacao;
        if (! $emb->usa_poltrona) return ['ok' => true, 'usa_poltrona' => false, 'tipos' => []];

        $mapaTipo = $this->classificar((string) ($emb->poltronas_lista ?? ''));
        if (! $mapaTipo) return ['ok' => true, 'usa_poltrona' => true, 'tipos' => null, 'lancha' => (string) $emb->nome]; // sem mapa desenhado

        $ocup = $this->ocupadas($emb->id, $data?->toDateString(), $rota->id, $origem, $destino)
            ->map(fn ($x) => mb_strtolower((string) $x))->all();

        $cont = ['janela' => 0, 'meio' => 0, 'corredor' => 0];
        foreach ($mapaTipo as $sel => $tipo) {
            if (in_array(mb_strtolower((string) $sel), $ocup, true)) continue;
            if (isset($cont[$tipo])) $cont[$tipo]++;
        }
        return ['ok' => true, 'usa_poltrona' => true, 'tipos' => $cont, 'lancha' => (string) $emb->nome];
    }

    /** Classifica cada poltrona do layout em janela/meio/corredor pela POSIÇÃO na fileira. */
    protected function classificar(string $layout): array
    {
        $layout = str_replace("\r", '', trim($layout));
        if ($layout === '') return [];
        $map = [];
        foreach (preg_split('/\n[ \t]*\n/', $layout) as $bloco) {
            foreach (preg_split('/\n/', $bloco) as $linha) {
                if (trim($linha) === '') continue;
                $p = explode('|', $linha);
                $esq = array_values(array_filter(preg_split('/\s+/', trim($p[0] ?? '')), fn ($x) => $x !== ''));
                $dir = array_values(array_filter(preg_split('/\s+/', trim($p[1] ?? '')), fn ($x) => $x !== ''));
                $this->classBloco($esq, 'esq', $map);
                $this->classBloco($dir, 'dir', $map);
            }
        }
        return $map;
    }

    protected function classBloco(array $seats, string $lado, array &$map): void
    {
        $n = count($seats);
        foreach ($seats as $i => $sel) {
            if ($n === 1) {
                $t = 'janela';
            } elseif ($lado === 'esq') {
                $t = $i === 0 ? 'janela' : ($i === $n - 1 ? 'corredor' : 'meio');
            } else {
                $t = $i === $n - 1 ? 'janela' : ($i === 0 ? 'corredor' : 'meio');
            }
            $map[$sel] = $t;
        }
    }

    /** Acha a rota ATIVA cujas paradas contêm origem E destino. */
    protected function rotaDoTrecho(string $origem, string $destino): ?Rota
    {
        $o = mb_strtolower(trim($origem));
        $d = mb_strtolower(trim($destino));
        foreach (Rota::with('paradas', 'embarcacao')->where('ativo', true)->get() as $r) {
            $locs = array_map(fn ($x) => mb_strtolower((string) $x), $r->localidades());
            if (in_array($o, $locs, true) && in_array($d, $locs, true)) return $r;
        }
        return null;
    }

    /** Poltronas ocupadas (vendidas + travadas) no trecho/data — mesma regra de segmento da venda. */
    protected function ocupadas($embarcacaoId, ?string $dataViagem, $rotaId, string $origem, string $destino): \Illuminate\Support\Collection
    {
        $q = Passagem::where('embarcacao_id', $embarcacaoId)->where('cancelada', false)
            ->when($dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem));

        $ordem = [];
        if ($rotaId && ($rota = Rota::find($rotaId))) $ordem = array_map('mb_strtolower', $rota->localidades());
        $idx = fn ($cidade) => array_search(mb_strtolower((string) $cidade), $ordem, true);
        $reqS = $idx($origem);
        $reqD = $idx($destino);
        $temTrecho = $ordem && $reqS !== false && $reqD !== false;
        $reqMin = $temTrecho ? min($reqS, $reqD) : null;
        $reqMax = $temTrecho ? max($reqS, $reqD) : null;

        $ocup = collect();
        foreach ($q->with(['passageiros' => fn ($q) => $q->where('cancelado', false)->select('id', 'passagem_id', 'poltrona')])->get(['id', 'origem', 'destino']) as $pg) {
            if ($temTrecho) {
                $c = $idx($pg->origem); $d = $idx($pg->destino);
                if ($c !== false && $d !== false) {
                    [$cMin, $cMax] = [min($c, $d), max($c, $d)];
                    if (! ($reqMin < $cMax && $cMin < $reqMax)) continue; // não cruza o trecho -> livre
                }
            }
            foreach ($pg->passageiros as $pax) if ($pax->poltrona) $ocup->push((string) $pax->poltrona);
        }

        $bq = PoltronaBloqueada::where('embarcacao_id', $embarcacaoId)->where('ativo', true)->aplicaveis('ia');
        if ($dataViagem) $bq->where(fn ($w) => $w->whereNull('data_viagem')->orWhereDate('data_viagem', $dataViagem));
        foreach ($bq->pluck('poltrona') as $pol) if ($pol !== null && $pol !== '') $ocup->push((string) $pol);

        return $ocup->unique()->values();
    }
}
