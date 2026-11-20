f='resources/views/passagens/comprovante.blade.php'
s=open(f,encoding='utf-8').read()
orig=s

# 1) escala 3 -> 2 (oversampling inutil em termica de 203dpi)
a1="h2c(els[i], {scale:3, backgroundColor:'#ffffff', useCORS:true, logging:false})"
b1="h2c(els[i], {scale:2, backgroundColor:'#ffffff', useCORS:true, logging:false})"
if a1 in s: s=s.replace(a1,b1,1)

# 2) espera fixa 450ms -> espera inteligente (fontes+imagens prontas, teto 500ms)
a2="if(RASTER){ setTimeout(rasterTickets, 450); return; }"
b2=("if(RASTER){ var _imgs=Array.prototype.slice.call(document.images); "
    "var _wi=Promise.all(_imgs.filter(function(im){return !im.complete;}).map(function(im){return new Promise(function(r){im.onload=im.onerror=r;});})); "
    "var _wf=(document.fonts&&document.fonts.ready)?document.fonts.ready:Promise.resolve(); "
    "Promise.race([Promise.all([_wi,_wf]), new Promise(function(r){setTimeout(r,500);})]).then(function(){ setTimeout(rasterTickets,60); }); return; }")
if a2 in s: s=s.replace(a2,b2,1)

open(f,'w',encoding='utf-8').write(s)
print("scale2:", b1 in s, "| espera:", b2 in s, "| changed:", s!=orig)
