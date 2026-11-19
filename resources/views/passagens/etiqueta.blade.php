<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Etiqueta {{ $p->numeroFormatado() }}</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<style>
  @page { margin: 0; size: {{ $pagina ?? '80mm auto' }}; }
  * { box-sizing: border-box; }
  body { margin: 0; padding: 0; width: {{ $largura ?? '80mm' }}; font-family: "Courier New", Courier, monospace; font-size: 11.5px; color: #000; background: #fff; }
  .lbl { padding: 3mm 4mm; page-break-after: always; }
  .lbl:last-child { page-break-after: auto; }
  .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; }
  .xl { font-size: 18px; } .lg { font-size: 14px; } .sm { font-size: 10px; }
  table { width: 100%; border-collapse: collapse; } td { padding: 1px 0; vertical-align: top; }
  @media screen { .lbl { border-bottom: 1px dashed #bbb; } body { margin: 10px auto; max-width: 360px; box-shadow: 0 0 8px #0003; } }
</style>
</head>
<body>
@php
  $lancha = $p->embarcacao?->nome ?: ($empresa->nomeExibicao() ?? '');
  $rota   = $p->origem.' x '.$p->destino;
  $data   = $p->data_viagem ? $p->data_viagem->format('d/m/Y') : $p->created_at->format('d/m/Y');
  $ticket = $p->numeroFormatado();
  $pol    = $pax->poltrona ?: '—';
@endphp
@if(!($soVia ?? false))
@for($v = 1; $v <= $volumes; $v++)
<div class="lbl">
  <div class="sm">Lancha</div>
  <div class="b lg">{{ strtoupper($lancha) }}</div>
  <div class="b lg" style="margin-top:2px">{{ strtoupper($pax->nome) }}</div>
  <div class="b lg" style="margin-top:3px">{{ $p->origem }} x {{ $p->destino }}</div>
  <div class="b lg">Pol. {{ $pol }} · QTD {{ $v }}/{{ $volumes }}</div>
  @if($pax->telefone)<div class="sm" style="margin-top:3px">Tel.: {{ $pax->telefone }}</div>@endif
  <div class="sm">Data: {{ $data }} · Ticket: {{ $ticket }}</div>
</div>
@endfor
@endif
<div class="lbl">
  <div class="b">— Via do Passageiro —</div>
  <div class="sm" style="margin-top:2px">{{ strtoupper($lancha) }}</div>
  <div class="b lg">{{ strtoupper($pax->nome) }}</div>
  <div class="b lg">{{ $p->origem }} x {{ $p->destino }}</div>
  <div class="b">Pol. {{ $pol }}</div>
  <div class="sm" style="margin-top:3px">Data: {{ $data }} · Ticket: {{ $ticket }}</div>
  @if(!($soVia ?? false))<div class="b" style="margin-top:2px">{{ $volumes }} Volume(s)</div>@endif
</div>
<script>
(function(){
  var RASTER = new URLSearchParams(location.search).has('raster');
  function raster(){
    var h2c = window.html2canvas;
    if(!h2c){ try{ parent.postMessage({fbreRaster:true, imagens:[], erro:'sem html2canvas'}, '*'); }catch(e){} return; }
    var els = [].slice.call(document.querySelectorAll('.lbl'));
    var out = [];
    (function next(i){
      if(i>=els.length){ try{ parent.postMessage({fbreRaster:true, imagens:out}, '*'); }catch(e){} return; }
      h2c(els[i], {scale:2, backgroundColor:'#ffffff', useCORS:true, logging:false}).then(function(cv){
        try{ out.push(cv.toDataURL('image/png')); }catch(e){} next(i+1);
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
