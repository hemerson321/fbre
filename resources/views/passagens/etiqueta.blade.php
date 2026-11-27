<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Etiqueta {{ $p->numeroFormatado() }}</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
@php
  $labW = $labW ?? 90; $labH = $labH ?? 29;
  $kw = max(0.6, $labW / 90);   // escala do TEXTO que corre na largura (nome, rota, dados) -> cabe sempre
  $kh = max(0.6, $labH / 29);   // escala da ALTURA (coluna da poltrona, bordas) -> usa o espaco extra
  $px  = fn ($n) => (int) round($n * $kw);
  $pxh = fn ($n) => (int) round($n * $kh);
  $kwBag = max(0.6, ($labWbag ?? $labW) / 90);  // BAGAGEM: fonte maior (acompanha o comprimento proprio)
  $pxB  = fn ($n) => (int) round($n * $kwBag);
  $bd = max(1, (int) round(1.4 * $kh));
  $nomeLines = $labH >= 45 ? 3 : 2;
  $lancha = $p->embarcacao?->nome ?: ($empresa->nomeExibicao() ?? '');
  $data   = $p->data_viagem ? $p->data_viagem->format('d/m/Y') : $p->created_at->format('d/m/Y');
  $ticket = $p->numeroFormatado();
  $pol    = $pax->poltrona ?: '—';
  $nome   = strtoupper($pax->nome);
  $orig   = strtoupper($p->origem); $dest = strtoupper($p->destino);
