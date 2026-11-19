<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Etiqueta {{ $p->numeroFormatado() }}</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
@php
  $labW = $labW ?? 90; $labH = $labH ?? 29;
  $k = max(0.6, $labH / 29);                 // escala relativa ao papel base (29mm). Aumenta o papel -> tudo cresce junto.
  $px = fn ($n) => (int) round($n * $k);
  $bd = max(1, (int) round(1.3 * $k));
  $lancha = $p->embarcacao?->nome ?: ($empresa->nomeExibicao() ?? '');
  $data   = $p->data_viagem ? $p->data_viagem->format('d/m/Y') : $p->created_at->format('d/m/Y');
  $ticket = $p->numeroFormatado();
  $pol    = $pax->poltrona ?: '—';
  $nome   = strtoupper($pax->nome);
  $orig   = strtoupper($p->origem); $dest = strtoupper($p->destino);
@endphp
<style>
  @page { margin: 0; size: {{ $labW }}mm {{ $labH }}mm; }
  * { box-sizing: border-box; }
  body { margin:0; padding:0; width:{{ $labW }}mm; font-family: Arial, Helvetica, sans-serif; color:#000; background:#fff; -webkit-font-smoothing:none; }
  .lbl { width:{{ $labW }}mm; height:{{ $labH }}mm; overflow:hidden; page-break-after:always;
         border:{{ $bd }}px solid #000; display:flex; flex-direction:column; background:#fff; }
  .lbl:last-child { page-break-after:auto; }
  .hdr { display:flex; align-items:center; justify-content:space-between; background:#000; color:#fff;
         padding:{{ $px(2) }}px {{ $px(5) }}px; }
  .hdr .lch { font-weight:800; font-size:{{ $px(10) }}px; letter-spacing:.3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .hdr .bdg { font-weight:800; font-size:{{ $px(9) }}px; border:{{ max(1,(int)round($k)) }}px solid #fff; border-radius:3px; padding:0 {{ $px(4) }}px; white-space:nowrap; margin-left:{{ $px(4) }}px; }
  .bdy { flex:1; display:flex; min-height:0; }
  .info { flex:1; padding:{{ $px(3) }}px {{ $px(5) }}px; display:flex; flex-direction:column; justify-content:center; min-width:0; }
  .nome { font-weight:800; font-size:{{ $px(13) }}px; line-height:1.03; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .rota { font-weight:700; font-size:{{ $px(11) }}px; margin-top:{{ $px(1) }}px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .rota .seta { font-weight:400; }
  .meta { font-size:{{ $px(8) }}px; margin-top:{{ $px(2) }}px; line-height:1.3; }
  .meta b { font-weight:700; }
  .seat { width:33%; border-left:{{ $bd }}px solid #000; display:flex; flex-direction:column;
          align-items:center; justify-content:center; padding:{{ $px(2) }}px; text-align:center; }
  .seat .cap { font-size:{{ $px(7) }}px; font-weight:700; letter-spacing:.5px; }
  .seat .num { font-size:{{ $px(23) }}px; font-weight:800; line-height:.95; }
  .seat .vol { font-size:{{ $px(8) }}px; font-weight:700; margin-top:{{ $px(1) }}px; }
  @media screen { body { margin:12px auto; max-width:420px; } .lbl { margin-bottom:12px; box-shadow:0 0 10px #0004; } }
</style>
</head>
<body>
@if(!($soVia ?? false))
@for($v = 1; $v <= $volumes; $v++)
<div class="lbl">
  <div class="hdr">
    <span class="lch">{{ strtoupper($lancha) }}</span>
    <span class="bdg">VOL {{ $v }}/{{ $volumes }}</span>
  </div>
  <div class="bdy">
    <div class="info">
      <div class="nome">{{ $nome }}</div>
      <div class="rota">{{ $orig }} <span class="seta">&rsaquo;</span> {{ $dest }}</div>
      <div class="meta"><b>Data:</b> {{ $data }} &nbsp; <b>Ticket:</b> {{ $ticket }}@if($pax->telefone)<br><b>Tel.:</b> {{ $pax->telefone }}@endif</div>
    </div>
    <div class="seat">
      <div class="cap">POLTRONA</div>
      <div class="num">{{ $pol }}</div>
      <div class="vol">{{ $v }}/{{ $volumes }}</div>
    </div>
  </div>
</div>
@endfor
@endif
<div class="lbl">
  <div class="hdr">
    <span class="lch">{{ strtoupper($lancha) }}</span>
    <span class="bdg">VIA DO PASSAGEIRO</span>
  </div>
  <div class="bdy">
    <div class="info">
      <div class="nome">{{ $nome }}</div>
      <div class="rota">{{ $orig }} <span class="seta">&rsaquo;</span> {{ $dest }}</div>
      <div class="meta"><b>Data:</b> {{ $data }} &nbsp; <b>Ticket:</b> {{ $ticket }}@if(!($soVia ?? false)) &nbsp; <b>Vol.:</b> {{ $volumes }} @endif @if($pax->telefone)<br><b>Tel.:</b> {{ $pax->telefone }}@endif</div>
    </div>
    <div class="seat">
      <div class="cap">POLTRONA</div>
      <div class="num">{{ $pol }}</div>
    </div>
  </div>
</div>
<script>
(function(){
  var RASTER = new URLSearchParams(location.search).has('raster');
  var GIRAR = {{ ($girar ?? true) ? 'true' : 'false' }};
  function raster(){
    var h2c = window.html2canvas;
    if(!h2c){ try{ parent.postMessage({fbreRaster:true, imagens:[], erro:'sem html2canvas'}, '*'); }catch(e){} return; }
    var els = [].slice.call(document.querySelectorAll('.lbl'));
    var out = [];
    (function next(i){
      if(i>=els.length){ try{ parent.postMessage({fbreRaster:true, imagens:out}, '*'); }catch(e){} return; }
      h2c(els[i], {scale:2, backgroundColor:'#ffffff', useCORS:true, logging:false}).then(function(cv){
        try{
          if(GIRAR){
            var rc=document.createElement('canvas'); rc.width=cv.height; rc.height=cv.width;
            var rx=rc.getContext('2d'); rx.translate(rc.width/2, rc.height/2); rx.rotate(Math.PI/2);
            rx.drawImage(cv, -cv.width/2, -cv.height/2);
            out.push(rc.toDataURL('image/png'));
          } else { out.push(cv.toDataURL('image/png')); }
        }catch(e){} next(i+1);
      }).catch(function(){ next(i+1); });
    })(0);
  }
  window.addEventListener('load', function(){
    if(RASTER){ setTimeout(raster, 350); return; }
    setTimeout(function(){ window.print(); }, 300);
  });
})();
</script>
</body>
</html>
