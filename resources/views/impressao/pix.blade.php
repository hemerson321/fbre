@extends('impressao._base', ['titulo' => 'PIX '.$numero])
@section('corpo')
<div class="c b lg">PIX</div>
<div class="c b" style="font-size:16px">R$ {{ number_format($c->valor,2,',','.') }}</div>
<div class="c" style="margin:2mm 0">
  @if($c->qr_base64)
    <img style="width:58mm;height:58mm" src="{{ \Illuminate\Support\Str::startsWith($c->qr_base64,'data:') ? $c->qr_base64 : 'data:image/png;base64,'.$c->qr_base64 }}">
  @else
    <div id="qr"></div>
  @endif
</div>

@if(!$c->qr_base64 && $c->qr_copia_cola)
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
  try{ new QRCode(document.getElementById('qr'),{text:@json($c->qr_copia_cola),width:220,height:220,correctLevel:QRCode.CorrectLevel.M}); }catch(e){}
</script>
@endif
@endsection
