<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<title>Passagem {{ $p->numeroFormatado() }}</title>
<style>@page{margin:0;size:80mm auto}*{box-sizing:border-box}body{margin:0;padding:3mm 4mm;width:80mm;font-family:"Courier New",monospace;font-size:11.5px;color:#000}
.c{text-align:center}.r{text-align:right}.b{font-weight:bold}.xl{font-size:18px}.lg{font-size:14px}.sm{font-size:10px;color:#333}hr{border:0;border-top:1px dashed #000;margin:4px 0}
table{width:100%;border-collapse:collapse}td{padding:2px 0;vertical-align:top}#qr{display:inline-block}#qr img,#qr canvas{margin:0 auto}</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script></head>
<body onload="qrImprimir()">
@php $e=$empresa; @endphp
<div class="c b lg">{{ strtoupper($e?->nomeExibicao() ?? 'FBRE') }}</div>
@if($e?->cnpj)<div class="c sm">CNPJ {{ $e->cnpj }}</div>@endif<hr>
<div class="c b xl">PASSAGEM {{ $p->numeroFormatado() }}</div>
<div class="c sm">{{ $p->created_at->format('d/m/Y H:i') }} · {{ $p->operador?->name }}</div><hr>
<table>
  <tr><td class="b">ORIGEM</td><td class="r">{{ $p->origem }}</td></tr>
  <tr><td class="b">DESTINO</td><td class="r">{{ $p->destino }}</td></tr>
  @if($p->data_viagem)<tr><td class="b">VIAGEM</td><td class="r">{{ $p->data_viagem->format('d/m/Y') }}</td></tr>@endif
  @if($p->embarcacao)<tr><td class="b">BARCO</td><td class="r">{{ $p->embarcacao->nome }}</td></tr>@endif
</table><hr>
<div class="b">PASSAGEIROS</div>
<table>
  @foreach($p->passageiros as $pax)
  <tr><td>{{ $pax->nome }}<div class="sm">{{ $pax->tipo }}@if($pax->poltrona) · poltrona {{ $pax->poltrona }}@endif</div>@if($pax->valor_integral > $pax->valor_total + 0.009)<div class="sm">Preço normal {{ number_format($pax->valor_integral,2,',','.') }} · Pago {{ number_format($pax->valor_total,2,',','.') }}</div>@endif<div class="sm">Transp. {{ number_format($pax->valor_passagem,2,',','.') }}@if(($pax->valor_alimentacao + $pax->valor_taxa)>0) · Alim.+Adic. {{ number_format($pax->valor_alimentacao + $pax->valor_taxa,2,',','.') }}@endif</div></td><td class="r b">{{ number_format($pax->valor_total,2,',','.') }}</td></tr>
  @endforeach
</table><hr>
<table>
  <tr><td>Tarifa de Transporte</td><td class="r">R$ {{ number_format($p->subtotal,2,',','.') }}</td></tr>
  <tr><td>Alimentação + Adicional</td><td class="r">R$ {{ number_format($p->alimentacao_total + $p->taxa_total,2,',','.') }}</td></tr>
  @if($p->desconto>0)<tr><td>Desconto</td><td class="r">-{{ number_format($p->desconto,2,',','.') }}</td></tr>@endif
  <tr class="lg"><td class="b">TOTAL</td><td class="r b">R$ {{ number_format($p->total,2,',','.') }}</td></tr>
  <tr><td>Pago</td><td class="r">R$ {{ number_format($p->pago,2,',','.') }}</td></tr>
  <tr><td class="b">DEVIDO</td><td class="r b">R$ {{ number_format($p->devido,2,',','.') }}</td></tr>
</table>
@if($e?->rodape_comprovante)<hr><div class="c sm">{{ $e->rodape_comprovante }}</div>@endif
<hr><div class="c"><div id="qr"></div><div class="sm b">CHECK-IN</div><div class="sm">Apresente no embarque</div></div><br><br>
<div id="pdfbar" class="c" style="display:none"><br><button onclick="gerarPdf()" style="padding:8px 14px;font-size:13px;border:1px solid #000;border-radius:8px;background:#fff">📄 Baixar / Compartilhar PDF</button></div>
<script>
var MODO=@json($modo ?? null);
function qrImprimir(){
  try{ new QRCode(document.getElementById('qr'),{text:@json(route('checkin',$p)),width:130,height:130,correctLevel:QRCode.CorrectLevel.M}); }catch(e){}
  if(MODO==='pdf'){ document.getElementById('pdfbar').style.display='block'; setTimeout(gerarPdf, 500); }
  else { setTimeout(function(){ window.print(); }, 350); }
}
function gerarPdf(){
  var bar=document.getElementById('pdfbar'); if(bar) bar.style.display='none';
  var opt={ margin:[2,2,2,2], filename:'passagem-{{ $p->numeroFormatado() }}.pdf', image:{type:'jpeg',quality:0.95}, html2canvas:{scale:2,useCORS:true}, jsPDF:{unit:'mm',format:[80,260],orientation:'portrait'} };
  html2pdf().set(opt).from(document.body).toPdf().output('blob').then(async function(blob){
    if(bar) bar.style.display='block';
    try{
      var file=new File([blob],opt.filename,{type:'application/pdf'});
      if(navigator.canShare && navigator.canShare({files:[file]})){ await navigator.share({files:[file], title:'Passagem {{ $p->numeroFormatado() }}'}); return; }
    }catch(e){}
    var url=URL.createObjectURL(blob); var a=document.createElement('a'); a.href=url; a.download=opt.filename; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
  }).catch(function(e){ alert('Erro ao gerar PDF: '+e.message); });
}
</script>
</body></html>