@endphp
<style>
  @page { margin: 0; size: {{ max($labW, $labWbag ?? $labW) }}mm {{ max($labH, $labHbag ?? $labH) }}mm; }
  * { box-sizing: border-box; }
  body { margin:0; padding:0; width:{{ $labW }}mm; font-family: Arial, Helvetica, sans-serif; color:#000; background:#fff; -webkit-font-smoothing:none; }
  .lbl { width:{{ $labW }}mm; height:{{ $labH }}mm; overflow:hidden; page-break-after:always;
         border:{{ $bd }}px solid #000; display:flex; flex-direction:column; background:#fff; }
  .lbl:last-child { page-break-after:auto; }
  .hdr { display:flex; align-items:center; justify-content:space-between; background:#000; color:#fff;
         padding:{{ $px(3) }}px {{ $px(6) }}px; }
  .hdr .lch { font-weight:800; font-size:{{ $px(12) }}px; letter-spacing:.3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .hdr .bdg { font-weight:800; font-size:{{ $px(10) }}px; border:{{ max(1,(int)round($kh)) }}px solid #fff; border-radius:3px; padding:{{ $px(1) }}px {{ $px(5) }}px; white-space:nowrap; margin-left:{{ $px(5) }}px; }
  .bdy { flex:1; display:flex; min-height:0; }
  .info { flex:1; padding:{{ $px(3) }}px {{ $px(6) }}px; display:flex; flex-direction:column; justify-content:space-between; min-width:0; }
  .nome { font-weight:800; font-size:{{ $px(17) }}px; line-height:1.0; text-transform:uppercase;
          display:-webkit-box; -webkit-line-clamp:{{ $nomeLines }}; -webkit-box-orient:vertical; overflow:hidden; }
  .rota { font-weight:800; font-size:{{ $px(14) }}px; margin-top:{{ $px(2) }}px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .rota .seta { font-weight:400; }
  .meta { font-size:{{ $px(11) }}px; line-height:1.3; margin-top:{{ $px(2) }}px; }
  .meta b { font-weight:800; }
  .obs { font-size:{{ $px(11) }}px; font-weight:700; line-height:1.1; margin-top:{{ $px(2) }}px; padding:{{ $px(2) }}px {{ $px(4) }}px; background:#000; color:#fff; border-radius:{{ $px(2) }}px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
  .troca { font-size:{{ $px(9) }}px; line-height:1.2; margin-top:{{ $px(2) }}px; padding:{{ $px(2) }}px {{ $px(3) }}px; border:{{ max(1, $px(1)) }}px solid #000; border-radius:{{ $px(2) }}px; }
  .troca b { font-weight:900; }
  /* coluna direita: poltrona + volume, os dois em destaque */
  .seat2 { width:35%; border-left:{{ $bd }}px solid #000; display:flex; flex-direction:column; }
  .seat2 .cell { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:{{ $px(1) }}px; text-align:center; }
  .seat2 .cell.sep { border-top:{{ $bd }}px solid #000; }
  .cap { font-size:{{ $pxh(8) }}px; font-weight:800; letter-spacing:.4px; }
  .num { font-size:{{ $pxh(24) }}px; font-weight:800; line-height:.9; }
  .vnum { font-size:{{ $pxh(21) }}px; }
  .seat1 { width:35%; border-left:{{ $bd }}px solid #000; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:{{ $px(2) }}px; text-align:center; }
  .seat1 .num { font-size:{{ $pxh(31) }}px; }
  /* ETIQUETA DE BAGAGEM: maior e com fonte maior que a via */
  .lbl.bag .hdr .lch { font-size:{{ $pxB(12) }}px; }
  .lbl.bag .hdr .bdg { font-size:{{ $pxB(10) }}px; }
  .lbl.bag .nome { font-size:{{ $pxB(17) }}px; }
  .lbl.bag .rota { font-size:{{ $pxB(14) }}px; }
  .lbl.bag .meta { font-size:{{ $pxB(11) }}px; }
  .lbl.bag .obs  { font-size:{{ $pxB(11) }}px; }
  .lbl.bag .seat2 .cap  { font-size:{{ $pxB(9) }}px; }
  .lbl.bag .seat2 .num  { font-size:{{ $pxB(26) }}px; }
  .lbl.bag .seat2 .vnum { font-size:{{ $pxB(22) }}px; }
  @media screen { body { margin:12px auto; max-width:460px; } .lbl { margin-bottom:12px; box-shadow:0 0 10px #0004; } }
</style>
</head>
<body>
@if(!($soVia ?? false))
@for($v = 1; $v <= $volumes; $v++)
<div class="lbl bag" style="width:{{ $labWbag ?? $labW }}mm;height:{{ $labHbag ?? $labH }}mm">
  <div class="hdr">
    <span class="lch">{{ strtoupper($lancha) }}</span>
    <span class="bdg">BAGAGEM</span>
  </div>
  <div class="bdy">
    <div class="info">
      <div>
        <div class="nome">{{ $nome }}</div>
        <div class="rota">{{ $orig }} <span class="seta">&rsaquo;</span> {{ $dest }}</div>
      </div>
      <div class="meta"><b>Data:</b> {{ $data }} &nbsp; <b>Ticket:</b> {{ $ticket }}@if($pax->telefone)<br><b>Tel.:</b> {{ $pax->telefone }}@endif</div>
      @if(!empty($obs ?? ''))<div class="obs">📝 {{ $obs }}</div>@endif
    </div>
    <div class="seat2">
      <div class="cell">
        <div class="cap">POLTRONA</div>
        <div class="num">{{ $pol }}</div>
      </div>
      <div class="cell sep">
        <div class="cap">VOLUME</div>
        <div class="num vnum">{{ $v }}/{{ $volumes }}</div>
      </div>
    </div>
  </div>
</div>
@endfor
@endif
{{-- via do passageiro: não sai quando o operador pediu SÓ etiqueta de bagagem; sai na quantidade pedida ($vias) --}}
@if(!($soBag ?? false))
@for($vv = 1; $vv <= max(1, (int) ($vias ?? 1)); $vv++)
<div class="lbl">
  <div class="hdr">
    <span class="lch">{{ strtoupper($lancha) }}</span>
    <span class="bdg">VIA DO PASSAGEIRO</span>
  </div>
  <div class="bdy">
    <div class="info">
      <div>
        <div class="nome">{{ $nome }}</div>
        <div class="rota">{{ $orig }} <span class="seta">&rsaquo;</span> {{ $dest }}</div>
      </div>
      <div class="meta"><b>Data:</b> {{ $data }} &nbsp; <b>Ticket:</b> {{ $ticket }}@if($pax->telefone)<br><b>Tel.:</b> {{ $pax->telefone }}@endif</div>
      @if(!empty($obs ?? ''))<div class="obs">📝 {{ $obs }}</div>@endif
      @if($pax->transf_de_lancha)
      <div class="troca"><b>MUDANÇA DE TRANSPORTE</b><br>Antes: {{ strtoupper($pax->transf_de_lancha) }}{{ $pax->transf_de_poltrona ? ' · polt. '.$pax->transf_de_poltrona : '' }}{{ $pax->transf_de_bilhete ? ' · bilhete '.$pax->transf_de_bilhete : '' }}<br><b>Agora: {{ strtoupper($lancha) }}{{ $pax->poltrona ? ' · POLTRONA '.$pax->poltrona : '' }}</b></div>
      @endif
    </div>
    <div class="seat1">
      <div class="cap">POLTRONA</div>
      <div class="num">{{ $pol }}</div>
      <div class="cap" style="margin-top:{{ $px(3) }}px">{{ $volumes }} VOL.</div>
    </div>
  </div>
</div>
@endfor
@endif
<script>
(function(){
  var RASTER = new URLSearchParams(location.search).has('raster');
  var GIRAR = {{ ($girar ?? true) ? 'true' : 'false' }};
  function fitEl(el, min){ if(!el) return; var fs=parseFloat(getComputedStyle(el).fontSize)||12; var g=60; while(el.scrollWidth>el.clientWidth+1 && fs>min && g-->0){ fs-=1; el.style.fontSize=fs+'px'; } }
  function fitLabels(){ var L=document.querySelectorAll('.lbl'); for(var i=0;i<L.length;i++){ fitEl(L[i].querySelector('.nome'),9); fitEl(L[i].querySelector('.rota'),8); var obs=L[i].querySelector('.obs'); if(obs) fitEl(obs,7); var ms=L[i].querySelectorAll('.meta'); for(var j=0;j<ms.length;j++) fitEl(ms[j],7); } }
  function raster(){
    var h2c = window.html2canvas;
    if(!h2c){ try{ parent.postMessage({fbreRaster:true, imagens:[], erro:'sem html2canvas'}, '*'); }catch(e){} return; }
    var els = [].slice.call(document.querySelectorAll('.lbl'));
    var out = [], tipos = [];
    (function next(i){
      if(i>=els.length){ try{ parent.postMessage({fbreRaster:true, imagens:out, tipos:tipos}, '*'); }catch(e){} return; }
      h2c(els[i], {scale:2, backgroundColor:'#ffffff', useCORS:true, logging:false}).then(function(cv){
        try{
          if(GIRAR){
            var rc=document.createElement('canvas'); rc.width=cv.height; rc.height=cv.width;
            var rx=rc.getContext('2d'); rx.translate(rc.width/2, rc.height/2); rx.rotate(Math.PI/2);
            rx.drawImage(cv, -cv.width/2, -cv.height/2);
            out.push(rc.toDataURL('image/png'));
          } else { out.push(cv.toDataURL('image/png')); }
          tipos.push(els[i].classList.contains('bag') ? 'bag' : 'via');
        }catch(e){} next(i+1);
      }).catch(function(){ next(i+1); });
    })(0);
  }
  window.addEventListener('load', function(){
    try{ fitLabels(); }catch(e){}
    if(RASTER){ setTimeout(raster, 350); return; }
    setTimeout(function(){ window.print(); }, 300);
  });
})();
</script>
</body>
</html>
