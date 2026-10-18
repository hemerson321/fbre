@extends('impressao._base', ['titulo' => 'Etiquetas '.$e->numeroFormatado()])
@section('corpo')
@for($v = 1; $v <= $e->volumes; $v++)
<div class="{{ $v < $e->volumes ? 'quebra' : '' }}">
  <div class="c b lg">{{ strtoupper($empresa->nomeExibicao()) }}</div>
  <div class="box c">
    <div class="b" style="font-size:26px">Nº {{ $e->numeroFormatado() }}</div>
    <div class="b lg">VOLUME {{ $v }}/{{ $e->volumes }}</div>
  </div>
  <div class="b lg">DESTINO: {{ strtoupper($e->destino) }}</div>
  <div class="sm">Origem: {{ $e->origem }} @if($e->embarcacao)· {{ $e->embarcacao->nome }}@endif</div>
  <hr>
  <div class="b">PARA: {{ strtoupper($e->destinatario_nome) }}</div>
  <div>{{ $e->destinatario_telefone }}</div>
  <div class="sm" style="margin-top:3px">DE: {{ $e->remetente_nome }} {{ $e->remetente_telefone }}</div>
  <hr>
  <table><tr><td class="sm">{{ $e->created_at->format('d/m/Y H:i') }}</td><td class="r b">{{ $e->devido > 0 ? 'A RECEBER R$ '.number_format($e->devido,2,',','.') : 'PAGO' }}</td></tr></table>
  <br>
</div>
@endfor
@endsection
