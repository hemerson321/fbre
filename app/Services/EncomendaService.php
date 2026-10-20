<?php

namespace App\Services;

use App\Models\Encomenda;
use App\Models\Evento;
use App\Models\Pessoa;
use App\Models\Produto;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Regras de criação/baixa de encomenda — usadas pela tela normal e pela sincronização offline.
 * Idempotente por uuid: enviar duas vezes a mesma encomenda não duplica.
 */
class EncomendaService
{
    public function criar(array $data, int $userId, ?string $uuid = null, bool $offline = false): Encomenda
    {
        if ($uuid && ($existente = Encomenda::where('uuid', $uuid)->first())) {
            return $existente; // já sincronizada
        }

        $empresa = Tenant::empresa();

        // viagem: se veio rota_id, cria/acha a viagem do ciclo; senão usa viagem_id explícito (ou avulso)
        $viagemId = $data['viagem_id'] ?? null;
        $rotaId = $data['rota_id'] ?? null;
        if (! $viagemId && $rotaId) {
            $rota = \App\Models\Rota::find($rotaId);
            if ($rota) {
                // data da viagem escolhida pelo operador (pode ser futura); senão a data do cadastro
                $dataRef = ! empty($data['viagem_data'])
                    ? \Carbon\Carbon::parse($data['viagem_data'])
                    : (! empty($data['criada_em']) ? \Carbon\Carbon::parse($data['criada_em']) : now());
                $viagemId = $rota->viagemPara($dataRef)->id;
            }
        }

        // data do lançamento (permite registrar encomenda feita em dia anterior) — mantém a hora atual
        $quando = null;
        if (! empty($data['lancamento_em'])) {
            $quando = \Carbon\Carbon::parse($data['lancamento_em'])->setTimeFromTimeString(now()->format('H:i:s'));
            if ($quando->isToday() || $quando->isFuture()) $quando = null; // só vale pra datas passadas
        }

        // número oficial e definitivo vem do aparelho (ex.: RE-0001) e nunca é reatribuído.
        // Fallback (cliente antigo / sem número): gera no servidor pela sequência do operador.
        $numeroCompleto = trim((string) ($data['numero_completo'] ?? ''));
        $seq = (int) ($data['numero_seq'] ?? 0);
        if ($numeroCompleto === '') {
            $op = \App\Models\User::find($userId);
            $pref = $op && $op->prefixo ? $op->prefixo : ('OP' . $userId);
            $seq = $op ? $op->proximoSeq() : 1;
            $numeroCompleto = $pref . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        }

        return DB::transaction(function () use ($data, $userId, $uuid, $offline, $empresa, $viagemId, $rotaId, $quando, $numeroCompleto, $seq) {
            $enc = Encomenda::create([
                'uuid' => $uuid,
                'numero' => $seq,
                'numero_completo' => $numeroCompleto,
                'numero_provisorio' => $data['numero_provisorio'] ?? null,
                'embarcacao_id' => ($data['embarcacao_id'] ?? null) ?: null,
                'viagem_id' => $viagemId,
                'rota_id' => $rotaId,
                'origem' => $data['origem'],
                'destino' => $data['destino'],
                'remetente_nome' => $data['remetente_nome'],
                'remetente_telefone' => $data['remetente_telefone'] ?? null,
                'remetente_documento' => $data['remetente_documento'] ?? null,
                'destinatario_nome' => $data['destinatario_nome'],
                'destinatario_telefone' => $data['destinatario_telefone'] ?? null,
                'destinatario_documento' => $data['destinatario_documento'] ?? null,
                'volumes' => $data['volumes'] ?? 1,
                'forma' => $data['forma'] ?? 'avista',
                'setor' => $data['setor'] ?? null,
                'observacao' => $data['observacao'] ?? null,
                'desconto' => $data['desconto'] ?? 0,
                'user_id' => $userId,
                'origem_offline' => $offline,
                'criada_no_aparelho_em' => ! empty($data['criada_em']) ? \Carbon\Carbon::parse($data['criada_em'])->setTimezone(config('app.timezone')) : null,
            ]);

            if ($quando) {
                $enc->forceFill(['created_at' => $quando, 'updated_at' => $quando])->saveQuietly();
            }

            foreach ($data['itens'] as $it) {
                $enc->itens()->create([
                    'descricao' => $it['descricao'],
                    'quantidade' => $it['quantidade'],
                    'valor_unitario' => $it['valor_unitario'],
                    'valor_total' => round($it['quantidade'] * $it['valor_unitario'], 2),
                    'observacao' => $it['observacao'] ?? null,
                ]);
                $this->catalogar($empresa, $it['descricao'], $it['valor_unitario']);
            }

            foreach ($data['pagamentos'] ?? [] as $p) {
                if ((float) $p['valor'] <= 0) continue;
                $enc->pagamentos()->create([
                    'empresa_id' => $empresa->id,
                    'meio' => $p['meio'],
                    'valor' => $p['valor'],
                    'user_id' => $userId,
                    'momento' => 'cadastro',
                ] + ($quando ? ['created_at' => $quando, 'updated_at' => $quando] : []));
            }

            $enc->recalcular();

            $this->lembrarPessoa($data['remetente_nome'], $data['remetente_telefone'] ?? null, $data['remetente_documento'] ?? null);
            $this->lembrarPessoa($data['destinatario_nome'], $data['destinatario_telefone'] ?? null, $data['destinatario_documento'] ?? null);

            Evento::create(['encomenda_id' => $enc->id, 'user_id' => $userId, 'tipo' => 'cadastro',
                'dados' => ['total' => $enc->total, 'pago' => $enc->pago, 'offline' => $offline]]);

            // mantém a semente do operador à frente do maior número já usado (continuidade/reinstalação)
            if ($seq > 0) {
                \App\Models\User::where('id', $userId)->where('proximo_seq', '<=', $seq)
                    ->update(['proximo_seq' => $seq + 1]);
            }

            return $enc;
        });
    }

