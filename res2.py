f='resources/views/passagens/comprovante.blade.php'
s=open(f,encoding='utf-8').read()
orig=s
a="    if(i>=els.length){ try{ parent.postMessage({fbreRaster:true, imagens:out}, '*'); }catch(e){} return; }"
b='''    if(i>=els.length){
      var rz=document.querySelector('.resumo-venda');
      if(!rz){ try{ parent.postMessage({fbreRaster:true, imagens:out, resumo:null}, '*'); }catch(e){} return; }
      h2c(rz, {scale:2, backgroundColor:'#ffffff', useCORS:true, logging:false}).then(function(cv){
        var ri=null; try{ ri=cv.toDataURL('image/png'); }catch(e){}
        try{ parent.postMessage({fbreRaster:true, imagens:out, resumo:ri}, '*'); }catch(e){}
      }).catch(function(){ try{ parent.postMessage({fbreRaster:true, imagens:out, resumo:null}, '*'); }catch(e){} });
      return;
    }'''
assert a in s, "anchor rasterTickets nao achado"
if 'querySelector(\'.resumo-venda\')' not in s:
    s=s.replace(a,b,1)
open(f,'w',encoding='utf-8').write(s)
print("changed:", s!=orig)
