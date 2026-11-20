f='resources/views/passagens/create.blade.php'
s=open(f,encoding='utf-8').read()
orig=s

# A) _rasterComprovante -> resolve {imagens, resumo}
a1="        const fim=(imgs)=>{ if(done)return; done=true; window.removeEventListener('message',onMsg); try{ifr.remove();}catch(e){} resolve(imgs||[]); };\n          const onMsg=(ev)=>{ const d=ev.data; if(!d||!d.fbreRaster)return; fim(d.imagens||[]); };"
# a indentacao do onMsg pode variar; faz em dois passos
a1a="const fim=(imgs)=>{ if(done)return; done=true; window.removeEventListener('message',onMsg); try{ifr.remove();}catch(e){} resolve(imgs||[]); };"
b1a="const fim=(res)=>{ if(done)return; done=true; window.removeEventListener('message',onMsg); try{ifr.remove();}catch(e){} resolve(res||{imagens:[],resumo:null}); };"
if a1a in s: s=s.replace(a1a,b1a,1)

a1b="const onMsg=(ev)=>{ const d=ev.data; if(!d||!d.fbreRaster)return; fim(d.imagens||[]); };"
b1b="const onMsg=(ev)=>{ const d=ev.data; if(!d||!d.fbreRaster)return; fim({imagens:d.imagens||[], resumo:d.resumo||null}); };"
if a1b in s: s=s.replace(a1b,b1b,1)

a1c="setTimeout(()=>fim([]), 20000);"
b1c="setTimeout(()=>fim({imagens:[],resumo:null}), 20000);"
if a1c in s: s=s.replace(a1c,b1c,1)

# B) imprimirDiretoENovo -> usa objeto e anexa resumo 1x
a2="          let imagens = await this._rasterComprovante('/passagens/'+id+'/comprovante');\n          if(imagens && imagens.length && vias>1){ let __t=[]; for(let __v=0; __v<vias; __v++) __t=__t.concat(imagens); imagens=__t; }"
b2="          const __rz = await this._rasterComprovante('/passagens/'+id+'/comprovante');\n          let imagens = (__rz && __rz.imagens) ? __rz.imagens.slice() : [];\n          if(imagens.length && vias>1){ let __t=[]; for(let __v=0; __v<vias; __v++) __t=__t.concat(imagens); imagens=__t; }\n          if(__rz && __rz.resumo) imagens.push(__rz.resumo);"
if a2 in s: s=s.replace(a2,b2,1)

open(f,'w',encoding='utf-8').write(s)
print("fim:", b1a in s, "| onMsg:", b1b in s, "| timeout:", b1c in s, "| diretoNovo:", '__rz.resumo) imagens.push' in s, "| changed:", s!=orig)
