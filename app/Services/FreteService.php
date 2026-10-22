<?php

namespace App\Services;

use App\Models\Frete;
use App\Models\Pessoa;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;

/** Criação/baixa de frete (carga). Idempotente por uuid. Grava no caixa unificado (modulo=frete). */
class FreteService
{
    public function criar(array $data, int $userId, ?string $uuid = null, bool $offline = false): Frete
    {
        if ($uuid && ($existente = Frete::where('uuid', $uuid)->first())) return $existente;

        $empresa = Tenant::empresa();
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

        $numeroCompleto = trim((string) ($data['numero_completo'] ?? ''));
        $seq = (int) ($data['numero_seq'] ?? 0);
        if ($numeroCompleto === '') {
            $op = \App\Models\User::find($userId);
            $pref = $op && $op->prefixo ? $op->prefixo : ('OP' . $userId);
            $seq = $op ? $op->proximoSeq() : 1;
            $numeroCompleto = $pref . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        }

        return DB::transaction(function () use ($data, $userId, $uuid, $offline, $empresa, $viagemId, $rotaId, $numeroCompleto, $seq) {
            $subtotal = round((float) ($data['subtotal'] ?? 0), 2);
            $f = Frete::create([
                'uuid' => $uuid, 'numero' => $seq, 'numero_completo' => $numeroCompleto,
                'embarcacao_id' => ($data['embarcacao_id'] ?? null) ?: null, 'viagem_id' => $viagemId, 'rota_id' => $rotaId,
                'data_viagem' => $data['data_viagem'] ?? null,
                'origem' => $data['origem'], 'destino' => $data['destino'],
                'remetente_nome' => $data['remetente_nome'], 'remetente_telefone' => $data['remetente_telefone'] ?? null, 'remetente_documento' => $data['remetente_documento'] ?? null,
                'destinatario_nome' => $data['destinatario_nome'], 'destinatario_telefone' => $data['destinatario_telefone'] ?? null, 'destinatario_documento' => $data['destinatario_documento'] ?? null,
                'descricao' => $data['descricao'] ?? 'Carga', 'peso' => $data['peso'] ?? null, 'volumes' => $data['volumes'] ?? 1,
                'preco_kilo' => $data['preco_kilo'] ?? 0, 'subtotal' => $subtotal,
                'forma' => $data['forma'] ?? 'avista', 'setor' => $data['setor'] ?? null, 'observacao' => $data['observacao'] ?? null,
                'desconto' => $data['desconto'] ?? 0, 'user_id' => $userId, 'origem_offline' => $offline,
                'criada_no_aparelho_em' => ! empty($data['criada_em']) ? \Carbon\Carbon::parse($data['criada_em'])->setTimezone(config('app.timezone')) : null,
            ]);

            foreach ($data['pagamentos'] ?? [] as $p) {
                if ((float) $p['valor'] <= 0) continue;
                \App\Models\Pagamento::create([
                    'empresa_id' => $empresa->id, 'modulo' => 'frete', 'origem_id' => $f->id, 'encomenda_id' => null,
                    'meio' => $p['meio'], 'valor' => $p['valor'], 'user_id' => $userId, 'momento' => 'cadastro', 'tipo_mov' => 'entrada',
                ]);
            }

            $this->lembrarPessoa($data['remetente_nome'], $data['remetente_telefone'] ?? null, $data['remetente_documento'] ?? null);
            $this->lembrarPessoa($data['destinatario_nome'], $data['destinatario_telefone'] ?? null, $data['destinatario_documento'] ?? null);
            $f->recalcular();

            if ($seq > 0) {
                \App\Models\User::where('id', $userId)->where('proximo_seq', '<=', $seq)->update(['proximo_seq' => $seq + 1]);
            }
            return $f;
        });
    }

    /** Baixa: pagamento e/ou entrega. */
    public function baixa(Frete $f, array $data, int $userId): void
    {
        if ($f->cancelada) return;
        DB::transaction(function () use ($f, $data, $userId) {
            $soma = 0;
            foreach (['dinheiro', 'pix', 'debito', 'credito', 'desconto'] as $meio) {
                $v = (float) ($data[$meio] ?? 0);
                if ($v > 0) {
                    \App\Models\Pagamento::create([
                        'empresa_id' => $f->empresa_id, 'modulo' => 'frete', 'origem_id' => $f->id, 'encomenda_id' => null,
                        'meio' => $meio, 'valor' => $v, 'user_id' => $userId, 'momento' => 'baixa', 'tipo_mov' => 'entrada',
                    ]);
                    $soma += $v;
                }
            }
            if ($soma > 0) $f->recalcular();
            $podeEntregar = $f->devido <= 0.009 || $f->forma === 'faturado';
            $quitou = $soma > 0 && $f->devido <= 0.009;
            if ((! empty($data['entregar']) || $quitou) && ! $f->entregue && $podeEntregar) {
                $f->forceFill(['entregue' => true, 'entregue_em' => now(), 'entregue_por' => $userId, 'entregue_para' => $data['entregue_para'] ?? null])->save();
            }
        });
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
