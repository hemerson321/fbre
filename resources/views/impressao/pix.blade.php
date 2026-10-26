@php
  $qr = null;
  if ($c->qr_base64) {
    $qr = \Illuminate\Support\Str::startsWith($c->qr_base64, 'data:') ? $c->qr_base64 : 'data:image/png;base64,'.$c->qr_base64;
  } elseif ($c->qr_copia_cola && class_exists(\chillerlan\QRCode\QRCode::class)) {
    try { $qr = (new \chillerlan\QRCode\QRCode())->render($c->qr_copia_cola); } catch (\Throwable $e) { $qr = null; }
  }
@endphp
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><title>PIX</title>
<style>
  @page { margin:0; size:80mm auto; }
  * { box-sizing:border-box; }
  body { margin:0; padding:3mm 4mm; width:80mm; font-family:"Courier New",monospace; color:#000; text-align:center; background:#fff; }
  .b{font-weight:bold} .lg{font-size:14px} .xl{font-size:16px}
  img{width:58mm;height:58mm;display:block;margin:2mm auto}
  .copia{font-size:9px;word-break:break-all;margin-top:2mm}
  @media screen { body { margin:10px auto; box-shadow:0 0 8px #0003; } }
</style></head>
<body onload="setTimeout(function(){window.print();},150)">
  <div class="b lg">PIX</div>
  <div class="b xl">R$ {{ number_format($c->valor,2,',','.') }}</div>
  @if($qr)
    <img src="{{ $qr }}" alt="QR PIX">
  @elseif($c->qr_copia_cola)
    <div class="copia">{{ $c->qr_copia_cola }}</div>
  @else
    <div>QR indisponível</div>
  @endif
</body></html>
