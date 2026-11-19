@extends('impressao._base', ['titulo' => 'Etiqueta '.$p->numeroFormatado(), 'pagina' => $pagina ?? '80mm auto'])
@section('corpo')
@php
  $lancha = $p->embarcacao?->nome ?: ($empresa->nomeExibicao() ?? '');
  $rota   = $p->origem.' x '.$p->destino;
  $data   = $p->data_viagem ? $p->data_viagem->format('d/m/Y') : $p->created_at->format('d/m/Y');
  $ticket = $p->numeroFormatado();
  $pol    = $pax->poltrona ?: '—';
@endphp
@if(!($soVia ?? false))
@for($v = 1; $v <= $volumes; $v++)
<div class="quebra">
  <table><tr>
    <td style="width:60%">
      <div class="sm">Lancha:</div>
      <div class="b lg">{{ strtoupper($lancha) }}</div>
      <div class="b lg">{{ strtoupper($pax->nome) }}</div>
      @if($pax->telefone)<div class="sm">Tel.: {{ $pax->telefone }}</div>@endif
      <div class="sm">Data: {{ $data }}</div>
      <div class="sm">Ticket: {{ $ticket }}</div>
    </td>
    <td class="c" style="width:40%;vertical-align:middle">
      <div class="b xl">{{ $p->origem }}</div>
      <div class="b">x</div>
      <div class="b xl">{{ $p->destino }}</div>
      <div class="b lg" style="margin-top:4px">Pol. {{ $pol }}</div>
      <div class="b lg">QTD.: {{ $v }}/{{ $volumes }}</div>
    </td>
  </tr></table>
</div>
@endfor
@endif
<div>
  <div class="b lg">Via do Passageiro</div>
  <div class="b">{{ strtoupper($lancha) }} · Data: {{ $data }}</div>
  <div class="b lg">{{ strtoupper($pax->nome) }}</div>
  <div class="sm">{{ $rota }} · Ticket: {{ $ticket }} · Pol. {{ $pol }}</div>
  <div class="b">{{ $volumes }} Volume(s)</div>
</div>
@endsection
