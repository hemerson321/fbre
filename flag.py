# ---- PassagemController ----
f='app/Http/Controllers/PassagemController.php'
s=open(f,encoding='utf-8').read()
orig=s

# 1) parcial: flag em vez de delete
a1='''        \\Illuminate\\Support\\Facades\\DB::transaction(function () use ($passagem, $ids) {
            $passagem->passageiros()->whereIn('colo_de_id', $ids)->delete(); // bebês de colo ligados
            $passagem->passageiros()->whereIn('id', $ids)->delete();
            $passagem->recalcular();
        });'''
b1='''        \\Illuminate\\Support\\Facades\\DB::transaction(function () use ($passagem, $ids, $motivo) {
            $marca = ['cancelado' => true, 'cancelado_em' => now(), 'cancelado_motivo' => $motivo];
            $passagem->passageiros()->whereIn('colo_de_id', $ids)->update($marca); // bebês de colo ligados
            $passagem->passageiros()->whereIn('id', $ids)->update($marca);
            $passagem->recalcular();
        });'''
if "'cancelado' => true, 'cancelado_em' => now(), 'cancelado_motivo' => $motivo]" not in s and a1 in s: s=s.replace(a1,b1,1)

# 2) ocupadas: exclui cancelados
a2="with('passageiros:id,passagem_id,poltrona')"
b2="with(['passageiros' => fn ($q) => $q->where('cancelado', false)->select('id', 'passagem_id', 'poltrona')])"
if a2 in s: s=s.replace(a2,b2,1)

open(f,'w',encoding='utf-8').write(s)
print("parcial-flag:", "'cancelado' => true, 'cancelado_em' => now(), 'cancelado_motivo' => $motivo]" in s, "| ocupadas:", b2 in s)

# ---- Passagem model recalcular ----
f2='app/Models/Passagem.php'
s2=open(f2,encoding='utf-8').read()
reps=[
 ("$subtotal = (float) $this->passageiros()->sum('valor_passagem');","$subtotal = (float) $this->passageiros()->where('cancelado', false)->sum('valor_passagem');"),
 ("$taxa = (float) $this->passageiros()->sum('valor_taxa');","$taxa = (float) $this->passageiros()->where('cancelado', false)->sum('valor_taxa');"),
 ("$alim = (float) $this->passageiros()->sum('valor_alimentacao');","$alim = (float) $this->passageiros()->where('cancelado', false)->sum('valor_alimentacao');"),
 ("'qtd_passageiros' => $this->passageiros()->count(),","'qtd_passageiros' => $this->passageiros()->where('cancelado', false)->count(),"),
]
for a,b in reps:
    if a in s2 and b not in s2: s2=s2.replace(a,b,1)
open(f2,'w',encoding='utf-8').write(s2)
print("recalcular filtros:", s2.count("where('cancelado', false)"))
