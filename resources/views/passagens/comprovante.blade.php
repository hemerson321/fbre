<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<title>Passagem {{ $p->numeroFormatado() }}</title>
<style>
@page{margin:0;size:80mm auto}
*{box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{margin:0;padding:0;width:80mm;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12px;color:#111}
.ticket{page-break-after:always;padding:4mm 4mm 5mm;position:relative;overflow:hidden}
.ticket:last-child{page-break-after:auto}
.cwm{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:60}
.cwm b{transform:rotate(-30deg);font-size:30px;font-weight:900;letter-spacing:3px;color:rgba(0,0,0,.24);border:3px solid rgba(0,0,0,.24);border-radius:6px;padding:3px 14px;white-space:nowrap}
.c{text-align:center}.muted{color:#6b7280}.b{font-weight:700}
.hdr{text-align:center;margin-bottom:4px}
.hdr img{height:80px;max-width:72mm;object-fit:contain;margin-bottom:4px}
.emp{font-weight:800;font-size:14px;letter-spacing:.02em;line-height:1.1}
.cnpj{font-size:10px;color:#6b7280}
.band{margin:6px -4mm 0;padding:5px 0;text-align:center;color:#fff;font-weight:800;font-size:12px;letter-spacing:.09em;background:var(--cor)}
.hero{display:flex;align-items:stretch;gap:6px;margin:9px 0 5px}
.tk{flex:1;border:1.5px solid var(--cor);border-radius:10px;padding:5px 9px}
.tk .l{font-size:9px;text-transform:uppercase;letter-spacing:.06em;color:var(--cor);font-weight:700}
.tk .v{font-size:16px;font-weight:800;line-height:1.1}
.pol{min-width:60px;border-radius:10px;background:var(--cor);color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:4px 8px}
.pol .l{font-size:8px;letter-spacing:.06em;opacity:.92}
.pol .v{font-size:20px;font-weight:800;line-height:1}
.trecho{text-align:center;font-size:16px;font-weight:800;margin:5px 0 1px}
.trecho .ar{color:var(--cor);margin:0 3px}
.sec{border:1px solid #e5e7eb;border-radius:10px;padding:7px 9px;margin-top:7px}
.sec.pay{border-color:var(--cor)}
.sec-h{font-size:9px;text-transform:uppercase;letter-spacing:.07em;color:var(--cor);font-weight:800;margin-bottom:4px}
.paxnome{font-size:15px;font-weight:800;line-height:1.15}
.chip{display:inline-block;background:var(--cor);color:#fff;border-radius:20px;padding:2px 9px;font-size:9px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap}
.row{display:flex;justify-content:space-between;gap:8px;font-size:11px;padding:1.5px 0}
.row .k{color:#6b7280}.row .vv{font-weight:600;text-align:right}
.total{display:flex;justify-content:space-between;align-items:center;margin-bottom:3px}
.total .tl{font-size:10px;text-transform:uppercase;color:#6b7280;font-weight:700}
.total .tv{font-size:20px;font-weight:800;color:var(--cor)}
.pg{display:inline-block;border-radius:20px;padding:1px 10px;font-size:10px;font-weight:800}
.pg.ok{background:#dcfce7;color:#166534}.pg.no{background:#fee2e2;color:#991b1b}
.qr{text-align:center;margin-top:9px}
.qrp{display:inline-block}.qrp img,.qrp canvas{margin:0 auto}
.info{margin-top:7px;font-size:10px;color:#4b5563;background:#f9fafb;border:1px solid #eef0f2;border-radius:8px;padding:6px 8px;line-height:1.35}
.foot{text-align:center;font-size:9px;color:#9ca3af;margin-top:8px;border-top:1px dashed #d1d5db;padding-top:5px}
@media print{#pdfbar,#topbar{display:none!important}}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script></head>
<body onload="qrImprimir()">
<div id="topbar" style="padding:8px 10px;background:#0b1a33;position:sticky;top:0;z-index:100">
  <button type="button" onclick="voltarComprovante()" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;font-size:14px;font-weight:700;border:0;border-radius:10px;background:#fff;color:#0b1a33;cursor:pointer">&larr; Voltar</button>
</div>
<script>function voltarComprovante(){ location.href='/passagens'; }</script>
@php
  $e=$empresa; $ehCam = $p->tipo==='camarote';
  $cor = $e?->cor_primaria ?: '#0b1a33';
  $dias = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
  $hora = $p->embarcacao?->hora_saida ? \Illuminate\Support\Str::substr($p->embarcacao->hora_saida,0,5) : null;
  $formas = $p->pagamentos->map(fn ($pg) => \App\Models\Pagamento::MEIOS[$pg->meio] ?? $pg->meio)->filter()->unique()->implode(', ');
  $nrViagem = $p->viagem?->id;
  $pagantes = $p->passageiros->filter(fn ($x) => (($x->papel ?? '') !== 'colo'))->values();
  $colos = $p->passageiros->filter(fn ($x) => (($x->papel ?? '') === 'colo'))->values();
  if ($pagantes->isEmpty()) { $pagantes = $p->passageiros->values(); $colos = collect(); }
  $tot = $pagantes->count();
  $titulo = $ehCam ? 'BILHETE · CAMAROTE' : 'BILHETE DE PASSAGEM';
@endphp

@foreach($pagantes as $i => $pax)
<div class="ticket" style="--cor:{{ $cor }}">@if($p->cancelada)<div class="cwm"><b>CANCELADO</b></div>@endif
  <div class="hdr">
    @if($e?->logo)<img src="/{{ $e->logo }}" alt="logo">@endif
    <div class="emp">{{ strtoupper($e?->nomeExibicao() ?? 'FBRE') }}</div>
    @if($e?->cnpj)<div class="cnpj">CNPJ {{ $e->cnpj }}</div>@endif
    @if($e?->telefone || $e?->whatsapp)<div class="cnpj">{{ $e->telefone }}{{ ($e->telefone && $e->whatsapp) ? ' · ' : '' }}{{ $e->whatsapp }}</div>@endif
    @if($e?->endereco || $e?->cidade)<div class="cnpj">{{ $e->endereco }}{{ ($e->endereco && $e->cidade) ? ' · ' : '' }}{{ $e->cidade }}{{ $e->uf ? '/'.$e->uf : '' }}</div>@endif
  </div>
  <div class="band">{{ $titulo }}</div>
  @if($e?->aviso_comprovante)<div style="text-align:center;font-weight:800;font-size:10.5px;line-height:1.25;text-transform:uppercase;letter-spacing:.02em;color:var(--cor);border:1.5px dashed var(--cor);border-radius:8px;padding:5px 7px;margin:5px 2px 2px">⚠️ {{ $e->aviso_comprovante }}</div>@endif

  <div class="hero">
    <div class="tk"><div class="l">Bilhete nº</div><div class="v">{{ $p->numeroFormatado() }}</div></div>
    @if($pax->poltrona)
      <div class="pol"><div class="l">POLTRONA</div><div class="v">{{ $pax->poltrona }}</div></div>
    @elseif($ehCam && $p->camarote)
      <div class="pol"><div class="l">CAMAROTE</div><div class="v" style="font-size:13px">{{ $p->camarote->nome }}</div></div>
    @endif
  </div>

  @if($p->embarcacao)<div style="text-align:center;font-size:17px;font-weight:800;color:var(--cor);text-transform:uppercase;margin:2px 0 0">🚤 {{ strtoupper($p->embarcacao->nome) }}</div>@endif
  <div class="trecho">{{ $p->origem }} <span class="ar">→</span> {{ $p->destino }}</div>

  @if($p->data_viagem || $hora)
  <div style="text-align:center;background:var(--cor);color:#fff;border-radius:11px;padding:7px 10px;margin:7px 0 3px">
    <div style="font-size:12px;font-weight:800;letter-spacing:.05em">🕒 SAÍDA @if($p->data_viagem){{ $p->data_viagem->format('d/m/Y') }} · {{ $dias[(int) $p->data_viagem->dayOfWeek] }}@endif</div>
    @if($hora)<div style="font-size:32px;font-weight:900;line-height:1.05;margin-top:3px;letter-spacing:.03em">⏰ {{ $hora }}</div>@endif
  </div>@endif

  @if($p->embarcacao?->logo || $p->camarote)
  <div class="sec">
    <div class="sec-h">Viagem</div>
    @if($p->embarcacao?->logo)<div style="text-align:center;margin:2px 0 4px"><img src="/{{ $p->embarcacao->logo }}" alt="barco" style="height:30px;max-width:46mm;object-fit:contain"></div>@endif
    @if($p->camarote)<div class="row"><span class="k">Suíte / Camarote</span><span class="vv">{{ $p->camarote->nome }}</span></div>@endif
  </div>
  @endif

  <div class="sec">
    <div class="sec-h">Passageiro</div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:6px">
      <div class="paxnome">{{ strtoupper($pax->nome ?: 'PASSAGEIRO') }}</div>
      <span class="chip">{{ strtoupper($pax->papel ?: $pax->tipo) }}</span>
    </div>
    @if($pax->documento)<div class="row"><span class="k">{{ $pax->doc_tipo ?: 'Documento' }}</span><span class="vv">{{ $pax->documento }}</span></div>@endif
    @if($pax->nascimento)<div class="row"><span class="k">Nascimento</span><span class="vv">{{ $pax->nascimento->format('d/m/Y') }}</span></div>@endif
    @if($pax->nacionalidade)<div class="row"><span class="k">Nacionalidade</span><span class="vv">{{ $pax->nacionalidade }}</span></div>@endif
    @if($pax->telefone)<div class="row"><span class="k">Telefone</span><span class="vv">{{ $pax->telefone }}</span></div>@endif
    @if($pax->observacao)<div style="margin-top:5px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:5px 8px;font-size:11px;line-height:1.3"><span class="b" style="color:#92400e">📌 Observação:</span> {{ $pax->observacao }}</div>@endif
    @php $meusColos = $colos->filter(fn ($b) => $b->colo_de_id ? ((int) $b->colo_de_id === (int) $pax->id) : ($i === 0)); @endphp
    @foreach($meusColos as $b)
      <div class="row"><span class="k">👶 Colo</span><span class="vv b">{{ strtoupper($b->nome ?: 'BEBÊ') }}@if($b->nascimento)<span style="font-weight:400;color:#555"> · nasc. {{ \Illuminate\Support\Carbon::parse($b->nascimento)->format('d/m/Y') }}</span>@endif</span></div>
    @endforeach
  </div>

  <div class="sec pay">
    <div class="sec-h">Pagamento</div>
    <div class="total"><span class="tl">Valor total</span><span class="tv">R$ {{ number_format((float) $pax->valor_total,2,',','.') }}</span></div>
    <div class="row"><span class="k">Situação</span><span class="vv">@if($p->cancelada)<span class="pg no">CANCELADA</span>@elseif($p->devido > 0.009)<span class="pg no">EM ABERTO</span>@else<span class="pg ok">PAGO</span>@endif</span></div>
    <div class="row"><span class="k">Forma de pagamento</span><span class="vv b">{{ \App\Models\Passagem::FORMAS[$p->forma] ?? ucfirst((string) $p->forma) }}</span></div>
    @php $pgs = $p->pagamentos->groupBy('meio')->map(fn ($g) => (float) $g->sum('valor'))->filter(fn ($v) => $v > 0.009); @endphp
    @if($pgs->count())
      @foreach($pgs as $meio => $val)<div class="row"><span class="k">{{ \App\Models\Pagamento::MEIOS[$meio] ?? ucfirst($meio) }}</span><span class="vv">R$ {{ number_format($val,2,',','.') }}</span></div>@endforeach
    @elseif($formas)<div class="row"><span class="k">Forma</span><span class="vv">{{ $formas }}</span></div>@endif
    <div class="row"><span class="k">Vendedor</span><span class="vv">{{ $p->operador?->name ?? '—' }}</span></div>
    <div class="row"><span class="k">Compra</span><span class="vv">{{ $p->created_at->format('d/m/Y H:i') }}</span></div>
    <div class="row"><span class="k">Impressão</span><span class="vv">{{ now()->format('d/m/Y H:i:s') }}</span></div>
    @if($pax->valor_integral > $pax->valor_total + 0.009)<div class="row"><span class="k">Preço normal</span><span class="vv">R$ {{ number_format((float) $pax->valor_integral,2,',','.') }}</span></div>@endif
  </div>

  @php $trackUrl = trim((string) ($p->embarcacao?->gps_url ?? '')) ?: null; @endphp
  <div class="qr">
    @if($trackUrl)
    <div style="margin-top:3px">
      <div style="border:1.5px solid var(--cor);border-radius:10px;padding:7px 6px 6px">
        <div class="qrp" data-url="{{ route('checkin',$p) }}" data-size="124"></div>
        <div class="b" style="font-size:11px;color:var(--cor);letter-spacing:.05em">CHECK-IN</div>
        <div class="muted" style="font-size:8.5px">Apresente este QR no embarque</div>
      </div>
      <div style="border:1.5px solid #047857;border-radius:10px;padding:7px 6px 6px;margin-top:7px">
        <div class="qrp" data-url="{{ $trackUrl }}" data-size="124"></div>
        <div class="b" style="font-size:11px;color:#047857;letter-spacing:.05em">ACOMPANHE A VIAGEM</div>
        <div class="muted" style="font-size:8.5px">Siga o barco em tempo real</div>
      </div>
    </div>
    @else
    <div class="qrp" data-url="{{ route('checkin',$p) }}"></div>
    <div class="b" style="font-size:10px;color:var(--cor);letter-spacing:.05em">CHECK-IN</div>
    <div class="muted" style="font-size:9px">Apresente este QR no embarque</div>
    @endif
  </div>

  @if($e?->rodape_comprovante)<div class="info"><div class="b" style="font-size:9px;text-transform:uppercase;letter-spacing:.06em;color:#374151;margin-bottom:2px">ℹ️ Informações importantes</div>{{ $e->rodape_comprovante }}</div>@endif
  @if($e && $e->config('usa_cancel_transporte'))<div class="info">Devolução: antes da saída retém {{ $p->embarcacao?->cancel_antes_pct ?? $e->config('cancel_antes_pct',10) }}%, depois da saída retém {{ $p->embarcacao?->cancel_depois_pct ?? $e->config('cancel_depois_pct',30) }}%.</div>@endif

  <div class="foot">Bilhete {{ $i+1 }}/{{ $tot }} · Emitido {{ now()->format('d/m/Y H:i') }} · {{ $p->operador?->name }}</div>
</div>
@endforeach


<div id="pdfbar" style="display:none;padding:10px 6px 22px">
  <button id="btEnviar" onclick="enviarPdf()" style="width:100%;max-width:300px;margin:0 auto;display:block;padding:13px 16px;font-size:15px;font-weight:bold;border:0;border-radius:12px;background:#22c55e;color:#fff">📲 Enviar / Compartilhar PDF</button>
  <div id="pdfStatus" style="text-align:center;font-size:11px;color:#666;margin-top:7px">preparando o PDF…</div>
</div>
<script>
var MODO=@json($modo ?? null);
var _pdfBlob=null,_pdfFile=null,_pdfName='passagem-{{ $p->numeroFormatado() }}.pdf';
var RASTER = new URLSearchParams(location.search).has('raster');
function rasterTickets(){
  var h2c = window.html2canvas;
  if(!h2c){ try{ parent.postMessage({fbreRaster:true, imagens:[], erro:'sem html2canvas'}, '*'); }catch(e){} return; }
  var els = Array.prototype.slice.call(document.querySelectorAll('.ticket'));
  var out = [];
  (function next(i){
    if(i>=els.length){ try{ parent.postMessage({fbreRaster:true, imagens:out}, '*'); }catch(e){} return; }
    h2c(els[i], {scale:3, backgroundColor:'#ffffff', useCORS:true, logging:false}).then(function(cv){
      try{ out.push(cv.toDataURL('image/png')); }catch(e){}
      next(i+1);
    }).catch(function(){ next(i+1); });
  })(0);
}
function qrImprimir(){
  try{ document.querySelectorAll('.qrp').forEach(function(el){ var sz=parseInt(el.dataset.size)||130; new QRCode(el,{text:el.dataset.url,width:sz,height:sz,correctLevel:QRCode.CorrectLevel.M}); }); }catch(e){}
  if(RASTER){ setTimeout(rasterTickets, 450); return; }
  var isMobile = /Android|iPhone|iPad|iPod|Mobile|Opera Mini/i.test(navigator.userAgent||'') || (window.matchMedia && window.matchMedia('(max-width:820px)').matches) || ('ontouchstart' in window && Math.min(screen.width,screen.height)<820);
  if(MODO==='pdf' || isMobile){
    document.getElementById('pdfbar').style.display='block';
    setTimeout(function(){ prepararPdf(function(){ var st=document.getElementById('pdfStatus'); if(st) st.textContent='Pronto! Toque em Enviar pra mandar no WhatsApp.'; }); }, 400);
  } else {
    setTimeout(function(){ window.print(); }, 350);
  }
}
function prepararPdf(cb){
  var _h=0; document.querySelectorAll('.ticket').forEach(function(el){ if(el.offsetHeight>_h) _h=el.offsetHeight; });
  var _mm=Math.ceil(_h/96*25.4)+8; if(_mm<70) _mm=70;
  var opt={ margin:0, filename:_pdfName, image:{type:'jpeg',quality:0.95}, html2canvas:{scale:2,useCORS:true}, jsPDF:{unit:'mm',format:[80,_mm],orientation:'portrait'}, pagebreak:{mode:['css']} };
  html2pdf().set(opt).from(document.body).toPdf().output('blob').then(function(blob){
    _pdfBlob=blob;
    try{ _pdfFile=new File([blob],_pdfName,{type:'application/pdf'}); }catch(e){ _pdfFile=null; }
    if(cb) cb();
  }).catch(function(e){ var st=document.getElementById('pdfStatus'); if(st) st.textContent='Erro ao gerar PDF: '+e.message; });
}
function enviarPdf(){
  // precisa rodar DENTRO do toque do usuario pra o compartilhamento (WhatsApp) funcionar
  try{
    if(_pdfFile && navigator.canShare && navigator.canShare({files:[_pdfFile]})){
      navigator.share({files:[_pdfFile], title:'Passagem {{ $p->numeroFormatado() }}', text:'Sua passagem 🎫'}).catch(function(){});
      return;
    }
  }catch(e){}
  if(_pdfBlob){
    var url=URL.createObjectURL(_pdfBlob); var a=document.createElement('a'); a.href=url; a.download=_pdfName; document.body.appendChild(a); a.click(); a.remove(); setTimeout(function(){URL.revokeObjectURL(url);},1500);
    return;
  }
  var st=document.getElementById('pdfStatus'); if(st) st.textContent='Ainda gerando… toque de novo em 1 segundo.';
  prepararPdf(function(){ var s2=document.getElementById('pdfStatus'); if(s2) s2.textContent='Pronto! Toque em Enviar.'; });
}
</script>
</body></html>
