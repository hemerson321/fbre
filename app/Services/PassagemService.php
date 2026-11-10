<?php

namespace App\Services;

use App\Models\Passagem;
use App\Models\Pessoa;
use App\Models\TabelaPreco;
use App\Models\TipoPassageiro;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Criação/baixa de passagem — tela normal e sincronização offline.
 * Idempotente por uuid. Grava pagamentos no caixa unificado (modulo=passagem).
 */
class PassagemService
{
    public function criar(array $data, int $userId, ?string $uuid = null, bool $offline = false): Passagem
    {
        if ($uuid && ($existente = Passagem::where('uuid', $uuid)->first())) {
            return $existente;
        }

        $empresa = Tenant::empresa();

        // viagem a partir da rota + data (igual encomenda)
        $viagemId = $data['viagem_id'] ?? null;
        $rotaId = $data['rota_id'] ?? null;
        if (! $viagemId && $rotaId) {
            $rota = \App\Models\Rota::find($rotaId);
            if ($rota) {
                $dataRef = ! empty($data['data_viagem']) ? \Carbon\Carbon::parse($data['data_viagem'])
                    : (! empty($data['criada_em']) ? \Carbon\Carbon::parse($data['criada_em']) : now());
                $viagemId = $rota->viagemPara($dataRef)->id;
            }
        }

        // Limites de venda do operador/agente (por dia e por viagem) — trava antes de criar.
        $op = \App\Models\User::find($userId);
        if ($op) {
            $novos = count($data['passageiros'] ?? []);
            $saleTotal = round(array_sum(array_map(fn ($px) => (float) ($px['valor_integral'] ?? (($px['valor_passagem'] ?? 0) + ($px['valor_taxa'] ?? 0) + ($px['valor_alimentacao'] ?? 0))), $data['passageiros'] ?? [])) - (float) ($data['desconto'] ?? 0), 2);
            if ($op->limite_passagens_dia) {
                $qd = (int) Passagem::where('user_id', $userId)->where('cancelada', false)->whereDate('created_at', today())->sum('qtd_passageiros');
                if ($qd + $novos > $op->limite_passagens_dia) {
                    throw new \RuntimeException('Limite diário de passagens atingido ('.$op->limite_passagens_dia.'/dia). Já vendidas hoje: '.$qd.'.');
                }
            }
            if ($op->limite_valor_dia) {
                $vd = (float) Passagem::where('user_id', $userId)->where('cancelada', false)->whereDate('created_at', today())->sum('total');
                if ($vd + $saleTotal > (float) $op->limite_valor_dia + 0.009) {
                    throw new \RuntimeException('Limite diário de valor atingido (R$ '.number_format((float) $op->limite_valor_dia, 2, ',', '.').'/dia).');
                }
            }
            if ($viagemId && $op->limite_passagens_viagem) {
                $qv = (int) Passagem::where('user_id', $userId)->where('cancelada', false)->where('viagem_id', $viagemId)->sum('qtd_passageiros');
                if ($qv + $novos > $op->limite_passagens_viagem) {
                    throw new \RuntimeException('Limite de passagens por viagem atingido ('.$op->limite_passagens_viagem.'/viagem). Já nesta viagem: '.$qv.'.');
                }
            }
            if ($viagemId && $op->limite_valor_viagem) {
                $vv = (float) Passagem::where('user_id', $userId)->where('cancelada', false)->where('viagem_id', $viagemId)->sum('total');
                if ($vv + $saleTotal > (float) $op->limite_valor_viagem + 0.009) {
                    throw new \RuntimeException('Limite de valor por viagem atingido (R$ '.number_format((float) $op->limite_valor_viagem, 2, ',', '.').'/viagem).');
                }
            }
        }

        // Cota de gratuidade POR TRECHO (soma por segmento da viagem, como as poltronas).
        if ($viagemId || ! empty($data['data_viagem'])) {
            $gi = $this->gratuidadeUso($empresa->id, $viagemId, $rotaId, $data['data_viagem'] ?? null, $data['embarcacao_id'] ?? null, (string) ($data['origem'] ?? ''), (string) ($data['destino'] ?? ''));
            if ($gi['ativo']) {
                $novosGrat = collect($data['passageiros'] ?? [])->filter(fn ($px) => in_array(mb_strtolower((string) ($px['tipo'] ?? '')), $gi['nomes'], true))->count();
                if ($novosGrat > 0 && $gi['usados'] + $novosGrat > $gi['cota']) {
                    throw new \RuntimeException('Cota de gratuidade atingida neste trecho ('.$gi['cota'].' por viagem). Já emitidas aqui: '.$gi['usados'].'.');
                }
            }
        }

        // número oficial do aparelho (PREFIXO-0001); fallback no servidor
        $numeroCompleto = trim((string) ($data['numero_completo'] ?? ''));
        $seq = (int) ($data['numero_seq'] ?? 0);
        if ($numeroCompleto === '') {
            $op = \App\Models\User::find($userId);
            $pref = $op && $op->prefixo ? $op->prefixo : ('OP' . $userId);
            $seq = $op ? $op->proximoSeq() : 1;
            $numeroCompleto = $pref . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        }

        return DB::transaction(function () use ($data, $userId, $uuid, $offline, $empresa, $viagemId, $rotaId, $numeroCompleto, $seq) {
            $p = Passagem::create([
                'uuid' => $uuid,
                'numero' => $seq,
                'numero_completo' => $numeroCompleto,
                'embarcacao_id' => ($data['embarcacao_id'] ?? null) ?: null,
                'viagem_id' => $viagemId,
                'rota_id' => $rotaId,
                'data_viagem' => $data['data_viagem'] ?? null,
                'origem' => $data['origem'],
                'destino' => $data['destino'],
                'tipo' => in_array($data['tipo'] ?? null, ['poltrona', 'rede', 'camarote'], true) ? $data['tipo'] : 'rede',
                'camarote_id' => ($data['camarote_id'] ?? null) ?: null,
                'comprador_nome' => $data['comprador_nome'] ?? null,
                'comprador_telefone' => $data['comprador_telefone'] ?? null,
                'comprador_documento' => $data['comprador_documento'] ?? null,
                'forma' => $data['forma'] ?? 'avista',
                'observacao' => $data['observacao'] ?? null,
                'desconto' => $data['desconto'] ?? 0,
                'user_id' => $userId,
                'origem_offline' => $offline,
                'criada_no_aparelho_em' => ! empty($data['criada_em']) ? \Carbon\Carbon::parse($data['criada_em'])->setTimezone(config('app.timezone')) : null,
            ]);

            foreach ($data['passageiros'] as $pax) {
                $vp = round((float) ($pax['valor_passagem'] ?? 0), 2);
                $vt = round((float) ($pax['valor_taxa'] ?? 0), 2);
                $va = round((float) ($pax['valor_alimentacao'] ?? 0), 2);
                $vi = round((float) ($pax['valor_integral'] ?? ($vp + $vt + $va)), 2);
                $docs = [];
                foreach ((array) ($pax['documentos'] ?? []) as $d) {
                    if (! is_string($d) || $d === '') continue;
                    $docs[] = str_starts_with($d, 'data:') ? ($this->salvarDataUrl($d) ?? null) : $d;
                }
                $docs = array_values(array_filter($docs));
                $p->passageiros()->create([
                    'nome' => $pax['nome'],
                    'tipo' => $pax['tipo'] ?? 'Adulto',
                    'papel' => $pax['papel'] ?? null,
                    'percentual' => (int) ($pax['percentual'] ?? 100),
                    'documento' => $pax['documento'] ?? null,
                    'nascimento' => ($pax['nascimento'] ?? null) ?: null,
                    'sexo' => $pax['sexo'] ?? null,
                    'telefone' => $pax['telefone'] ?? null,
                    'nacionalidade' => $pax['nacionalidade'] ?? null,
                    'checkin' => (bool) ($pax['checkin'] ?? true),
                    'foto_rg' => $pax['foto_rg'] ?? null,
                    'foto' => $pax['foto'] ?? null,
                    'documentos' => $docs ?: null,
                    'poltrona' => $pax['poltrona'] ?? null,
                    'valor_passagem' => $vp,
                    'valor_taxa' => $vt,
                    'valor_alimentacao' => $va,
                    'valor_integral' => $vi,
                    'valor_total' => round($vp + $vt + $va, 2),
                    'observacao' => $pax['observacao'] ?? null,
                ]);
                $this->lembrarPessoa($pax['nome'], null, $pax['documento'] ?? null);
            }

            foreach ($data['pagamentos'] ?? [] as $pag) {
                if ((float) $pag['valor'] <= 0) continue;
                \App\Models\Pagamento::create([
                    'empresa_id' => $empresa->id,
                    'modulo' => 'passagem',
                    'origem_id' => $p->id,
                    'encomenda_id' => null,
                    'meio' => $pag['meio'],
                    'pix_auto' => ($pag['meio'] === 'pix' && ! empty($pag['auto'])),
                    'valor' => $pag['valor'],
                    'user_id' => $userId,
                    'momento' => 'cadastro',
                    'tipo_mov' => 'entrada',
                ]);
            }

            $this->lembrarPessoa($data['comprador_nome'] ?? '', $data['comprador_telefone'] ?? null, $data['comprador_documento'] ?? null);
            $p->recalcular();
            $this->definirRevendedor($p, $userId);

            if ($seq > 0) {
                \App\Models\User::where('id', $userId)->where('proximo_seq', '<=', $seq)
                    ->update(['proximo_seq' => $seq + 1]);
            }

            return $p;
        });
    }

