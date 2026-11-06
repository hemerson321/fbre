@extends('layouts.app')
@section('titulo','Prestação de contas — dono')
@section('conteudo')
@php
  $moeda = fn($v) => 'R$ '.number_format((float)$v,2,',','.');
  $shareText = "*PRESTAÇÃO DE CONTAS — {$embarcacao->nome}*\n".
    ($embarcacao->dono_nome ? "Dono: {$embarcacao->dono_nome}\n" : '').
    "Data: ".now()->format('d/m/Y H:i')."\n".
    "————————————————\n".
    "🏬 BALCÃO (empresa): {$balcao->count()} passagem(ns)\n".
    "   Vendido: ".$moeda($brutoBalcao)."  →  Dono: ".$moeda($donoBalcao)."\n".
    "🤝 AGENTES (repasse): {$agentes->count()} passagem(ns)\n".
    "   Vendido: ".$moeda($brutoAgentes)."  →  Dono: ".$moeda($donoAgentes)."\n".
    "————————————————\n".
    "Total vendido: ".$moeda($bruto)."\n".
    "Comissão da empresa ({$pct}%): ".$moeda($comissao)."\n".
    "*A PAGAR AO DONO: ".$moeda($dono)."*";
@endphp
<div class="max-w-2xl mx-auto space-y-4" x-data="donoShare(@js($shareText))">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold">🚤 {{ $embarcacao->nome }}</h1>
      <p class="text-xs text-slate-500">Prestação de contas · Dono: <b>{{ $embarcacao->dono_nome ?: '—' }}</b> · empresa fica {{ $pct }}%</p>
    </div>
    <a href="{{ route('admin.acertos', ['aba'=>'transportes']) }}" class="btn btn-ghost btn-sm">voltar</a>
  </div>

  {{-- total a pagar --}}
  <div class="card p-4" style="background:linear-gradient(135deg,#0b1a33,#13294d);color:#fff">
    <div class="text-xs opacity-70">A pagar ao dono</div>
    <div class="text-3xl font-extrabold text-amber-300 mb-2">{{ $moeda($dono) }}</div>
    <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
      <div><span class="opacity-70">Total vendido:</span> <b>{{ $moeda($bruto) }}</b></div>
      <div><span class="opacity-70">Comissão da empresa ({{ $pct }}%):</span> <b class="text-emerald-300">{{ $moeda($comissao) }}</b></div>
    </div>
  </div>

  {{-- resumo por canal --}}
  <div class="grid grid-cols-2 gap-2">
    <div class="card p-3">
      <div class="text-[11px] font-bold uppercase text-slate-500">🏬 Balcão (empresa)</div>
      <div class="text-xs text-slate-400 mb-1">{{ $balcao->count() }} passagem(ns)</div>
      <div class="text-sm">Vendido: <b>{{ $moeda($brutoBalcao) }}</b></div>
      <div class="text-sm text-emerald-600">Dono: <b>{{ $moeda($donoBalcao) }}</b></div>
    </div>
    <div class="card p-3">
      <div class="text-[11px] font-bold uppercase text-slate-500">🤝 Agentes (repasse)</div>
      <div class="text-xs text-slate-400 mb-1">{{ $agentes->count() }} passagem(ns)</div>
      <div class="text-sm">Vendido: <b>{{ $moeda($brutoAgentes) }}</b></div>
      <div class="text-sm text-emerald-600">Dono: <b>{{ $moeda($donoAgentes) }}</b></div>
    </div>
  </div>

  <p class="text-[11px] text-slate-400 leading-snug">
    <b>Balcão</b>: vendas feitas pela empresa — o dono recebe o total menos a comissão da empresa ({{ $pct }}%).
    <b>Agentes</b>: vendas de agentes de repasse — a empresa só intermedia, então o dono recebe o repasse integral (o agente fica com a comissão dele).
  </p>

  {{-- ações --}}
  <div class="flex gap-2">
    <a href="{{ route('admin.acertos.dono-relatorio',$embarcacao) }}?pdf=1{{ ($dataViagem ?? null) ? '&data_viagem='.$dataViagem : '' }}" target="_blank" class="btn btn-ghost flex-1" style="border:1px solid #334155">🖨 PDF / Imprimir</a>
    <button type="button" @click="compartilhar()" class="btn flex-1 text-white" style="background:#22c55e">📲 WhatsApp</button>
    <form method="post" action="{{ route('admin.acertos.baixa-dono',$embarcacao) }}" onsubmit="return confirm('Confirmar pagamento ao dono de {{ $embarcacao->nome }}? Isso dá baixa e lança a saída no caixa.')" class="flex-1">@csrf
      @if($viagemId)<input type="hidden" name="viagem_id" value="{{ $viagemId }}">@endif
      @if($dataViagem ?? null)<input type="hidden" name="data_viagem" value="{{ $dataViagem }}">@endif
      <button class="btn btn-accent w-full">✓ Pagar dono (baixa)</button>
    </form>
  </div>

  {{-- lista: balcão --}}
  @if($balcao->isNotEmpty())
  <div class="card overflow-hidden">
    <div class="p-3 font-bold text-sm text-slate-600 border-b bg-slate-50 flex justify-between">
      <span>🏬 Vendas de balcão</span><span class="text-emerald-600">dono {{ $moeda($donoBalcao) }}</span>
    </div>
    <div class="divide-y text-sm">
      @foreach($balcao as $p)
        <div class="flex justify-between p-3">
          <div class="min-w-0">
            <b>#{{ $p->numeroFormatado() }}</b> {{ $p->origem }} → {{ $p->destino }}
            <div class="text-xs text-slate-400">{{ $p->created_at->format('d/m H:i') }} · vendeu {{ $p->operador?->name ?: '—' }}@if($p->comprador_nome) · {{ $p->comprador_nome }}@endif</div>
          </div>
          <div class="text-right whitespace-nowrap">
            <b>{{ $moeda($p->total) }}</b>
            <div class="text-xs text-emerald-600">dono {{ $moeda($p->dono_valor) }}</div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
  @endif

  {{-- lista: agentes --}}
  @if($agentes->isNotEmpty())
  <div class="card overflow-hidden">
    <div class="p-3 font-bold text-sm text-slate-600 border-b bg-slate-50 flex justify-between">
      <span>🤝 Vendas de agentes</span><span class="text-emerald-600">dono {{ $moeda($donoAgentes) }}</span>
    </div>
    <div class="divide-y text-sm">
      @foreach($agentes as $p)
        <div class="flex justify-between p-3">
          <div class="min-w-0">
            <b>#{{ $p->numeroFormatado() }}</b> {{ $p->origem }} → {{ $p->destino }}
            <div class="text-xs text-slate-400">{{ $p->created_at->format('d/m H:i') }} · agente {{ $p->operador?->name ?: '—' }}@if($p->comprador_nome) · {{ $p->comprador_nome }}@endif</div>
          </div>
          <div class="text-right whitespace-nowrap">
            <b>{{ $moeda($p->total) }}</b>
            <div class="text-xs text-emerald-600">dono {{ $moeda($p->dono_valor) }}</div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
  @endif

  @if($balcao->isEmpty() && $agentes->isEmpty())
    <div class="card p-6 text-center text-slate-400">Nada pendente pra este transporte.</div>
  @endif
</div>
<script>
function donoShare(txt){
  return {
    txt: txt,
    compartilhar(){
      try{ if(navigator.share){ navigator.share({title:'Prestação de contas', text:this.txt}).catch(function(){}); return; } }catch(e){}
      window.open('https://wa.me/?text='+encodeURIComponent(this.txt),'_blank');
    }
  };
}
</script>
@endsection