    /** Baixa de pagamento e/ou entrega. Idempotente por uuid do evento. */
    public function baixa(Encomenda $enc, array $data, int $userId, ?string $uuid = null): void
    {
        if ($uuid && Evento::where('uuid', $uuid)->exists()) return;
        if ($enc->cancelada) return;

        DB::transaction(function () use ($enc, $data, $userId, $uuid) {
            $soma = 0;
            foreach (['dinheiro', 'pix', 'debito', 'credito', 'desconto'] as $meio) {
                $v = (float) ($data[$meio] ?? 0);
                if ($v > 0) {
                    $enc->pagamentos()->create(['empresa_id' => $enc->empresa_id, 'meio' => $meio, 'valor' => $v, 'user_id' => $userId, 'momento' => 'baixa']);
                    $soma += $v;
                }
            }
            if ($soma > 0) {
                $enc->recalcular();
                Evento::create(['uuid' => (! empty($data['entregar']) || $enc->entregue) ? null : $uuid, 'encomenda_id' => $enc->id, 'user_id' => $userId, 'tipo' => 'pagamento', 'dados' => ['valor' => $soma]]);
            }
            // ao quitar o valor, a entrega é dada automaticamente (faturado: libera mesmo com saldo)
            $podeEntregar = $enc->devido <= 0.009 || $enc->forma === 'faturado';
            $quitouAgora = $soma > 0 && $enc->devido <= 0.009;
            if ((! empty($data['entregar']) || $quitouAgora) && ! $enc->entregue && $podeEntregar) {
                $enc->forceFill(['entregue' => true, 'entregue_em' => ! empty($data['entregue_em']) ? \Carbon\Carbon::parse($data['entregue_em'])->setTimezone(config('app.timezone')) : now(), 'entregue_por' => $userId, 'entregue_para' => $data['entregue_para'] ?? null])->save();
                Evento::create(['uuid' => $uuid, 'encomenda_id' => $enc->id, 'user_id' => $userId, 'tipo' => 'entrega', 'dados' => ['para' => $data['entregue_para'] ?? null]]);
            } elseif ($uuid) {
                Evento::where('uuid', $uuid)->exists() || Evento::create(['uuid' => $uuid, 'encomenda_id' => $enc->id, 'user_id' => $userId, 'tipo' => 'sync', 'dados' => []]);
            }
        });
    }

    /** Garante o produto no catálogo e conta o uso (ordena os mais usados). */
    protected function catalogar($empresa, ?string $nome, $valor): void
    {
        $nome = trim((string) $nome);
        if ($nome === '') return;
        $p = Produto::firstOrCreate(
            ['empresa_id' => $empresa->id, 'nome' => $nome],
            ['valor_padrao' => (float) $valor, 'ativo' => true]
        );
        $p->increment('usos');
    }

    protected function lembrarPessoa(string $nome, ?string $tel, ?string $doc): void
    {
        $nome = trim($nome);
        if ($nome === '') return;
        $p = Pessoa::where('nome', $nome)->when($tel, fn ($q) => $q->where('telefone', $tel))->first();
        if ($p) {
            $p->update(['telefone' => $tel ?: $p->telefone, 'documento' => $doc ?: $p->documento]);
        } else {
            Pessoa::create(['nome' => $nome, 'telefone' => $tel, 'documento' => $doc]);
        }
    }
}
