@extends('layouts.app')
@section('titulo','Acerto do dono')
@section('conteudo')
@php
  $shareText = "*Acerto — {$embarcacao->nome}*\n".
    ($embarcacao->dono_nome ? "Dono: {$embarcacao->dono_nome}\n" : '').
    "Passagens: {$passagens->count()}\n".
    "Total vendido: R$ ".number_format($bruto,2,',','.')."\n".
    "Comissão empresa ({$pct}%): R$ ".number_format($comissao,2,',','.')."\n".
    "*A pagar ao dono: R$ ".number_format($dono,2,',','.')."*";
@endphp
<div class="max-w-2xl mx-auto space-y-4" x-data="donoShare(@js($shareText))">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold">{{ $embarcacao->nome }}</h1>
      <p class="text-xs text-slate-500">Dono: <b>{{ $embarcacao->dono_nome ?: '—' }}</b> · empresa fica {{ $pct }}%</p>
    </div>
    <a href="{{ route('admin.acertos') }}" class="btn btn-ghost btn-sm">voltar</a>
  </div>

  <div class="card p-4" style="background:linear-gradient(135deg,#0b1a33,#13294d);color:#fff">
    <div class="text-xs opacity-70">A pagar ao dono</div>
    <div class="text-3xl font-extrabold text-amber-300 mb-2">R$ {{ number_format($dono,2,',','.') }}</div>
    <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
      <div><span class="opacity-70">Total vendido:</span> <b>R$ {{ number_format($bruto,2,',','.') }}</b></div>
      <div><span class="opacity-70">Comissão empresa ({{ $pct }}%):</span> <b class="text-emerald-300">R$ {{ number_format($comissao,2,',','.') }}</b></div>
    </div>
  </div>

  <div class="flex gap-2">
    <button type="button" @click="compartilhar()" class="btn flex-1 text-white" style="background:#22c55e">📲 Enviar no WhatsApp</button>
    <form method="post" action="{{ route('admin.acertos.baixa-dono',$embarcacao) }}" onsubmit="return confirm('Marcar como pago ao dono de {{ $embarcacao->nome }}?')" class="flex-1">@csrf
      @if($viagemId)<input type="hidden" name="viagem_id" value="{{ $viagemId }}">@endif
      <button class="btn btn-accent w-full">✓ Pagar dono (baixa)</button>
    </form>
  </div>

  <div class="card overflow-hidden">
    <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Passagens pendentes ({{ $passagens->count() }})</div>
    <div class="divide-y text-sm">
      @forelse($passagens as $p)
        <div class="flex justify-between p-3">
          <div><b>#{{ $p->numeroFormatado() }}</b> {{ $p->origem }} → {{ $p->destino }}
            <div class="text-xs text-slate-400">{{ $p->created_at->format('d/m H:i') }}@if($p->comprador_nome) · {{ $p->comprador_nome }}@endif</div>
          </div>
          <div class="text-right">
            <b>R$ {{ number_format((float)$p->total,2,',','.') }}</b>
            <div class="text-xs text-emerald-600">dono R$ {{ number_format((float)$p->total*(100-$pct)/100,2,',','.') }}</div>
          </div>
        </div>
      @empty <div class="p-6 text-center text-slate-400">Nada pendente pra este transporte.</div> @endforelse
    </div>
  </div>
</div>
<script>
function donoShare(txt){
  return {
    txt: txt,
    compartilhar(){
      try{ if(navigator.share){ navigator.share({title:'Acerto do transporte', text:this.txt}).catch(function(){}); return; } }catch(e){}
      window.open('https://wa.me/?text='+encodeURIComponent(this.txt),'_blank');
    }
  };
}
</script>
@endsection
