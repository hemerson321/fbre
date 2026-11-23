<?php

namespace App\Http\Controllers;

use App\Models\Embarcacao;
use App\Models\Pagamento;
use App\Models\Passagem;
use App\Models\Prestacao;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrestacaoController extends Controller
{
    /** Quem pode fechar/pagar/desfazer prestação: quem confere o caixa (mesma régua do card). */
    protected function gate(Embarcacao $embarcacao): void
    {
        $u = auth()->user();
        abort_unless($embarcacao->empresa_id === Tenant::id(), 404);
        abort_unless($u->caixaTodos(), 403);
    }

    protected function gatePrest(Prestacao $p): void
    {
        $u = auth()->user();
        abort_unless($p->empresa_id === Tenant::id(), 404);
        abort_unless($u->caixaTodos(), 403);
    }

    protected function parseValor($v): float
    {
        $v = trim((string) $v);
        if ($v === '') return 0.0;
        // aceita "1.467,00" e "1467.00"
        if (str_contains($v, ',')) $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
        return round((float) $v, 2);
    }

    /** Dívida de devolução ainda em aberto do dono (devoluções já acertadas menos o que já foi abatido). */
    protected function debitoDono(Embarcacao $e): float
    {
        $deb = (float) Pagamento::where('embarcacao_id', $e->id)->where('estornado', false)->where('categoria', 'Débito do dono (devolução)')->sum('valor')
             - (float) Pagamento::where('embarcacao_id', $e->id)->where('estornado', false)->where('categoria', 'Débito do dono — abatido')->sum('valor');
        return max(0.0, round($deb, 2));
    }

    protected function registrarPagamento(Prestacao $p, Embarcacao $e, float $valor, string $forma, string $lote, ?int $viagemId = null, ?string $dataViagem = null): void
    {
        if ($valor <= 0) return;
        Pagamento::create([
            'empresa_id' => Tenant::id(), 'modulo' => 'avulso', 'origem_id' => null, 'encomenda_id' => null,
            'embarcacao_id' => $e->id, 'viagem_id' => $viagemId, 'data_viagem' => $dataViagem,
            'meio' => $forma, 'valor' => $valor, 'user_id' => auth()->id(),
            'momento' => 'baixa', 'tipo_mov' => 'saida',
            'categoria' => 'Pagamento ao dono (balcão)',
            'descricao' => 'Prestação #'.$p->numero.' — repasse do balcão ao dono - '.$e->nome,
            'conferido' => true, 'conferido_em' => now(), 'conferido_por' => auth()->id(),
            'acerto_conta' => false, 'acerto_lote' => $lote, 'prestacao_id' => $p->id,
        ]);
    }

    /** Fecha a prestação: congela o ciclo pendente do transporte (separa das vendas atuais) e paga total/parcial. */
    public function fechar(Request $request, Embarcacao $embarcacao)
    {
        $this->gate($embarcacao);
        $pct = (int) $embarcacao->comissao_empresa_pct;
        $viagemId = $request->input('viagem_id') ?: null;
        $dataViagem = $request->input('data_viagem') ?: null;

        $q = Passagem::where('embarcacao_id', $embarcacao->id)->where('cancelada', false)->where('transferida', false)
            ->where('acerto_dono', false)->whereNull('revendedor_id')
            ->when($viagemId, fn ($x) => $x->where('viagem_id', $viagemId))
            ->when($dataViagem, fn ($x) => $x->whereDate('data_viagem', $dataViagem));
        $ids = (clone $q)->pluck('id');
        if ($ids->isEmpty()) return back()->with('erro', 'Não há passagens pendentes desse transporte para fechar.');

        $agg = (clone $q)->selectRaw('COUNT(*) qtd, COALESCE(SUM(total),0) bruto, COALESCE(SUM(total*(100-'.$pct.')/100),0) devido')->first();
        $qtd = (int) $agg->qtd;
        $bruto = round((float) $agg->bruto, 2);
        $devidoBruto = round((float) $agg->devido, 2);
        $comissao = round($bruto - $devidoBruto, 2);

        $deb = $this->debitoDono($embarcacao);
        $abate = round(min($devidoBruto, $deb), 2);
        $totalDevido = round($devidoBruto - $abate, 2);

        $forma = (string) ($request->input('forma') ?: 'dinheiro');
        $pagarAgora = $request->filled('valor') ? $this->parseValor($request->input('valor')) : $totalDevido;
        $pagarAgora = max(0.0, min($pagarAgora, $totalDevido));

        DB::transaction(function () use ($embarcacao, $ids, $qtd, $bruto, $pct, $comissao, $totalDevido, $abate, $forma, $pagarAgora, $viagemId, $dataViagem) {
            $lote = (string) Str::uuid();
            $prest = Prestacao::create([
                'empresa_id' => Tenant::id(), 'embarcacao_id' => $embarcacao->id, 'viagem_id' => $viagemId, 'data_viagem' => $dataViagem,
                'revendedor_id' => null, 'tipo' => 'balcao',
                'lote' => $lote, 'numero' => Prestacao::proximoNumero(), 'qtd' => $qtd,
                'total_bruto' => $bruto, 'comissao_pct' => $pct, 'comissao' => $comissao, 'total_devido' => $totalDevido,
                'valor_pago' => 0, 'saldo' => $totalDevido, 'forma' => $forma, 'status' => 'pendente',
                'fechada_em' => now(), 'fechada_por' => auth()->id(),
            ]);
            Passagem::whereIn('id', $ids)->update([
                'acerto_dono' => true, 'acerto_dono_em' => now(), 'acerto_dono_por' => auth()->id(),
                'acerto_lote' => $lote, 'prestacao_id' => $prest->id,
            ]);
            if ($abate > 0) {
                Pagamento::create([
                    'empresa_id' => Tenant::id(), 'modulo' => 'avulso', 'embarcacao_id' => $embarcacao->id,
                    'viagem_id' => $viagemId, 'data_viagem' => $dataViagem,
                    'meio' => 'dinheiro', 'valor' => $abate, 'user_id' => auth()->id(),
                    'momento' => 'baixa', 'tipo_mov' => 'entrada', 'categoria' => 'Débito do dono — abatido',
                    'descricao' => 'Prestação #'.$prest->numero.' — débito de devolução abatido - '.$embarcacao->nome,
                    'acerto_lote' => $lote, 'prestacao_id' => $prest->id,
                ]);
            }
            $this->registrarPagamento($prest, $embarcacao, $pagarAgora, $forma, $lote, $viagemId, $dataViagem);
            $prest->valor_pago = $pagarAgora;
            $prest->recalcularSaldo();
            $prest->save();
        });

        $msg = 'Prestação fechada: '.$qtd.' passagem(ns) · devido R$ '.number_format($totalDevido, 2, ',', '.').' · pago R$ '.number_format($pagarAgora, 2, ',', '.');
        if ($abate > 0) $msg .= ' · abatido R$ '.number_format($abate, 2, ',', '.').' de devolução';
        return back()->with('ok', $msg.'.');
    }

    /** Baixa de saldo pendente de uma prestação já fechada (pagamento parcial adicional). */
    public function pagar(Request $request, Prestacao $prestacao)
    {
        $this->gatePrest($prestacao);
        if ($prestacao->desfeita) return back()->with('erro', 'Prestação desfeita.');
        $emb = $prestacao->embarcacao;
        if (! $emb) abort(404);
        $saldo = (float) $prestacao->saldo;
        if ($saldo <= 0) return back()->with('erro', 'Prestação já está quitada.');

        $forma = (string) ($request->input('forma') ?: ($prestacao->forma ?: 'dinheiro'));
        $v = $request->filled('valor') ? $this->parseValor($request->input('valor')) : $saldo;
        $v = max(0.0, min($v, $saldo));
        if ($v <= 0) return back()->with('erro', 'Valor inválido.');

        DB::transaction(function () use ($prestacao, $emb, $v, $forma) {
            $this->registrarPagamento($prestacao, $emb, $v, $forma, $prestacao->lote, $prestacao->viagem_id, $prestacao->data_viagem ? \Illuminate\Support\Carbon::parse($prestacao->data_viagem)->toDateString() : null);
            $prestacao->valor_pago = round((float) $prestacao->valor_pago + $v, 2);
            $prestacao->forma = $forma;
            $prestacao->recalcularSaldo();
            $prestacao->save();
        });

        return back()->with('ok', 'Baixa de R$ '.number_format($v, 2, ',', '.').' na prestação #'.$prestacao->numero.'.');
    }

    /** Desfaz a prestação: passagens voltam pra em aberto, estorna os lançamentos no caixa e some do histórico. */
    public function desfazer(Request $request, Prestacao $prestacao)
    {
        $this->gatePrest($prestacao);
        if ($prestacao->desfeita) return back()->with('erro', 'Prestação já foi desfeita.');

        DB::transaction(function () use ($prestacao) {
            Passagem::where('prestacao_id', $prestacao->id)->update([
                'acerto_dono' => false, 'acerto_dono_em' => null, 'acerto_dono_por' => null,
                'acerto_lote' => null, 'prestacao_id' => null,
            ]);
            Pagamento::where('prestacao_id', $prestacao->id)->where('estornado', false)->update(['estornado' => true]);
            $prestacao->desfeita = true;
            $prestacao->desfeita_em = now();
            $prestacao->desfeita_por = auth()->id();
            $prestacao->save();
        });

        return back()->with('ok', 'Prestação #'.$prestacao->numero.' desfeita: passagens voltaram para em aberto e os lançamentos foram estornados.');
    }

    /** Detalhe (passageiros + pagamentos) para "ver detalhes/comprovante". */
    public function detalhe(Prestacao $prestacao)
    {
        $this->gatePrest($prestacao);
        $prestacao->load(['embarcacao', 'fechadaPor']);
        $passagens = Passagem::where('prestacao_id', $prestacao->id)->orderBy('id')->get(['id', 'numero', 'comprador_nome', 'total', 'data_viagem', 'origem', 'destino']);
        $pagamentos = Pagamento::where('prestacao_id', $prestacao->id)->orderBy('id')->get(['id', 'valor', 'meio', 'tipo_mov', 'categoria', 'estornado', 'created_at']);
        return view('prestacoes.detalhe', compact('prestacao', 'passagens', 'pagamentos'));
    }
}
