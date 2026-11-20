f='app/Http/Controllers/PassagemController.php'
s=open(f,encoding='utf-8').read()
orig=s
# no atualizar(): apos recalcular, ajusta pagamento se sobrou pago (valor baixou na edicao)
a="""            $passagem->recalcular();
        });

        return ['ok' => true];
    }"""
b="""            $passagem->recalcular();

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
        });

        return ['ok' => true];
    }"""
assert a in s, "anchor atualizar recalcular nao achado"
if '$excesso = round((float) $passagem->pago' not in s: s=s.replace(a,b,1)
open(f,'w',encoding='utf-8').write(s)
print("fix edit:", '$excesso = round((float) $passagem->pago' in s, "| changed:", s!=orig)
