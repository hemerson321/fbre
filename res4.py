f='resources/views/passagens/show.blade.php'
s=open(f,encoding='utf-8').read()
a="    let imgs = d.imagens; if(vias>1){ let t=[]; for(let v=0;v<vias;v++) t=t.concat(d.imagens); imgs=t; }"
b="    let imgs = d.imagens.slice(); if(vias>1){ let t=[]; for(let v=0;v<vias;v++) t=t.concat(d.imagens); imgs=t; }\n    if(d.resumo) imgs.push(d.resumo);"
assert a in s, "anchor show nao achado"
if 'd.resumo) imgs.push' not in s:
    s=s.replace(a,b,1)
    open(f,'w',encoding='utf-8').write(s)
    print("show OK")
else:
    print("show JA tinha")
