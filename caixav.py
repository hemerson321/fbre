f='app/Http/Controllers/PassagemController.php'
s=open(f,encoding='utf-8').read()
anchor="    public function minhasVendas(Request $request)"
assert anchor in s, "anchor minhasVendas nao achado"
block='''    /** Caixa por viagem: manifesto financeiro (passageiros + vendedor + valor + forma) com totais por forma de pagamento. */
    public function caixaViagem(Request $request)
    {
        $f = $this->filtros($request);
        unset($f['cancelada']); // inclui as devolvidas/canceladas no relatorio
        $passagens = Passagem::filtrar($f)
            ->with(['passageiros', 'operador', 'pagamentos', 'embarcacao', 'camarote'])
            ->orderBy('data_viagem')->orderBy('origem')->orderBy('id')->get();

        $linhas = collect();
        $totForma = [];      // meio => soma recebida
        $totDesconto = 0.0;  // desconto total
        $totGeral = 0.0;     // soma dos totais (vendas nao canceladas)
        $totDevolvido = 0.0; // soma das canceladas/devolvidas
        $nPax = 0; $nIdosos = 0; $nColos = 0; $nDevolvidas = 0;
        $nr = 0;

        foreach ($passagens as $p) {
            $cancelada = (bool) $p->cancelada;
            $meios = $p->pagamentos->where('meio', '!=', 'desconto')->groupBy('meio');
            $formaTxt = $meios->isNotEmpty()
                ? $meios->keys()->map(fn ($m) => \\App\\Models\\Pagamento::MEIOS[$m] ?? ucfirst($m))->implode(', ')
                : (\\App\\Models\\Passagem::FORMAS[$p->forma] ?? ucfirst((string) $p->forma));
            $temDinheiro = $p->pagamentos->where('meio', 'dinheiro')->where('valor', '>', 0)->isNotEmpty();
            $ehFaturado = in_array($p->forma, ['faturado', 'areceber'], true);

            foreach ($p->passageiros as $pax) {
                $ehColo = ($pax->papel ?? '') === 'colo';
                $cancPax = $cancelada || (bool) ($pax->cancelado ?? false);
                $nr++;
                $tipoTxt = (string) ($pax->papel ?: $pax->tipo);
                $ehIdoso = mb_stripos($tipoTxt, 'idoso') !== false;
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
                    'status' => $cancPax ? 'Devolvida' : ((float) $p->devido > 0.009 ? 'Em aberto' : 'Paga'),
                    'forma' => $formaTxt,
                    'dinheiro' => $temDinheiro,
                    'faturado' => $ehFaturado,
                    'cliente' => (string) ($p->comprador_nome ?? ''),
                    'poltrona' => (string) ($pax->poltrona ?? ''),
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

        $totRecebido = array_sum($totForma);
        $embs = $passagens->map(fn ($p) => $p->embarcacao?->nome)->filter()->unique()->values();
        $datas = $passagens->pluck('data_viagem')->filter()->unique()->values();

        return view('passagens.caixa_viagem', [
            'linhas' => $linhas,
            'totForma' => $totForma,
            'totDesconto' => $totDesconto,
            'totGeral' => $totGeral,
            'totRecebido' => $totRecebido,
            'totDevolvido' => $totDevolvido,
            'nPax' => $nPax, 'nIdosos' => $nIdosos, 'nColos' => $nColos, 'nDevolvidas' => $nDevolvidas,
            'embs' => $embs, 'datas' => $datas, 'f' => $f,
            'auto' => $request->boolean('print'),
        ]);
    }

    public function minhasVendas(Request $request)'''
if 'function caixaViagem' not in s: s=s.replace(anchor, block, 1)
open(f,'w',encoding='utf-8').write(s)
print("metodo:", 'function caixaViagem' in s)
