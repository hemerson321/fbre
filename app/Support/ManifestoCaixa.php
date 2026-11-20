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
        $totDesconto = 0.0;
        $totGeral = 0.0;
        $totDevolvido = 0.0;
        $nPax = 0; $nIdosos = 0; $nColos = 0; $nDevolvidas = 0;
        $nr = 0;

        foreach ($passagens as $p) {
            $cancelada = (bool) $p->cancelada;
            $meios = $p->pagamentos->where('meio', '!=', 'desconto')->groupBy('meio');
            $formaTxt = $meios->isNotEmpty()
                ? $meios->keys()->map(fn ($m) => \App\Models\Pagamento::MEIOS[$m] ?? ucfirst($m))->implode(', ')
                : (\App\Models\Passagem::FORMAS[$p->forma] ?? ucfirst((string) $p->forma));
            $ehMunicipio = $p->forma === 'faturado';

            foreach ($p->passageiros as $pax) {
                $ehColo = ($pax->papel ?? '') === 'colo';
                $cancPax = $cancelada || (bool) ($pax->cancelado ?? false);
                $nr++;
                $tipoTxt = (string) ($pax->papel ?: $pax->tipo);
                $ehIdoso = mb_stripos($tipoTxt, 'idoso') !== false;
                $ehPcd = mb_stripos($tipoTxt, 'pcd') !== false || mb_stripos($tipoTxt, 'defic') !== false;
                $linhas->push((object) [
                    'nr' => $nr,
                    'ticket' => $p->numeroFormatado(),
                    'trecho' => $p->origem.'/'.$p->destino,
                    'nome' => $pax->nome,
                    'colo' => $ehColo,
                    'cancelado' => $cancPax,
                    'data_compra' => $p->created_at,
                    'vendedor' => $p->operador?->name ?? '—',
                    'valor' => (float) $pax->valor_total,
                    'tipo' => $tipoTxt,
                    'p' => $ehColo ? 'C' : mb_strtoupper(mb_substr(trim($tipoTxt) ?: 'A', 0, 1)),
                    'agencia' => (string) ($p->revendedor?->name ?? ''),
                    'status' => $cancPax ? 'Devolvida' : ((float) $p->devido > 0.009 ? 'Em aberto' : 'Paga'),
                    'forma' => $formaTxt,
                    'idoso' => $ehIdoso && ! $cancPax,
                    'pcd' => $ehPcd && ! $cancPax,
                    'municipio' => $ehMunicipio && ! $cancPax,
                    'cliente' => (string) ($p->comprador_nome ?? ''),
                    'poltrona' => (string) ($pax->poltrona ?? ''),
                    'embarcou' => (bool) $p->embarcou,
                    'checkin' => (bool) ($pax->checkin ?? false),
                ]);
                if (! $cancPax && ! $ehColo) { $nPax++; if ($ehIdoso) $nIdosos++; }
                if ($ehColo && ! $cancPax) $nColos++;
            }

            if ($cancelada) {
                $nDevolvidas++;
                $totDevolvido += (float) $p->total;
            } else {
                foreach ($p->pagamentos as $pg) {
                    if ($pg->meio === 'desconto') { $totDesconto += (float) $pg->valor; continue; }
                    if ((float) $pg->valor <= 0) continue;
                    $totForma[$pg->meio] = ($totForma[$pg->meio] ?? 0) + (float) $pg->valor;
                }
                $totDesconto += (float) $p->desconto;
                $totGeral += (float) $p->total;
            }
        }

        return [
            'linhas' => $linhas,
            'totForma' => $totForma,
            'totDesconto' => $totDesconto,
            'totGeral' => $totGeral,
            'totRecebido' => array_sum($totForma),
            'totDevolvido' => $totDevolvido,
            'nPax' => $nPax, 'nIdosos' => $nIdosos, 'nColos' => $nColos, 'nDevolvidas' => $nDevolvidas,
        ];
    }
}