    /** Baixa: pagamento e/ou embarque (check-in). */
    /**
     * Uso da cota de gratuidade NO TRECHO pedido (soma por segmento da viagem, como as poltronas).
     * @return array{ativo:bool,cota:int,usados:int,restante:int,nomes:array}
     */
    public function gratuidadeUso(int $empresaId, ?int $viagemId, $rotaId, ?string $dataViagem, ?int $embarcacaoId, string $origem, string $destino): array
    {
        $empresa = \App\Models\Empresa::find($empresaId);
        $nomes = \App\Models\TipoPassageiro::where('empresa_id', $empresaId)->where('ativo', true)->where('gratuito', true)
            ->pluck('nome')->map(fn ($n) => mb_strtolower((string) $n))->all();
        $cota = null;
        if ($embarcacaoId && ($emb = \App\Models\Embarcacao::find($embarcacaoId)) && $emb->gratuito_qtd !== null) $cota = (int) $emb->gratuito_qtd;
        if ($cota === null) $cota = (int) ($empresa ? $empresa->config('gratuito_qtd', 2) : 2);

        if (! $nomes) return ['ativo' => false, 'cota' => $cota, 'usados' => 0, 'restante' => $cota, 'nomes' => []];

        if (! $viagemId && $rotaId) {
            $rt = \App\Models\Rota::find($rotaId);
            if ($rt) {
                $dataRef = $dataViagem ? \Carbon\Carbon::parse($dataViagem) : now();
                $viagemId = $rt->viagemPara($dataRef)->id;
            }
        }
        if (! $viagemId && ! $dataViagem) return ['ativo' => true, 'cota' => $cota, 'usados' => 0, 'restante' => $cota, 'nomes' => $nomes];

        $rota = $rotaId ? \App\Models\Rota::find($rotaId) : null;
        if (! $rota) {
            $rota = \App\Models\Rota::with('paradas')->get()->first(function ($r) use ($origem, $destino) {
                $locs = array_map('mb_strtolower', $r->localidades());
                return in_array(mb_strtolower($origem), $locs, true) && in_array(mb_strtolower($destino), $locs, true);
            });
        }
        $ordem = $rota ? array_map('mb_strtolower', $rota->localidades()) : [];
        $idx = fn ($c) => array_search(mb_strtolower((string) $c), $ordem, true);
        $reqS = $idx($origem); $reqD = $idx($destino);
        $temTrecho = $ordem && $reqS !== false && $reqD !== false;

        $rows = DB::table('passagens as p')
            ->join('passagem_passageiros as pp', 'p.id', '=', 'pp.passagem_id')
            ->where('p.empresa_id', $empresaId)->where('p.cancelada', false)
            ->when($viagemId, fn ($q) => $q->where('p.viagem_id', $viagemId))
            ->when(! $viagemId && $dataViagem, fn ($q) => $q->whereDate('p.data_viagem', $dataViagem))
            ->whereIn(DB::raw('LOWER(pp.tipo)'), $nomes)
            ->select('p.origem', 'p.destino', DB::raw('COUNT(*) as n'))
            ->groupBy('p.origem', 'p.destino')->get();

        if (! $temTrecho) {
            $tot = (int) $rows->sum('n');
            return ['ativo' => true, 'cota' => $cota, 'usados' => $tot, 'restante' => max(0, $cota - $tot), 'nomes' => $nomes];
        }
        [$reqMin, $reqMax] = [min($reqS, $reqD), max($reqS, $reqD)];
        $seg = [];
        foreach ($rows as $r) {
            $c = $idx($r->origem); $d = $idx($r->destino);
            if ($c === false || $d === false) { for ($k = $reqMin; $k < $reqMax; $k++) $seg[$k] = ($seg[$k] ?? 0) + (int) $r->n; continue; }
            [$cMin, $cMax] = [min($c, $d), max($c, $d)];
            for ($k = $cMin; $k < $cMax; $k++) $seg[$k] = ($seg[$k] ?? 0) + (int) $r->n;
        }
        $usados = 0;
        for ($k = $reqMin; $k < $reqMax; $k++) $usados = max($usados, $seg[$k] ?? 0);
        return ['ativo' => true, 'cota' => $cota, 'usados' => $usados, 'restante' => max(0, $cota - $usados), 'nomes' => $nomes];
    }

