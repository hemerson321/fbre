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

    protected function lembrarPessoa(string $nome, ?string $tel, ?string $doc): void
    {
        $nome = trim($nome);
        if ($nome === '') return;
        $p = Pessoa::where('nome', $nome)->when($tel, fn ($q) => $q->where('telefone', $tel))->first();
        if ($p) $p->update(['telefone' => $tel ?: $p->telefone, 'documento' => $doc ?: $p->documento]);
        else Pessoa::create(['nome' => $nome, 'telefone' => $tel, 'documento' => $doc]);
    }
}
