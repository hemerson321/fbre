f='app/Http/Controllers/PassagemController.php'
s=open(f,encoding='utf-8').read()
orig=s

# 1) substitui o trim inline do atualizar por chamada ao helper
a1="""            $passagem->recalcular();

            // Se o valor caiu na edição e ficou pago A MAIS, reduz o pagamento pra bater com o novo total.
            $passagem->refresh();
            $excesso = round((float) $passagem->pago - (float) $passagem->total, 2);
            if ($excesso > 0.009) {
                foreach ($passagem->pagamentos()->where('meio', '!=', 'desconto')->orderByDesc('id')->get() as $pg) {
                    if ($excesso <= 0.009) break;
                    $corte = min((float) $pg->valor, $excesso);
                    $novo = round((float) $pg->valor - $corte, 2);
                    if ($novo <= 0.009) $pg->delete(); else $pg->forceFill(['valor' => $novo])->save();
                    $excesso = round($excesso - $corte, 2);
                }
                $passagem->recalcular();
            }
        });"""
b1="""            $passagem->recalcular();
            $this->ajustarPagamentoAoTotal($passagem); // pago desce pra bater com o novo total
        });"""
if a1 in s: s=s.replace(a1,b1,1)

# 2) adiciona o helper + chama no cancelamento parcial
a2="""            $passagem->passageiros()->whereIn('id', $ids)->update($marca);
            $passagem->recalcular();
        });"""
b2="""            $passagem->passageiros()->whereIn('id', $ids)->update($marca);
            $passagem->recalcular();
            $this->ajustarPagamentoAoTotal($passagem); // pago desce pra bater (cancelou passageiro)
        });"""
if a2 in s: s=s.replace(a2,b2,1)

# 3) define o helper antes de cancelarPassageirosParcial
a3="    /** Cancela apenas alguns passageiros da passagem: remove, libera poltrona e recalcula. Devolução em dinheiro é manual no caixa. */\n    protected function cancelarPassageirosParcial"
b3='''    /** Se o pago ficou MAIOR que o total (edição baixou valor ou cancelou passageiro), reduz o pagamento registrado pra bater. */
    protected function ajustarPagamentoAoTotal(Passagem $passagem): void
    {
        $passagem->refresh();
        $excesso = round((float) $passagem->pago - (float) $passagem->total, 2);
        if ($excesso <= 0.009) return;
        foreach ($passagem->pagamentos()->where('meio', '!=', 'desconto')->orderByDesc('id')->get() as $pg) {
            if ($excesso <= 0.009) break;
            $corte = min((float) $pg->valor, $excesso);
            $novo = round((float) $pg->valor - $corte, 2);
            if ($novo <= 0.009) $pg->delete(); else $pg->forceFill(['valor' => $novo])->save();
            $excesso = round($excesso - $corte, 2);
        }
        $passagem->recalcular();
    }

    /** Cancela apenas alguns passageiros da passagem: remove, libera poltrona e recalcula. Devolução em dinheiro é manual no caixa. */
    protected function cancelarPassageirosParcial'''
if 'function ajustarPagamentoAoTotal' not in s: s=s.replace(a3,b3,1)

open(f,'w',encoding='utf-8').write(s)
print("helper:", 'function ajustarPagamentoAoTotal' in s, "| atualizar-call:", s.count('$this->ajustarPagamentoAoTotal($passagem)'), "| changed:", s!=orig)
