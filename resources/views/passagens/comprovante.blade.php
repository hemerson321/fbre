<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<title>Passagem {{ $p->numeroFormatado() }}</title>
<style>@page{margin:0;size:80mm auto}*{box-sizing:border-box}body{margin:0;padding:3mm 4mm;width:80mm;font-family:"Courier New",monospace;font-size:11.5px;color:#000}
.c{text-align:center}.r{text-align:right}.b{font-weight:bold}.xl{font-size:18px}.lg{font-size:14px}.sm{font-size:10px;color:#333}hr{border:0;border-top:1px dashed #000;margin:4px 0}
table{width:100%;border-collapse:collapse}td{padding:2px 0;vertical-align:top}.qrp{display:inline-block}.qrp img,.qrp canvas{margin:0 auto}
.ticket{page-break-after:always}.ticket:last-child{page-break-after:auto}
.pax-nome{font-size:16px;font-weight:bold;text-align:center;margin-top:2px}</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script></head>
<body onload="qrImprimir()">
@php $e=$empresa; $ehCam = $p->tipo==='camarote'; @endphp

@if($ehCam)
  {{-- CAMAROTE: 1 bilhete por ocupante --}}
  @php $tot=$p->passageiros->count(); @endphp
  @foreach($p->passageiros as $i => $pax)
  <div class="ticket">
    <div class="c b lg">{{ strtoupper($e?->nomeExibicao() ?? 'FBRE') }}</div>
    @if($e?->cnpj)<div class="c sm">CNPJ {{ $e->cnpj }}</div>@endif<hr>
    <div class="c b xl">CAMAROTE {{ $p->numeroFormatado() }}</div>
    <div class="c sm">Bilhete {{ $i+1 }}/{{ $tot }} · {{ $p->created_at->format('d/m/Y H:i') }} · {{ $p->operador?->name }}</div><hr>
    <div class="pax-nome">{{ strtoupper($pax->nome ?: 'PASSAGEIRO') }}</div>
    <div class="c sm">{{ ucfirst($pax->papel ?: 'ocupante') }}@if($pax->documento) · Doc {{ $pax->documento }}@endif @if($pax->nascimento) · Nasc {{ $pax->nascimento->format('d/m/Y') }}@endif</div>
    <hr>
    <table>
      <tr><td class="b">ORIGEM</td><td class="r">{{ $p->origem }}</td></tr>
      <tr><td class="b">DESTINO</td><td class="r">{{ $p->destino }}</td></tr>
      @if($p->camarote)<tr><td class="b">SUITE/CAMAROTE</td><td class="r">{{ $p->camarote->nome }}</td></tr>@endif
      @if($p->data_viagem)<tr><td class="b">VIAGEM</td><td class="r">{{ $p->data_viagem->format('d/m/Y') }}</td></tr>@endif
      @if($p->embarcacao)<tr><td class="b">BARCO</td><td class="r">{{ $p->embarcacao->nome }}</td></tr>@endif
    </table><hr>
    <div class="c"><div class="qrp" data-url="{{ route('checkin',$p) }}"></div><div class="sm b">CHECK-IN</div><div class="sm">Apresente no embarque</div></div>
    <div class="c sm">Camarote total R$ {{ number_format((float) $p->total,2,',','.') }} · {{ $p->devido>0.009 ? 'DEVE R$ '.number_format((float) $p->devido,2,',','.') : 'PAGO' }}</div>
    <br>
  </div>
  @endforeach
@else
  {{-- PASSAGEM comum: 1 bilhete por passageiro (mesmo formato da impressão direta) --}}
  @php $tot=$p->passageiros->count(); @endphp
  @foreach($p->passageiros as $i => $pax)
  <div class="ticket">
    <div class="c b lg">{{ strtoupper($e?->nomeExibicao() ?? 'FBRE') }}</div>
    @if($e?->cnpj)<div class="c sm">CNPJ {{ $e->cnpj }}</div>@endif<hr>
    <div class="c b xl">PASSAGEM {{ $p->numeroFormatado() }}</div>
    <div class="c sm">Bilhete {{ $i+1 }}/{{ $tot }} · {{ $p->created_at->format('d/m/Y H:i') }} · {{ $p->operador?->name }}</div><hr>
    <div class="pax-nome">{{ strtoupper($pax->nome ?: 'PASSAGEIRO') }}</div>
    <div class="c lg b">{{ strtoupper($pax->papel ?: $pax->tipo) }}</div>
    @php $extra=[]; if($pax->poltrona) $extra[]='Poltrona '.$pax->poltrona; if($pax->documento) $extra[]='Doc '.$pax->documento; @endphp
    @if($extra)<div class="c sm">{{ implode(' · ', $extra) }}</div>@endif
    <hr>
    <table>
      <tr><td class="b">ORIGEM</td><td class="r">{{ $p->origem }}</td></tr>
      <tr><td class="b">DESTINO</td><td class="r">{{ $p->destino }}</td></tr>
      @if($p->data_viagem)<tr><td class="b">VIAGEM</td><td class="r">{{ $p->data_viagem->format('d/m/Y') }}</td></tr>@endif
      @if($p->embarcacao)<tr><td class="b">BARCO</td><td class="r">{{ $p->embarcacao->nome }}</td></tr>@endif
    </table><hr>
    <table>
      @if($pax->valor_integral > $pax->valor_total + 0.009)<tr><td class="sm">Preço normal</td><td class="r sm">{{ number_format((float) $pax->valor_integral,2,',','.') }}</td></tr>@endif
      <tr><td>Transporte</td><td class="r">R$ {{ number_format((float) $pax->valor_passagem,2,',','.') }}</td></tr>
      @if(($pax->valor_alimentacao + $pax->valor_taxa)>0)<tr><td>Alimentação + Adicional</td><td class="r">R$ {{ number_format((float) $pax->valor_alimentacao + $pax->valor_taxa,2,',','.') }}</td></tr>@endif
      <tr class="lg"><td class="b">TOTAL</td><td class="r b">R$ {{ number_format((float) $pax->valor_total,2,',','.') }}</td></tr>
    </table>
    @if($p->devido>0.009)<div class="c sm b">PASSAGEM COM VALOR EM ABERTO</div>@endif
    @if($e?->rodape_comprovante)<hr><div class="c sm">{{ $e->rodape_comprovante }}</div>@endif
    <hr><div class="c"><div class="qrp" data-url="{{ route('checkin',$p) }}"></div><div class="sm b">CHECK-IN</div><div class="sm">Apresente no embarque</div></div><br>
  </div>
  @endforeach
@endif

<div id="pdfbar" class="c" style="display:none"><br><button onclick="gerarPdf()" style="padding:8px 14px;font-size:13px;border:1px solid #000;border-radius:8px;background:#fff">📄 Baixar / Compartilhar PDF</button></div>
<script>
var MODO=@json($modo ?? null);
function qrImprimir(){
  try{ document.querySelectorAll('.qrp').forEach(function(el){ new QRCode(el,{text:el.dataset.url,width:130,height:130,correctLevel:QRCode.CorrectLevel.M}); }); }catch(e){}
  if(MODO==='pdf'){ document.getElementById('pdfbar').style.display='block'; setTimeout(gerarPdf, 500); }
  else { setTimeout(function(){ window.print(); }, 350); }
}
function gerarPdf(){
  var bar=document.getElementById('pdfbar'); if(bar) bar.style.display='none';
  // altura da pagina = maior bilhete (px->mm) + folga, pra nao sobrar espaco vazio no PDF
  var _h=0; document.querySelectorAll('.ticket').forEach(function(el){ if(el.offsetHeight>_h) _h=el.offsetHeight; });
  var _mm=Math.ceil(_h/96*25.4)+8; if(_mm<70) _mm=70;
  var opt={ margin:[2,2,2,2], filename:'passagem-{{ $p->numeroFormatado() }}.pdf', image:{type:'jpeg',quality:0.95}, html2canvas:{scale:2,useCORS:true}, jsPDF:{unit:'mm',format:[80,_mm],orientation:'portrait'}, pagebreak:{mode:['css','legacy']} };
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
