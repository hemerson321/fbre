f='resources/views/passagens/index.blade.php'
s=open(f,encoding='utf-8').read()
orig=s

# 1) editarUm: calcular lista estavel de tipos por passageiro (1x)
a1="            if(r.ok && j && j.ok){ this.ed=j; } else { alert((j&&j.erro)||'Nao foi possivel carregar.'); this.edModal=false; }"
b1=("            if(r.ok && j && j.ok){ this.ed=j; "
    "(this.ed.passageiros||[]).forEach(p=>{ p._tipos = (p.tipo && !this.tiposPax.includes(p.tipo)) ? [p.tipo].concat(this.tiposPax) : this.tiposPax.slice(); }); "
    "} else { alert((j&&j.erro)||'Nao foi possivel carregar.'); this.edModal=false; }")
if '_tipos =' not in s and a1 in s: s=s.replace(a1,b1,1)

# 2) select usa a lista estavel px._tipos (sem depender de px.tipo)
a2='<template x-for="t in (px.tipo && !tiposPax.includes(px.tipo) ? [px.tipo].concat(tiposPax) : tiposPax)" :key="t"><option :value="t" x-text="t"></option></template>'
b2='<template x-for="t in (px._tipos||tiposPax)" :key="t"><option :value="t" x-text="t"></option></template>'
if a2 in s: s=s.replace(a2,b2,1)

# 3) forcar o valor inicial do select (evita Alpine resetar p/ 1a opcao)
a3='<select x-show="px.papel!==\'colo\'" x-model="px.tipo" class="input !min-h-0 h-8 py-0 text-xs font-semibold" style="width:auto">'
b3='<select x-show="px.papel!==\'colo\'" x-model="px.tipo" x-init="$nextTick(()=>{ $el.value=px.tipo; })" class="input !min-h-0 h-8 py-0 text-xs font-semibold" style="width:auto">'
if a3 in s and 'x-init="$nextTick(()=>{ $el.value=px.tipo' not in s: s=s.replace(a3,b3,1)

open(f,'w',encoding='utf-8').write(s)
print("estavel:", '_tipos =' in s, "| select:", b2 in s, "| init:", '$el.value=px.tipo' in s, "| changed:", s!=orig)
