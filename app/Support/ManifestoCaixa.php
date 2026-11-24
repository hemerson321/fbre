<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Monta as linhas (manifesto financeiro) + totais a partir de uma coleção de Passagens.
 * Reutilizado no relatório "Caixa por viagem", nos lançamentos do Caixa e em "Minhas vendas".
 * As passagens devem vir com: passageiros, operador, pagamentos.
 */
class ManifestoCaixa
{
    public static function dados(Collection $passagens): array
    {
        $linhas = collect();
        $totForma = [];
        $totFormaProp = []; // parte de cada forma recebida direto pelo PROPRIETÁRIO (maquineta/conta dele)
        $totDesconto = 0.0;
        $totGeral = 0.0;
        $totDevolvido = 0.0; $totCancelado = 0.0;
        $nPax = 0; $nIdosos = 0; $nColos = 0; $nDevolvidas = 0; $nCancelados = 0; $nPcd = 0; $nOutros = 0;
        $nr = 0;

        foreach ($passagens as $p) {
            $cancelada = (bool) $p->cancelada;
            $pgsForma = $p->pagamentos->where('meio', '!=', 'desconto');
            $formaParts = $pgsForma->map(fn ($pg) => (\App\Models\Pagamento::MEIOS[$pg->meio] ?? ucfirst((string) $pg->meio)).((bool) ($pg->pago_proprietario ?? false) ? ' Prop.' : ''))->unique()->values();
            $formaTxt = $formaParts->isNotEmpty()
                ? $formaParts->implode(', ')
                : (\App\Models\Passagem::FORMAS[$p->forma] ?? ucfirst((string) $p->forma));
            $ehMunicipio = $p->forma === 'faturado';
            $ehProp = $p->pagamentos->contains(fn ($pg) => (bool) ($pg->pago_proprietario ?? false));

            foreach ($p->passageiros as $pax) {
                $ehColo = ($pax->papel ?? '') === 'colo';
                $cancPax = $cancelada || (bool) ($pax->cancelado ?? false);
                $cancTipo = $cancPax ? ($cancelada ? ((string) ($p->cancel_tipo ?? 'devolucao')) : ((string) ($pax->cancelado_tipo ?? 'devolucao'))) : null;
                $ehCancelamento = $cancTipo === 'cancelamento';
                $nr++;
                $tipoTxt = (string) ($pax->papel ?: $pax->tipo);
                $ehIdoso = mb_stripos($tipoTxt, 'idoso') !== false;
                $ehPcd = mb_stripos($tipoTxt, 'pcd') !== false || mb_stripos($tipoTxt, 'defic') !== false;
                $ehOutros = mb_stripos($tipoTxt, 'outro') !== false;
                $linhas->push((object) [
                    'nr' => $nr,
                    'ticket' => $p->numeroFormatado(),
                    'trecho' => $p->origem.'/'.$p->destino,
                    'nome' => $pax->nome,
                    'colo' => $ehColo,
                    'cancelado' => $cancPax,
                    'data_compra' => $p->created_at,
                    'vendedor' => $p->revendedor?->name ?: ($p->operador?->name ?? '—'),
                    'valor' => (float) $pax->valor_total,
                    'tipo' => $tipoTxt,
                    'p' => $ehColo ? 'C' : mb_strtoupper(mb_substr(trim($tipoTxt) ?: 'A', 0, 1)),
                    'agencia' => (string) ($p->revendedor?->name ?? ''),
                    'status' => $cancPax ? ($ehCancelamento ? 'Cancelada' : 'Devolvida') : ((float) $p->devido > 0.009 ? 'Em aberto' : ($ehProp ? 'Pago · Proprietário' : 'Paga')),
                    'cancel_label' => $ehCancelamento ? 'CANCELADA' : 'DEVOLVIDA',
                    'forma' => $formaTxt,
                    'idoso' => $ehIdoso && ! $cancPax,
                    'pcd' => $ehPcd && ! $cancPax,
                    'municipio' => $ehMunicipio && ! $cancPax,
                    'proprietario' => $ehProp && ! $cancPax,
                    'cliente' => (string) ($p->clienteFaturado?->rotulo() ?: ($p->comprador_nome ?? '')),
                    'poltrona' => (string) ($pax->poltrona ?? ''),
                    'embarcou' => (bool) $p->embarcou,
                    'checkin' => (bool) ($pax->checkin ?? false),
                ]);
                if (! $cancPax && ! $ehColo) { $nPax++; if ($ehIdoso) $nIdosos++; if ($ehPcd) $nPcd++; if ($ehOutros) $nOutros++; }
                if ($ehColo && ! $cancPax) $nColos++;
            }

            if ($cancelada) {
                if (($p->cancel_tipo ?? '') === 'cancelamento') { $nCancelados++; $totCancelado += (float) $p->total; }
                else { $nDevolvidas++; $totDevolvido += (float) $p->total; }
            } else {
                foreach ($p->pagamentos as $pg) {
                    if ($pg->meio === 'desconto') { $totDesconto += (float) $pg->valor; continue; }
                    if ((float) $pg->valor <= 0) continue;
                    $totForma[$pg->meio] = ($totForma[$pg->meio] ?? 0) + (float) $pg->valor;
                    if ((bool) ($pg->pago_proprietario ?? false)) $totFormaProp[$pg->meio] = ($totFormaProp[$pg->meio] ?? 0) + (float) $pg->valor;
                }
                $totDesconto += (float) $p->desconto;
                $totGeral += (float) $p->total;
            }
        }

        return [
            'linhas' => $linhas,
            'totForma' => $totForma,
            'totFormaProp' => $totFormaProp,
            'totDesconto' => $totDesconto,
            'totGeral' => $totGeral,
            'totRecebido' => array_sum($totForma),
            'totDevolvido' => $totDevolvido, 'totCancelado' => $totCancelado,
            'nPax' => $nPax, 'nIdosos' => $nIdosos, 'nPcd' => $nPcd, 'nOutros' => $nOutros, 'nColos' => $nColos, 'nDevolvidas' => $nDevolvidas, 'nCancelados' => $nCancelados,
        ];
    }
}
