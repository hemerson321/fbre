@extends('layouts.app')
@section('titulo','Passagem '.$p->numeroFormatado())
@php $__e=\App\Support\Tenant::empresa(); $cor = (($__e?->config('cores_por_modulo', true) ?? true) ? \App\Support\Modulos::hex('passagem') : ($__e?->cor_primaria ?? '#0b1a33')); $u=auth()->user(); @endphp
@section('conteudo')
<div class="max-w-2xl mx-auto space-y-4">
  <div class="rounded-2xl px-4 py-3 text-white flex items-center gap-3" style="background:{{ $cor }}">
    <span class="text-2xl">🎫</span>
    <div class="flex-1"><div class="font-bold leading-tight">Passagem #{{ $p->numeroFormatado() }}</div>
      <div class="text-[11px] opacity-80">{{ $p->origem }} → {{ $p->destino }} @if($p->data_viagem)· {{ $p->data_viagem->format('d/m/Y') }}@endif</div></div>
    <span class="chip st-{{ $p->cor() }}">{{ $p->statusTexto() }}</span>
  </div>

  <div class="card p-4 space-y-1 text-sm">
    @if($p->tipo==='camarote' && $p->camarote)<div class="flex justify-between"><span class="text-slate-500">Suíte/Camarote</span><b>🛏️ {{ $p->camarote->nome }}</b></div>@endif
    <div class="flex justify-between"><span class="text-slate-500">Embarcação</span><b>{{ $p->embarcacao?->nome ?: '—' }}</b></div>
    <div class="flex justify-between"><span class="text-slate-500">Comprador</span><b>{{ $p->comprador_nome ?: '—' }}</b></div>
    <div class="flex justify-between"><span class="text-slate-500">Operador</span><b>{{ $p->operador?->name }}</b></div>
    <div class="flex justify-between"><span class="text-slate-500">Forma</span><b>{{ \App\Models\Passagem::FORMAS[$p->forma] ?? $p->forma }}</b></div>
  </div>

  <div class="card p-4">
    <div class="font-semibold mb-2">Passageiros</div>
    <div class="divide-y">
      @foreach($p->passageiros as $pax)
        <div class="flex justify-between py-2 text-sm">
          <div class="flex gap-2">
