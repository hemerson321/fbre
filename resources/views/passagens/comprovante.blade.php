<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<title>Passagem {{ $p->numeroFormatado() }}</title>
<style>@page{margin:0;size:80mm auto}*{box-sizing:border-box}body{margin:0;padding:3mm 4mm;width:80mm;font-family:"Courier New",monospace;font-size:11.5px;color:#000}
.c{text-align:center}.r{text-align:right}.b{font-weight:bold}.xl{font-size:18px}.lg{font-size:14px}.sm{font-size:10px;color:#333}hr{border:0;border-top:1px dashed #000;margin:4px 0}
table{width:100%;border-collapse:collapse}td{padding:2px 0;vertical-align:top}</style></head>
<body onload="window.print()">
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
  <tr><td>{{ $pax->nome }}<div class="sm">{{ $pax->tipo }}@if($pax->poltrona) · poltrona {{ $pax->poltrona }}@endif</div></td><td class="r b">{{ number_format($pax->valor_total,2,',','.') }}</td></tr>
  @endforeach
</table><hr>
<table>
  @if($p->desconto>0)<tr><td>Desconto</td><td class="r">-{{ number_format($p->desconto,2,',','.') }}</td></tr>@endif
  <tr class="lg"><td class="b">TOTAL</td><td class="r b">R$ {{ number_format($p->total,2,',','.') }}</td></tr>
  <tr><td>Pago</td><td class="r">R$ {{ number_format($p->pago,2,',','.') }}</td></tr>
  <tr><td class="b">DEVIDO</td><td class="r b">R$ {{ number_format($p->devido,2,',','.') }}</td></tr>
</table>
@if($e?->rodape_comprovante)<hr><div class="c sm">{{ $e->rodape_comprovante }}</div>@endif
<hr><div class="c sm">Apresente esta passagem no embarque.</div><br><br>
</body></html>