    public function baixa(Passagem $p, array $data, int $userId): void
    {
        if ($p->cancelada) return;

        DB::transaction(function () use ($p, $data, $userId) {
            $soma = 0;
            foreach (['dinheiro', 'pix', 'debito', 'credito', 'desconto'] as $meio) {
                $v = (float) ($data[$meio] ?? 0);
                if ($v > 0) {
                    \App\Models\Pagamento::create([
                        'empresa_id' => $p->empresa_id, 'modulo' => 'passagem', 'origem_id' => $p->id,
                        'encomenda_id' => null, 'meio' => $meio, 'pix_auto' => ($meio === 'pix' && ! empty($data['pix_auto'])), 'valor' => $v, 'user_id' => $userId,
                        'momento' => 'baixa', 'tipo_mov' => 'entrada',
                    ]);
                    $soma += $v;
                }
            }
            if ($soma > 0) $p->recalcular();

            $podeEmbarcar = $p->devido <= 0.009 || $p->forma === 'faturado';
            $quitou = $soma > 0 && $p->devido <= 0.009;
            if ((! empty($data['embarcar']) || $quitou) && ! $p->embarcou && $podeEmbarcar) {
                $p->forceFill(['embarcou' => true, 'embarcou_em' => now(), 'embarcou_por' => $userId])->save();
            }
        });
    }

