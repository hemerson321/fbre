f='app/Http/Controllers/PassagemController.php'
s=open(f,encoding='utf-8').read()
orig=s

# 1) ramificacao no inicio do cancelar()
a1='''        $data = $request->validate(['motivo' => 'required|string|min:3|max:300'], ['motivo.required' => 'Informe o motivo.']);
        abort_if($passagem->cancelada, 422, 'Passagem já cancelada.');
        $empresa = \\App\\Support\\Tenant::empresa();'''
b1='''        $data = $request->validate(['motivo' => 'required|string|min:3|max:300'], ['motivo.required' => 'Informe o motivo.']);
        abort_if($passagem->cancelada, 422, 'Passagem já cancelada.');

        // Cancelamento PARCIAL: só alguns passageiros (quando há 2+). Se marcar todos, cai no fluxo normal abaixo.
        $paxIds = array_values(array_filter(array_map('intval', (array) $request->input('pax_ids', []))));
        if ($request->input('escopo_cancel') === 'parcial' && $paxIds) {
            $pagantes = $passagem->passageiros()->whereRaw("COALESCE(papel,'') <> 'colo'")->pluck('id')->all();
            $selec = array_values(array_intersect($paxIds, $pagantes));
            if (empty($selec)) return back()->with('erro', 'Selecione ao menos um passageiro válido.');
            if (count($selec) < count($pagantes)) {
                return $this->cancelarPassageirosParcial($passagem, $selec, $data['motivo']);
            }
            // selecionou todos -> segue cancelamento total normal
        }

        $empresa = \\App\\Support\\Tenant::empresa();'''
if 'escopo_cancel' not in s and a1 in s: s=s.replace(a1,b1,1)

# 2) novo metodo parcial (antes de function transferir)
a2="    /** Transfere a passagem pra outro transporte: cria nova (paga) no destino e lanca a despesa no transporte de origem. */"
b2='''    /** Cancela apenas alguns passageiros da passagem: remove, libera poltrona e recalcula. Devolução em dinheiro é manual no caixa. */
    protected function cancelarPassageirosParcial(Passagem $passagem, array $ids, string $motivo)
    {
        $alvos = $passagem->passageiros()->whereIn('id', $ids)->get();
        if ($alvos->isEmpty()) return back()->with('erro', 'Passageiro(s) não encontrado(s).');
        $nomes = $alvos->pluck('nome')->map(fn ($n) => trim((string) $n))->filter()->implode(', ');
        $valor = (float) $alvos->sum('valor_total');

        \\Illuminate\\Support\\Facades\\DB::transaction(function () use ($passagem, $ids) {
            $passagem->passageiros()->whereIn('colo_de_id', $ids)->delete(); // bebês de colo ligados
            $passagem->passageiros()->whereIn('id', $ids)->delete();
            $passagem->recalcular();
        });

        $nota = '['.now()->format('d/m/Y H:i').'] Cancelado(s): '.$nomes.' — '.$motivo.' ('.(auth()->user()->name ?? '').'). Valor: R$ '.number_format($valor, 2, ',', '.').'.';
        $obs = trim((string) $passagem->observacao);
        $passagem->forceFill(['observacao' => trim($obs."\\n".$nota)])->save();

        $msg = 'Passageiro(s) cancelado(s): '.$nomes.'. Poltrona liberada e valores recalculados.';
        if ($valor > 0.009) $msg .= ' Se houver devolução (R$ '.number_format($valor, 2, ',', '.').'), registre no caixa (saída avulsa).';
        return redirect()->route('passagens.show', $passagem)->with('ok', $msg);
    }

    /** Transfere a passagem pra outro transporte: cria nova (paga) no destino e lanca a despesa no transporte de origem. */'''
if 'function cancelarPassageirosParcial' not in s: s=s.replace(a2,b2,1)

open(f,'w',encoding='utf-8').write(s)
print("branch:", 'escopo_cancel' in s, "| metodo:", 'function cancelarPassageirosParcial' in s, "| changed:", s!=orig)
