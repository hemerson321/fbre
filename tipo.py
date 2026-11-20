f='app/Http/Controllers/PassagemController.php'
s=open(f,encoding='utf-8').read()
orig=s

# 1) define $tipoCancel apos abort_if cancelada (antes do bloco parcial) + passa pro parcial
a1="""        abort_if($passagem->cancelada, 422, 'Passagem já cancelada.');

        // Cancelamento PARCIAL: só alguns passageiros (quando há 2+). Se marcar todos, cai no fluxo normal abaixo."""
b1="""        abort_if($passagem->cancelada, 422, 'Passagem já cancelada.');
        $tipoCancel = $request->input('cancel_tipo') === 'cancelamento' ? 'cancelamento' : 'devolucao';

        // Cancelamento PARCIAL: só alguns passageiros (quando há 2+). Se marcar todos, cai no fluxo normal abaixo."""
if '$tipoCancel =' not in s and a1 in s: s=s.replace(a1,b1,1)

# 2) passa tipo pro parcial
a2="return $this->cancelarPassageirosParcial($passagem, $selec, $data['motivo']);"
b2="return $this->cancelarPassageirosParcial($passagem, $selec, $data['motivo'], $tipoCancel);"
if a2 in s: s=s.replace(a2,b2,1)

# 3) assinatura do metodo + grava tipo
a3="protected function cancelarPassageirosParcial(Passagem $passagem, array $ids, string $motivo)"
b3="protected function cancelarPassageirosParcial(Passagem $passagem, array $ids, string $motivo, string $tipo = 'devolucao')"
if a3 in s: s=s.replace(a3,b3,1)
a4="$marca = ['cancelado' => true, 'cancelado_em' => now(), 'cancelado_motivo' => $motivo];"
b4="$marca = ['cancelado' => true, 'cancelado_em' => now(), 'cancelado_motivo' => $motivo, 'cancelado_tipo' => $tipo];"
if a4 in s: s=s.replace(a4,b4,1)
# o closure precisa de $tipo
a5="\\Illuminate\\Support\\Facades\\DB::transaction(function () use ($passagem, $ids, $motivo) {"
b5="\\Illuminate\\Support\\Facades\\DB::transaction(function () use ($passagem, $ids, $motivo, $tipo) {"
if a5 in s: s=s.replace(a5,b5,1)

# 4) full cancel: grava cancel_tipo (2 caminhos)
a6="""            $passagem->forceFill([
                'cancelada' => true, 'motivo_cancelamento' => $data['motivo'],
                'cancel_retido' => $retido, 'cancel_reembolso' => $reembolso, 'cancel_fase' => $depois ? 'depois' : 'antes',
            ])->save();"""
b6="""            $passagem->forceFill([
                'cancelada' => true, 'motivo_cancelamento' => $data['motivo'],
                'cancel_retido' => $retido, 'cancel_reembolso' => $reembolso, 'cancel_fase' => $depois ? 'depois' : 'antes',
                'cancel_tipo' => $tipoCancel,
            ])->save();"""
if a6 in s: s=s.replace(a6,b6,1)
a7="$passagem->forceFill(['cancelada' => true, 'motivo_cancelamento' => $data['motivo']])->save();"
b7="$passagem->forceFill(['cancelada' => true, 'motivo_cancelamento' => $data['motivo'], 'cancel_tipo' => $tipoCancel])->save();"
if a7 in s: s=s.replace(a7,b7,1)

open(f,'w',encoding='utf-8').write(s)
print("tipoDef:", '$tipoCancel =' in s, "| passaParcial:", '$selec, $data[\'motivo\'], $tipoCancel' in s, "| assinatura:", "string \$tipo = 'devolucao'" in s, "| marca:", "'cancelado_tipo' => \$tipo" in s, "| full1:", "'cancel_tipo' => \$tipoCancel," in s, "| full2:", "'cancel_tipo' => \$tipoCancel]" in s)
