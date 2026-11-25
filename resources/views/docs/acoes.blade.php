{{-- Barra de ações reutilizável p/ documentos (recibo, orçamento). Params: $arquivo, $titulo --}}
@php $arquivo = $arquivo ?? 'documento.pdf'; $titulo = $titulo ?? 'Documento'; @endphp
<style>
  #barra{position:sticky;bottom:0;z-index:50;background:#0b2447;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;padding:12px 14px;box-shadow:0 -6px 18px rgba(0,0,0,.2)}
  #barra button{cursor:pointer;border:0;border-radius:10px;padding:10px 16px;font-size:13px;font-weight:800;font-family:Helvetica,Arial,sans-serif}
  #barra .b-print{background:#fff;color:#0b2447}
  #barra .b-pdf{background:#2aa8e0;color:#fff}
  #barra .b-wa{background:#22c55e;color:#fff}
  #barra .b-img{background:#f5a623;color:#3a2600}
  @media print{ #barra{display:none!important} body{background:#fff!important} }
  @media (max-width:820px){ #folha{zoom:.9} }
</style>
<div id="barra">
  <button type="button" class="b-print" onclick="DOC.imprimir()">🖨 Imprimir</button>
  <button type="button" class="b-pdf" onclick="DOC.pdf(this)">📄 Baixar PDF</button>
  <button type="button" class="b-wa" onclick="DOC.whats(this)">📲 Enviar no WhatsApp</button>
  <button type="button" class="b-img" onclick="DOC.copiar(this)">🖼️ Copiar imagem</button>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
(function(){
  var NOME=@json($arquivo), TITULO=@json($titulo);
  var MODO=new URLSearchParams(location.search).get('modo');
  var _blob=null,_file=null;
  function alvo(){ return document.getElementById('folha'); }
  function opt(){ return {margin:0,filename:NOME,image:{type:'jpeg',quality:0.96},html2canvas:{scale:2,useCORS:true,backgroundColor:'#ffffff'},jsPDF:{unit:'mm',format:'a4',orientation:'portrait'},pagebreak:{mode:['css']}}; }
  function gerar(cb,err){ try{ html2pdf().set(opt()).from(alvo()).toPdf().output('blob').then(function(b){ _blob=b; try{_file=new File([b],NOME,{type:'application/pdf'});}catch(e){_file=null;} cb&&cb(); }).catch(function(e){ (err||function(m){alert(m);})('Erro ao gerar PDF: '+e.message); }); }catch(e){ (err||function(m){alert(m);})('Erro: '+e.message); } }
  function baixar(blob,nome){ var u=URL.createObjectURL(blob); var a=document.createElement('a'); a.href=u; a.download=nome; document.body.appendChild(a); a.click(); a.remove(); setTimeout(function(){URL.revokeObjectURL(u);},1500); }
  function busy(btn,on,txt){ if(!btn||!btn.innerHTML){return;} if(on){ btn._t=btn.innerHTML; btn.disabled=true; btn.innerHTML='⏳'; } else { btn.innerHTML=txt||btn._t; setTimeout(function(){ btn.innerHTML=btn._t; btn.disabled=false; },1300); } }
  window.DOC={
    imprimir:function(){ window.print(); },
    pdf:function(btn){ busy(btn,true); gerar(function(){ baixar(_blob,NOME); busy(btn,false,'✅'); }, function(m){ busy(btn,false,'✖'); alert(m); }); },
    whats:function(btn){ busy(btn,true); gerar(function(){ busy(btn,false,'✅'); if(_file && navigator.canShare && navigator.canShare({files:[_file]})){ navigator.share({files:[_file],title:TITULO,text:TITULO}).catch(function(){}); } else { baixar(_blob,NOME); alert('PDF baixado. No WhatsApp, toque no clipe 📎 e anexe o PDF pra enviar.'); } }, function(m){ busy(btn,false,'✖'); alert(m); }); },
    copiar:function(btn){ busy(btn,true); try{ html2canvas(alvo(),{scale:2,useCORS:true,backgroundColor:'#ffffff'}).then(function(cv){ cv.toBlob(function(blob){ if(!blob){ busy(btn,false,'✖'); return; } if(navigator.clipboard && window.ClipboardItem){ navigator.clipboard.write([new ClipboardItem({'image/png':blob})]).then(function(){ busy(btn,false,'✅'); }).catch(function(){ baixar(blob,NOME.replace('.pdf','.png')); busy(btn,false,'✅'); }); } else { baixar(blob,NOME.replace('.pdf','.png')); busy(btn,false,'✅'); } },'image/png'); }).catch(function(e){ busy(btn,false,'✖'); alert('Erro: '+e.message); }); }catch(e){ busy(btn,false,'✖'); alert('Erro: '+e.message); } }
  };
  if(MODO==='pdf'){ setTimeout(function(){ gerar(function(){ if(_file && navigator.canShare && navigator.canShare({files:[_file]})){ navigator.share({files:[_file],title:TITULO,text:TITULO}).catch(function(){ baixar(_blob,NOME); }); } else { baixar(_blob,NOME); } }); },450); }
})();
</script>