@php $miniatura = $pax->foto ?: $pax->foto_rg; @endphp @if($miniatura)<a href="/{{ $pax->foto_rg ?: $pax->foto }}" target="_blank" title="Ver documento"><img src="/{{ $miniatura }}" class="h-10 w-10 object-cover rounded border shrink-0"></a>@endif
            <div>{{ $pax->nome }}
            <span class="inline-block text-[10px] font-bold px-1.5 py-0.5 rounded {{ $pax->checkin ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $pax->checkin ? 'check-in ✓' : 'sem check-in' }}</span>
            <div class="text-xs text-slate-500">{{ $pax->tipo }} ({{ $pax->percentual }}%)@if($pax->poltrona) · poltrona {{ $pax->poltrona }}@endif @if($pax->documento)· {{ $pax->documento }}@endif</div>@if($pax->valor_integral > $pax->valor_total + 0.009)<div class="text-xs text-amber-600">Preço normal R$ {{ number_format($pax->valor_integral,2,',','.') }} · Pago R$ {{ number_format($pax->valor_total,2,',','.') }}</div>@endif<div class="text-xs text-slate-400">Transp. R$ {{ number_format($pax->valor_passagem,2,',','.') }}@if(($pax->valor_alimentacao + $pax->valor_taxa)>0) · Alim.+Adic. R$ {{ number_format($pax->valor_alimentacao + $pax->valor_taxa,2,',','.') }}@endif</div></div></div>
          <div class="text-right font-semibold">R$ {{ number_format($pax->valor_total,2,',','.') }}</div>
        </div>
      @endforeach
    </div>
    <div class="border-t mt-2 pt-2 space-y-1 text-sm">
      <div class="flex justify-between"><span>Tarifa de Transporte</span><span>R$ {{ number_format($p->subtotal,2,',','.') }}</span></div>
      <div class="flex justify-between"><span>Alimentação + Adicional</span><span>R$ {{ number_format($p->alimentacao_total + $p->taxa_total,2,',','.') }}</span></div>
      @if($p->desconto>0)<div class="flex justify-between text-amber-600"><span>Desconto</span><span>- R$ {{ number_format($p->desconto,2,',','.') }}</span></div>@endif
      <div class="flex justify-between text-lg font-bold" style="color:{{ $cor }}"><span>Total</span><span>R$ {{ number_format($p->total,2,',','.') }}</span></div>
      <div class="flex justify-between"><span>Pago</span><span>R$ {{ number_format($p->pago,2,',','.') }}</span></div>
      <div class="flex justify-between {{ $p->devido>0.009?'text-red-600 font-semibold':'text-emerald-600' }}"><span>Devido</span><span>R$ {{ number_format($p->devido,2,',','.') }}</span></div>
    </div>
  </div>

  {{-- baixa de pagamento --}}
  @if(!$p->cancelada && $p->devido>0.009 && $u->permite('passagem','baixa'))
  <form method="post" action="{{ route('passagens.pagar',$p) }}" class="card p-4 space-y-3" x-data="{d:0,px:0,db:0,cr:0}">
    @csrf
    <div class="font-semibold">Receber pagamento</div>
    <div class="grid grid-cols-2 gap-2">
      <div><label class="text-[11px] text-slate-500">Dinheiro</label><input name="dinheiro" class="input" x-money="d"></div>
      <div><label class="text-[11px] text-slate-500">PIX</label><input name="pix" class="input" x-money="px"></div>
      <div><label class="text-[11px] text-slate-500">Débito</label><input name="debito" class="input" x-money="db"></div>
      <div><label class="text-[11px] text-slate-500">Crédito</label><input name="credito" class="input" x-money="cr"></div>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="embarcar" value="1" class="w-5 h-5 rounded"> Confirmar embarque ao pagar</label>
    <button class="btn w-full text-white" style="background:{{ $cor }}">Registrar pagamento</button>
  </form>
  @endif

  {{-- embarque --}}
  @if(!$p->cancelada && !$p->embarcou && ($p->devido<=0.009 || $p->forma==='faturado') && $u->permite('passagem','baixa'))
  <form method="post" action="{{ route('passagens.embarcar',$p) }}">@csrf
    <button class="btn btn-ghost w-full">✅ Confirmar embarque</button>
  </form>
  @endif

  @php $empP = \App\Support\Tenant::empresa(); @endphp
  @if(!$p->cancelada && $empP && $empP->usaPix('passagem') && $p->devido > 0)
  <button onclick="FBRE.abrirImpressao('{{ route('imprimir.pix.passagem',$p) }}')" class="btn btn-accent w-full">💠 Gerar PIX dinâmico (imprimir QR)</button>
  @endif

  <div class="flex gap-2">
    <button onclick="FBRE.abrirImpressao('{{ route('passagens.comprovante',$p) }}')" class="btn btn-ghost flex-1">🖨 Comprovante</button>
    <button onclick="window.open('{{ route('passagens.comprovante',$p) }}?modo=pdf','_blank')" class="btn btn-ghost flex-1">📄 PDF / Enviar</button>
    @if(!$p->cancelada && ($u->isAdmin()||$u->isSuper()))
    <button type="button" class="btn btn-danger" x-data @click="$refs.cancel.classList.toggle('hidden')">Cancelar</button>
    @endif
  </div>
  @if(!$p->cancelada && ($u->isAdmin()||$u->isSuper()))
  <form method="post" action="{{ route('passagens.cancelar',$p) }}" class="card p-4 hidden" x-ref="cancel">@csrf
    <label class="label">Motivo do cancelamento</label>
    <input name="motivo" class="input mb-2" required minlength="3">
    <button class="btn btn-danger w-full">Confirmar cancelamento</button>
  </form>
  @endif
  @if($p->cancelada)<div class="card p-3 text-center text-slate-500 text-sm">Cancelada: {{ $p->motivo_cancelamento }}</div>@endif
</div>
@endsection
@push('scripts')
@if(request('print'))
<script>document.addEventListener('DOMContentLoaded',()=>FBRE.abrirImpressao('{{ route('passagens.comprovante',$p) }}'));</script>
@endif
@endpush