    /** Define a quem a venda pertence (revendedor) e quanto é o repasse de cada nível. */
    protected function definirRevendedor(\App\Models\Passagem $p, int $userId): void
    {
        $seller = \App\Models\User::find($userId);
        if (! $seller) return;
        $revId = $seller->revendedorDono();
        if (! $revId) return; // venda direta da empresa: sem acerto de revendedor

        $total = (float) $p->total;
        $rev = \App\Models\User::find($revId);
        $p->forceFill([
            'revendedor_id' => $revId,
            'acerto_empresa_valor' => $rev ? $rev->repasseDe($total) : $total,
            'acerto_revendedor_valor' => $seller->revendedor_id ? $seller->repasseDe($total) : 0,
        ])->save();
    }

    /** Salva uma imagem data-URL (base64) no storage publico e devolve o caminho relativo. */
    protected function salvarDataUrl(string $dataUrl): ?string
    {
        if (! preg_match('/^data:image\/([a-zA-Z0-9]+);base64,(.+)$/', $dataUrl, $m)) return null;
        $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
        $bin = base64_decode($m[2], true);
        if ($bin === false || strlen($bin) < 32) return null;
        $nome = 'documentos/'.date('Y/m/d').'/'.\Illuminate\Support\Str::random(28).'.'.$ext;
        \Illuminate\Support\Facades\Storage::disk('public')->put($nome, $bin);
        return 'storage/'.$nome;
    }

    protected function lembrarPessoa(string $nome, ?string $tel, ?string $doc): void
    {
        $nome = trim($nome);
        if ($nome === '') return;
        $p = Pessoa::where('nome', $nome)->when($tel, fn ($q) => $q->where('telefone', $tel))->first();
        if ($p) $p->update(['telefone' => $tel ?: $p->telefone, 'documento' => $doc ?: $p->documento]);
        else Pessoa::create(['nome' => $nome, 'telefone' => $tel, 'documento' => $doc]);
    }
}
