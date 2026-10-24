@extends('impressao._base', ['titulo' => 'PIX '.$e->numeroFormatado()])
@section('corpo')
<div class="c">
  @if($empresa->logo)<img class="logo" src="{{ asset($empresa->logo) }}"><br>@endif
  <div class="b lg">{{ strtoupper($empresa->nomeExibicao()) }}</div>
</div>
<hr>
<div class="c b xl">PAGUE COM PIX</div>
<div class="c sm">Encomenda Nº {{ $e->numeroFormatado() }}</div>
<hr>
<div class="c b" style="font-size:16px">R$ {{ number_format($c->valor,2,',','.') }}</div>
<div class="c sm">Aponte a câmera do celular no QR abaixo</div>

<div class="c" style="margin:3mm 0">
  @if($c->qr_base64)
    <img style="width:52mm;height:52mm" src="{{ \Illuminate\Support\Str::startsWith($c->qr_base64,'data:') ? $c->qr_base64 : 'data:image/png;base64,'.$c->qr_base64 }}">
  @else
    <div id="qr"></div>
  @endif
</div>

@if($c->qr_copia_cola)
<div class="sm b c">PIX copia e cola:</div>
<div class="box" style="word-break:break-all;font-size:9px">{{ $c->qr_copia_cola }}</div>
@endif

<hr>
<div class="c sm">Pague pelo app do seu banco. A entrega é liberada após a confirmação.</div>
<div class="c sm">{{ now()->format('d/m/Y H:i') }}</div>

@if(!$c->qr_base64 && $c->qr_copia_cola)
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
  try{ new QRCode(document.getElementById('qr'),{text:@json($c->qr_copia_cola),width:200,height:200,correctLevel:QRCode.CorrectLevel.M}); }catch(e){}
</script>
@endif
@endsection
