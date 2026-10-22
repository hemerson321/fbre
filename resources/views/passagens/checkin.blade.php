@extends('layouts.app')
@section('titulo','Check-in · '.$p->numeroFormatado())
@section('conteudo')
@php $emp = \App\Support\Tenant::empresa(); $cor = ($emp?->config('cores_por_modulo', true) ?? true) ? \App\Support\Modulos::hex('passagem') : ($emp?->cor_primaria ?? '#0b1a33'); @endphp
<div class="max-w-md mx-auto space-y-3">
  <div class="rounded-2xl px-4 py-3 text-white" style="background:{{ $cor }}">
    <div class="text-xs opacity-80">Check-in da passagem</div>
    <div class="text-2xl font-extrabold">{{ $p->numeroFormatado() }}</div>
    <div class="text-sm">{{ $p->origem }} → {{ $p->destino }}@if($p->data_viagem) · {{ $p->data_viagem->format('d/m/Y') }}@endif</div>
    @if($p->embarcacao)<div class="text-xs opacity-80">{{ $p->embarcacao->nome }}</div>@endif
  </div>

  <div class="card p-4">
    <div class="font-semibold mb-2">Passageiros ({{ $p->passageiros->count() }})</div>
    <div class="divide-y">
      @foreach($p->passageiros as $pax)
        <div class="flex items-center gap-2 py-2 text-sm">
          @php $mini = $pax->foto ?: $pax->foto_rg; @endphp
          @if($mini)<img src="/{{ $mini }}" class="h-9 w-9 object-cover rounded border shrink-0">@endif
          <div class="flex-1">{{ $pax->nome }}<div class="text-xs text-slate-500">{{ $pax->tipo }}@if($pax->poltrona) · poltrona {{ $pax->poltrona }}@endif</div></div>
          <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $pax->checkin ? 'bg-emerald-100 text-emerald-700':'bg-slate-200 text-slate-600' }}">{{ $pax->checkin ? 'check-in ✓':'sem check-in' }}</span>
        </div>
      @endforeach
    </div>
  </div>

  <div class="card p-4 text-center space-y-2">
    @if($p->cancelada)
      <div class="text-red-600 font-bold">Passagem cancelada.</div>
    @elseif($p->embarcou)
      <div class="text-emerald-600 font-extrabold text-lg">✅ Embarque já confirmado</div>
      <div class="text-xs text-slate-500">{{ optional($p->embarcou_em)->format('d/m/Y H:i') }}</div>
    @elseif($p->devido > 0.009 && $p->forma !== 'faturado')
      <div class="text-amber-700 font-semibold">Falta pagamento: R$ {{ number_format($p->devido,2,',','.') }}</div>
      <a href="{{ route('passagens.show',$p) }}" class="btn btn-ghost w-full">Abrir p/ receber</a>
    @elseif($u->permite('passagem','baixa'))
      <form method="post" action="{{ route('passagens.embarcar',$p) }}">@csrf
        <button class="btn w-full text-white text-lg py-3" style="background:{{ $cor }}">✅ Confirmar embarque</button>
      </form>
    @else
      <div class="text-slate-500 text-sm">Você não tem permissão para validar embarque.</div>
    @endif
    <a href="{{ route('passagens.show',$p) }}" class="text-xs text-slate-400 underline">ver detalhes</a>
  </div>
</div>
@endsection
