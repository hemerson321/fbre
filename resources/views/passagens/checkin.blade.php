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

  @php $podeBaixa = $u->permite('passagem','baixa') && ! $p->cancelada && ($p->devido <= 0.009 || $p->forma === 'faturado'); @endphp
  <div class="card p-4">
    @php $trocados = $p->passageiros->filter(fn ($x) => ! empty($x->transf_de_lancha)); @endphp
    @if($trocados->isNotEmpty())
    <div class="mb-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
      <div class="font-extrabold">⚠ Mudança de transporte — avise o passageiro</div>
      @foreach($trocados as $x)
      <div class="mt-1">{{ $x->nome }}: antes <b>{{ $x->transf_de_lancha }}</b>{{ $x->transf_de_poltrona ? ' · poltrona '.$x->transf_de_poltrona : '' }}{{ $x->transf_de_bilhete ? ' · bilhete '.$x->transf_de_bilhete : '' }} → agora <b>{{ $p->embarcacao?->nome }}{{ $x->poltrona ? ' · poltrona '.$x->poltrona : '' }}</b></div>
      @endforeach
    </div>
    @endif
    <div class="font-semibold mb-2">Passageiros (<span data-chkcount>{{ $p->passageiros->where('checkin',true)->count() }}/{{ $p->passageiros->count() }}</span>)</div>
    <div class="divide-y">
      @foreach($p->passageiros as $pax)
        <div class="flex items-center gap-2 py-2 text-sm">
          @php $mini = $pax->foto ?: $pax->foto_rg; @endphp
          @if($mini)<img src="/{{ $mini }}" class="h-9 w-9 object-cover rounded border shrink-0">@endif
          <div class="flex-1">{{ $pax->nome }}@if($pax->papel==='colo')<span class="text-[10px] font-bold text-sky-600"> · 👶 colo</span>@endif<div class="text-xs text-slate-500">{{ $pax->tipo }}@if($pax->poltrona) · poltrona {{ $pax->poltrona }}@endif</div></div>
          <button type="button" title="Imprimir etiqueta de bagagem" class="rounded-lg px-2.5 py-1.5 text-[15px] font-bold bg-white text-slate-600 border border-slate-300 shrink-0" onclick="var n=prompt('Quantos volumes de bagagem?','1'); if(n && +n>0){ var vol=Math.min(99,Math.max(1,parseInt(n)||1)); FBRE.imprimirDiretoUrl(this,'{{ route('passagens.etiqueta',[$p,$pax]) }}?volumes='+vol,'{{ route('passagens.etiqueta-direto',[$p,$pax]) }}'); }">🏷️<span class="hidden sm:inline text-[12px]"> Etiqueta</span></button>
          <button type="button" title="Imprimir via do passageiro" onclick="FBRE.imprimirDiretoUrl(this,'{{ route('passagens.etiqueta',[$p,$pax]) }}?so_via=1','{{ route('passagens.etiqueta-direto',[$p,$pax]) }}')" class="rounded-lg px-2.5 py-1.5 text-[15px] font-bold bg-white text-slate-600 border border-slate-300 shrink-0">🧾<span class="hidden sm:inline text-[12px]"> Via</span></button>
          @if($podeBaixa)
          <button type="button" class="chkbtn rounded-lg px-3 py-1.5 text-[13px] font-bold {{ $pax->checkin ? 'bg-emerald-600 text-white border border-emerald-600' : 'bg-white text-slate-600 border border-slate-300' }}" onclick="FBRE.checkinPax(this,'{{ route('passagens.show',$p) }}/checkin-pax/{{ $pax->id }}')">{{ $pax->checkin ? '✓ embarcou' : 'Check-in' }}</button>
          @else
          <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $pax->checkin ? 'bg-emerald-100 text-emerald-700':'bg-slate-200 text-slate-600' }}">{{ $pax->checkin ? 'check-in ✓':'sem check-in' }}</span>
          @endif
        </div>
      @endforeach
    </div>
  </div>

  <div class="card p-4 text-center space-y-2">
    @if($p->cancelada)
      <div class="text-red-600 font-bold">Passagem cancelada.</div>
    @elseif($p->devido > 0.009 && $p->forma !== 'faturado')
      <div class="text-amber-700 font-semibold">Falta pagamento: R$ {{ number_format($p->devido,2,',','.') }}</div>
      <a href="{{ route('passagens.show',$p) }}" class="btn btn-ghost w-full">Abrir p/ receber</a>
    @elseif($u->permite('passagem','baixa'))
      <div class="text-xs text-slate-500">Check-in individual — toque em cada passageiro acima.</div>
    @else
      <div class="text-slate-500 text-sm">Você não tem permissão para validar embarque.</div>
    @endif
    <a href="{{ route('passagens.show',$p) }}" class="text-xs text-slate-400 underline">ver detalhes</a>
  </div>
</div>
@endsection
