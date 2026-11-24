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
    public function disponiveis(string $origem, string $destino, ?Carbon $data = null, ?string $lancha = null): array
    {
        $rota = $this->rotaDoTrecho($origem, $destino, $data, $lancha);
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
    public function tiposDisponiveis(string $origem, string $destino, ?Carbon $data = null, ?string $lancha = null): array
    {
        $rota = $this->rotaDoTrecho($origem, $destino, $data, $lancha);
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

    /** Primeira poltrona LIVRE de um tipo (janela/meio/corredor) no trecho/data. Retorna o nome (ex.: 03J) ou null. */
    public function primeiraLivre(string $origem, string $destino, ?Carbon $data, string $tipo, ?string $lancha = null): ?string
    {
        $rota = $this->rotaDoTrecho($origem, $destino, $data, $lancha);
        if (! $rota || ! $rota->embarcacao || ! $rota->embarcacao->usa_poltrona) return null;
        $emb = $rota->embarcacao;
        $mapaTipo = $this->classificar((string) ($emb->poltronas_lista ?? ''));
        if (! $mapaTipo) return null;

        $ocup = $this->ocupadas($emb->id, $data?->toDateString(), $rota->id, $origem, $destino)
            ->map(fn ($x) => mb_strtolower((string) $x))->all();
        $tipo = mb_strtolower(trim($tipo));

        foreach ($mapaTipo as $sel => $t) {
            if ($t === $tipo && ! in_array(mb_strtolower((string) $sel), $ocup, true)) return (string) $sel;
        }
        return null;
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

    /**
     * Atribui e RESERVA (hold) n poltrona(s) de um tipo no trecho/data, pra uma conversa.
     * Para n>1 tenta assentos JUNTOS; se não der, pega os mais próximos e avisa.
     * @return array{ok:bool, seats?:array, juntas?:bool, descricao?:string, esgotado?:bool}
     */
    public function atribuir(string $origem, string $destino, ?Carbon $data, string $tipo, int $n, ?int $conversaId, ?string $lancha = null): array
    {
        $n = max(1, min(8, $n));
        $rota = $this->rotaDoTrecho($origem, $destino, $data, $lancha);
        if (! $rota || ! $rota->embarcacao || ! $rota->embarcacao->usa_poltrona) return ['ok' => false];
        $emb = $rota->embarcacao;
        $blocos = $this->blocosOrdenados((string) ($emb->poltronas_lista ?? ''));
        if (! $blocos) return ['ok' => false];

        $tipo = mb_strtolower(trim($tipo)) ?: 'janela';
        if (! in_array($tipo, ['janela', 'meio', 'corredor'], true)) $tipo = 'janela'; // 'juntos'/'qualquer' = tanto faz; prefere pegar a janela
        $ind = $this->indisponiveis($emb, $rota->id, $data, $origem, $destino, $conversaId);
        $livre = fn ($sel) => ! in_array(mb_strtolower((string) $sel), $ind, true);

        $escolhidos = [];
        $juntas = false;

        if ($n === 1) {
            foreach ($blocos as $bl) foreach ($bl as $sx) if ($sx['tipo'] === $tipo && $livre($sx['sel'])) { $escolhidos = [$sx['sel']]; break 2; }
            if (! $escolhidos) foreach ($blocos as $bl) foreach ($bl as $sx) if ($livre($sx['sel'])) { $escolhidos = [$sx['sel']]; break 2; }
            $juntas = true;
        } else {
            $run = $this->acharRun($blocos, $n, $livre, $tipo);
            if ($run) { $escolhidos = $run; $juntas = true; }
            else {
                // não cabem todos lado a lado: junta o MÁXIMO possível (maiores sequências livres primeiro) e completa com avulsas
                $falta = $n;
                while ($falta > 1) {
                    $livre2 = fn ($sel) => $livre($sel) && ! in_array($sel, $escolhidos, true);
                    $achou = null;
                    for ($k = $falta; $k >= 2; $k--) { $achou = $this->acharRun($blocos, $k, $livre2, $tipo); if ($achou) break; }
                    if (! $achou) break;
                    foreach ($achou as $sel) $escolhidos[] = $sel;
                    $falta = $n - count($escolhidos);
                }
                if (count($escolhidos) < $n) foreach ($blocos as $bl) foreach ($bl as $sx) if ($sx['tipo'] === $tipo && $livre($sx['sel']) && ! in_array($sx['sel'], $escolhidos, true)) { $escolhidos[] = $sx['sel']; if (count($escolhidos) >= $n) break 2; }
                if (count($escolhidos) < $n) foreach ($blocos as $bl) foreach ($bl as $sx) if ($livre($sx['sel']) && ! in_array($sx['sel'], $escolhidos, true)) { $escolhidos[] = $sx['sel']; if (count($escolhidos) >= $n) break 2; }
                $juntas = false;
            }
        }

        if (! $escolhidos) return ['ok' => true, 'esgotado' => true, 'seats' => [], 'descricao' => 'sem poltrona livre'];

        $viagemId = optional($rota->viagemPara($data ?: now()))->id;
        if ($conversaId) \App\Models\PoltronaHold::where('conversa_id', $conversaId)->delete();
        foreach ($escolhidos as $sel) {
            \App\Models\PoltronaHold::create([
                'empresa_id' => $emb->empresa_id, 'embarcacao_id' => $emb->id, 'viagem_id' => $viagemId,
                'data_viagem' => $data?->toDateString(), 'poltrona' => $sel, 'origem' => $origem, 'destino' => $destino,
                'user_id' => 0, 'conversa_id' => $conversaId, 'expires_at' => now()->addMinutes(5),
            ]);
        }

        try { \Illuminate\Support\Facades\Log::info('agente.poltrona.reserva', ['lancha' => $emb->nome, 'embarcacao_id' => $emb->id, 'rota_id' => $rota->id, 'viagem_id' => $viagemId, 'data' => $data?->toDateString(), 'trecho' => $origem.' > '.$destino, 'poltronas' => $escolhidos, 'conversa' => $conversaId]); } catch (\Throwable $e) {}
        $tipoDe = [];
        foreach ($blocos as $bl) foreach ($bl as $sx) $tipoDe[$sx['sel']] = $sx['tipo'];
        $desc = $this->descreverEscolha($escolhidos, $tipoDe, $juntas, $n, $blocos);
        return ['ok' => true, 'seats' => $escolhidos, 'juntas' => $juntas, 'descricao' => $desc];
    }

    /** Solta as reservas (holds) feitas pelo agente nessa conversa. */
    public function liberarConversa(int $conversaId): void
    {
        \App\Models\PoltronaHold::where('conversa_id', $conversaId)->delete();
    }

    /** Renova (estende) as reservas ativas da conversa — chamado a cada mensagem do cliente. Se já expirou, NÃO ressuscita. */
    public function renovarConversa(int $conversaId, int $minutos = 2): void
    {
        \App\Models\PoltronaHold::where('conversa_id', $conversaId)
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()->addMinutes(max(1, $minutos))]);
    }

    /** Poltronas indisponíveis p/ o agente: vendidas + travadas(IA) + holds de operadores/outras conversas. */
    protected function indisponiveis($emb, $rotaId, ?Carbon $data, string $origem, string $destino, ?int $conversaId): array
    {
        $ocup = $this->ocupadas($emb->id, $data?->toDateString(), $rotaId, $origem, $destino)
            ->map(fn ($x) => mb_strtolower((string) $x))->all();
        $hq = \App\Models\PoltronaHold::where('expires_at', '>', now())->where('embarcacao_id', $emb->id)
            ->when($data, fn ($q) => $q->whereDate('data_viagem', $data->toDateString()))
            ->when($conversaId, fn ($q) => $q->where(fn ($w) => $w->whereNull('conversa_id')->orWhere('conversa_id', '!=', $conversaId)));
        foreach ($hq->pluck('poltrona') as $pol) if ($pol !== null && $pol !== '') $ocup[] = mb_strtolower((string) $pol);
        return array_values(array_unique($ocup));
    }

    /** Blocos (lado esq/dir de cada fileira) em ordem, com o tipo de cada assento. */
    protected function blocosOrdenados(string $layout): array
    {
        $layout = str_replace("\r", '', trim($layout));
        if ($layout === '') return [];
        $blocos = [];
        foreach (preg_split('/\n[ \t]*\n/', $layout) as $b) {
            foreach (preg_split('/\n/', $b) as $linha) {
                if (trim($linha) === '') continue;
                $p = explode('|', $linha);
                foreach (['esq', 'dir'] as $lado) {
                    $side = $lado === 'esq' ? ($p[0] ?? '') : ($p[1] ?? '');
                    $seats = array_values(array_filter(preg_split('/\s+/', trim($side)), fn ($x) => $x !== ''));
                    if (! $seats) continue;
                    $cnt = count($seats);
                    $linhaBloco = [];
                    foreach ($seats as $i => $sel) {
                        if ($cnt === 1) $t = 'janela';
                        elseif ($lado === 'esq') $t = $i === 0 ? 'janela' : ($i === $cnt - 1 ? 'corredor' : 'meio');
                        else $t = $i === $cnt - 1 ? 'janela' : ($i === 0 ? 'corredor' : 'meio');
                        $linhaBloco[] = ['sel' => (string) $sel, 'tipo' => $t];
                    }
                    $blocos[] = $linhaBloco;
                }
            }
        }
        return $blocos;
    }

    /** Acha n assentos CONSECUTIVOS livres num mesmo bloco (preferindo um bloco que tenha o tipo pedido). */
    protected function acharRun(array $blocos, int $n, callable $livre, string $tipo): ?array
    {
        $melhor = null;
        foreach ($blocos as $bl) {
            $run = [];
            foreach ($bl as $sx) {
                if ($livre($sx['sel'])) $run[] = $sx;
                else { $melhor = $this->avaliarRun($run, $n, $tipo, $melhor); $run = []; }
            }
            $melhor = $this->avaliarRun($run, $n, $tipo, $melhor);
        }
        if (! $melhor) return null;
        // dentro da sequência livre, escolhe a janela de n assentos que pega o tipo pedido (ex.: 3 juntos pegando a janela)
        $seq = $melhor['seats']; $ini = 0;
        for ($i = 0; $i + $n <= count($seq); $i++) {
            foreach (array_slice($seq, $i, $n) as $sx) if ($sx['tipo'] === $tipo) { $ini = $i; break 2; }
        }
        return array_map(fn ($x) => $x['sel'], array_slice($seq, $ini, $n));
    }

    protected function avaliarRun(array $run, int $n, string $tipo, ?array $melhor): ?array
    {
        if (count($run) < $n) return $melhor;
        $temTipo = false;
        foreach ($run as $sx) if ($sx['tipo'] === $tipo) { $temTipo = true; break; }
        $cand = ['seats' => $run, 'temTipo' => $temTipo];
        if (! $melhor) return $cand;
        if ($temTipo && ! $melhor['temTipo']) return $cand;
        return $melhor;
    }

    protected function descreverEscolha(array $seats, array $tipoDe, bool $juntas, int $n, array $blocos = []): string
    {
        $lista = function (array $xs) { $xs = array_values($xs); if (count($xs) <= 1) return (string) ($xs[0] ?? ''); $u = array_pop($xs); return implode(', ', $xs).' e '.$u; };
        $tipos = fn (array $xs) => implode(', ', array_map(fn ($x) => (string) ($tipoDe[$x] ?? ''), $xs));
        if ($n === 1) return $seats[0].' ('.($tipoDe[$seats[0]] ?? '').')';
        if ($juntas) return $lista($seats).' — as '.$n.' LADO A LADO, uma do lado da outra ('.$tipos($seats).')';

        // não coube todo mundo junto: agrupa o que ficou lado a lado (mesmo bloco, assentos vizinhos)
        $grupos = []; $usados = [];
        foreach ($blocos as $bl) {
            $g = [];
            foreach ($bl as $sx) {
                if (in_array($sx['sel'], $seats, true)) { $g[] = $sx['sel']; $usados[] = $sx['sel']; }
                else { if ($g) $grupos[] = $g; $g = []; }
            }
            if ($g) $grupos[] = $g;
        }
        foreach ($seats as $x) if (! in_array($x, $usados, true)) $grupos[] = [$x];
        usort($grupos, fn ($a, $b) => count($b) <=> count($a));
        $partes = array_map(fn ($g) => count($g) > 1
            ? $lista($g).' lado a lado ('.$tipos($g).')'
            : $g[0].' separada ('.($tipoDe[$g[0]] ?? '').')', $grupos);
        return 'NAO tem '.$n.' lado a lado nessa viagem; ficou assim: '.implode(' + ', $partes);
    }

    /** Acha a rota ATIVA cujas paradas contêm origem E destino. */
    /**
     * Rota (e portanto a LANCHA) que atende o trecho. Várias lanchas podem fazer o mesmo trecho, então
     * desempata por: nome da lancha informado > dia da semana da saída na cidade de origem > sentido da viagem.
     * (Antes pegava sempre a primeira rota do trecho, e o agente só acertava a poltrona de uma lancha.)
     */
    protected function rotaDoTrecho(string $origem, string $destino, ?Carbon $data = null, ?string $lancha = null): ?Rota
    {
        $norm = fn ($x) => preg_replace('/[^a-z0-9]+/', '', mb_strtolower(\Illuminate\Support\Str::ascii(trim((string) $x))));
        $o = mb_strtolower(trim($origem));
        $d = mb_strtolower(trim($destino));
        $ln = $lancha !== null ? $norm($lancha) : '';
        $melhor = null; $melhorPts = -1;
        foreach (Rota::with('paradas', 'embarcacao')->where('ativo', true)->get() as $r) {
            $locs = array_map(fn ($x) => mb_strtolower((string) $x), $r->localidades());
            $io = array_search($o, $locs, true); $id = array_search($d, $locs, true);
            if ($io === false || $id === false) continue;
            $pts = 0;
            if ($ln !== '' && $r->embarcacao) {
                $en = $norm($r->embarcacao->nome);
                if ($en !== '' && ($en === $ln || str_contains($en, $ln) || str_contains($ln, $en))) $pts += 100;
            }
            if ($data) {
                $par = $r->paradas->values()[$io] ?? null;
                if ($par && $par->dia_semana !== null && (int) $par->dia_semana === (int) $data->dayOfWeek) $pts += 10;
            }
            if ($io < $id) $pts += 3;
            if ($pts > $melhorPts) { $melhor = $r; $melhorPts = $pts; }
        }
        return $melhor;
    }

    /** Poltronas ocupadas (vendidas + travadas) no trecho/data — mesma regra de segmento da venda. */
    protected function ocupadas($embarcacaoId, ?string $dataViagem, $rotaId, string $origem, string $destino): \Illuminate\Support\Collection
    {
        // MESMA regra do mapa do operador (PassagemController::ocupadas): em viagem de horário
        // fixo a venda fica amarrada ao viagem_id e o data_viagem pode estar nulo. Se filtrar só
        // por data, o agente NÃO enxerga a venda e revende a poltrona ocupada. Então resolve o
        // viagem_id pela rota+data e filtra igualzinho.
        $rota = $rotaId ? Rota::find($rotaId) : null;
        $viagemId = null; $viagemAberta = false;
        if ($rota && $dataViagem) {
            try { $viagemId = optional($rota->viagemPara(Carbon::parse($dataViagem)))->id; } catch (\Throwable $e) {}
        }
        if ($viagemId) { $vg = \App\Models\Viagem::find($viagemId); $viagemAberta = ! $vg || $vg->data_partida === null; }

        $q = Passagem::where('embarcacao_id', $embarcacaoId)->where('cancelada', false)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when(($viagemAberta || ! $viagemId) && $dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem));

        $ordem = [];
        if ($rota) $ordem = array_map('mb_strtolower', $rota->localidades());
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
