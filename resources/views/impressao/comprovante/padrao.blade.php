@extends('impressao._base', ['titulo' => 'Comprovante '.$e->numeroFormatado()])
@section('corpo')
<div class="c">
  @if($empresa->logo)<img class="logo" src="{{ asset($empresa->logo) }}"><br>@endif
  <div class="b lg">{{ strtoupper($empresa->nomeExibicao()) }}</div>
  @if($empresa->cnpj)<div class="sm">CNPJ {{ $empresa->cnpj }}</div>@endif
  @if($empresa->telefone || $empresa->whatsapp)<div class="sm">{{ $empresa->telefone }} {{ $empresa->whatsapp ? '· WhatsApp '.$empresa->whatsapp : '' }}</div>@endif
</div>
<hr>
<div class="c b xl">ENCOMENDA Nº {{ $e->numeroFormatado() }}</div>
<div class="c sm">{{ $e->created_at->format('d/m/Y H:i') }} · Operador: {{ $e->operador?->name }}</div>
<hr>
<table>
  <tr><td class="b">ORIGEM</td><td class="r">{{ $e->origem }}</td></tr>
  <tr><td class="b">DESTINO</td><td class="r">{{ $e->destino }}</td></tr>
  @if($e->embarcacao)<tr><td class="b">TRANSPORTE</td><td class="r">{{ $e->embarcacao->nome }}</td></tr>@endif
  <tr><td class="b">VOLUMES</td><td class="r">{{ $e->volumes }}</td></tr>
</table>
<hr>
<div class="b">REMETENTE</div>
<div>{{ $e->remetente_nome }}</div>
<div class="sm">{{ $e->remetente_telefone }} {{ $e->remetente_documento }}</div>
<div class="b" style="margin-top:3px">DESTINATÁRIO</div>
<div>{{ $e->destinatario_nome }}</div>
<div class="sm">{{ $e->destinatario_telefone }} {{ $e->destinatario_documento }}</div>
<hr>
<table>
  @foreach($e->itens as $i)
  <tr><td>{{ $i->descricao }}</td><td class="r" style="white-space:nowrap">{{ (float)$i->quantidade }}x {{ number_format($i->valor_unitario,2,',','.') }}</td><td class="r b" style="white-space:nowrap">{{ number_format($i->valor_total,2,',','.') }}</td></tr>
  @if($i->observacao)<tr><td colspan="3" class="sm">&nbsp;&nbsp;{{ $i->observacao }}</td></tr>@endif
  @endforeach
</table>
<hr>
<table>
  @if($e->desconto>0)
  <tr><td>Subtotal</td><td class="r">{{ number_format($e->subtotal,2,',','.') }}</td></tr>
  <tr><td>Desconto</td><td class="r">-{{ number_format($e->desconto,2,',','.') }}</td></tr>
  @endif
  <tr class="lg"><td class="b">TOTAL</td><td class="r b">R$ {{ number_format($e->total,2,',','.') }}</td></tr>
  <tr><td>Pago</td><td class="r">R$ {{ number_format($e->pago,2,',','.') }}</td></tr>
  <tr><td class="b">DEVIDO</td><td class="r b">R$ {{ number_format($e->devido,2,',','.') }}</td></tr>
  <tr><td>Forma</td><td class="r">{{ \App\Models\Encomenda::FORMAS[$e->forma] ?? $e->forma }}</td></tr>
</table>
@if($e->pagamentos->count())
<div class="sm">
  @foreach($e->pagamentos as $p)<div>{{ \App\Models\Pagamento::MEIOS[$p->meio] ?? $p->meio }}: R$ {{ number_format((float)$p->valor,2,',','.') }} ({{ $p->created_at?->format('d/m H:i') }})</div>@endforeach
</div>
@endif
<hr>
<div class="c b">{{ strtoupper($e->statusTexto()) }}</div>
@if($e->entregue)<div class="c sm">Entregue@if($e->entregue_em) em {{ $e->entregue_em->format('d/m/Y H:i') }}@endif@if($e->entregue_para) para {{ $e->entregue_para }}@endif@if($e->entregue_documento) · CPF {{ $e->entregue_documento }}@endif</div>@endif
@if($e->observacao)<hr><div class="sm">Obs: {{ $e->observacao }}</div>@endif
@if($empresa->pix)<hr><div class="c sm">PIX: {{ $empresa->pix }}</div>@endif
@if($empresa->rodape_comprovante)<hr><div class="c sm">{!! nl2br(e($empresa->rodape_comprovante)) !!}</div>@endif
<hr>
<div class="c sm">Apresente este comprovante para retirar a encomenda.</div>
<div class="c sm">fbre.com.br</div>
<br><br>
@endsection
