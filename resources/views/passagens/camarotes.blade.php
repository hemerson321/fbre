@extends('layouts.app')
@section('titulo','Camarotes · ocupação')
@section('conteudo')
<div class="max-w-3xl mx-auto space-y-4">
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <div>
      <h1 class="text-lg font-bold">🛏️ Camarotes · ocupação e reservas</h1>
      <p class="text-xs text-slate-500">Veja o que está ocupado, as reservas em aberto e dê baixa quando o cliente vier pagar o restante.</p>
    </div>
    <a href="{{ route('passagens.camarote.create') }}" class="btn btn-sm btn-primary">🎫 Vender</a>
  </div>

  <form method="GET" class="card p-3 flex items-end gap-2">
    <label class="block flex-1"><span class="text-xs text-slate-500">Viagem</span>
      <select name="viagem_id" onchange="this.form.submit()" class="input py-2">
        <option value="">Próximas viagens</option>
        @foreach($viagens as $v)
          <option value="{{ $v['id'] }}" @selected((string)$viagemId === (string)$v['id'])>{{ $v['label'] }}</option>
        @endforeach
      </select></label>
    <button class="btn btn-sm btn-ghost">Filtrar</button>
    @if($viagemId)<a href="{{ route('passagens.camarotes') }}" class="btn btn-sm btn-ghost">Próximas</a>@endif
  </form>
  @if($viagens->isEmpty())<p class="text-xs text-amber-600">Cadastre uma rota com paradas pra gerar as viagens.</p>@endif

  @forelse($camarotes as $c)
    @php $lista = $vendas[$c->id] ?? collect(); @endphp
    <div class="card p-4 space-y-2">
      <div class="flex items-center justify-between">
        <div class="font-bold">🛏️ {{ $c->nome }} <span class="text-xs font-normal text-slate-400">· até {{ $c->capacidade }} pessoa(s)</span></div>
        @if($lista->isEmpty())
          <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Livre</span>
        @else
          <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $lista->count() }} na viagem</span>
        @endif
      </div>

      @forelse($lista as $p)
        @php
          $reserva = $p->devido > 0.009;
          $checkin = $p->passageiros->count() && $p->passageiros->every(fn($x)=>$x->checkin) && $p->embarcou;
        @endphp
        <div class="rounded-xl border border-slate-200 p-3 flex items-center gap-3 flex-wrap">
          <div class="flex-1 min-w-[180px]">
            <div class="font-semibold text-sm">{{ $p->origem }} → {{ $p->destino }}
              @if($p->data_viagem)<span class="text-slate-400 font-normal">· {{ $p->data_viagem->format('d/m/Y') }}</span>@endif
            </div>
            <div class="text-xs text-slate-500">
              #{{ $p->numeroFormatado() }} · {{ $p->comprador_nome ?: '—' }} · {{ $p->passageiros->count() }} pax
            </div>
          </div>
          <div class="text-right">
            <div class="text-sm font-bold">R$ {{ number_format($p->total,2,',','.') }}</div>
            @if($reserva)
              <div class="text-[11px] text-amber-600 font-semibold">Reserva · falta R$ {{ number_format($p->devido,2,',','.') }}</div>
            @elseif($checkin)
              <div class="text-[11px] text-emerald-600 font-semibold">Pago · check-in ok</div>
            @else
              <div class="text-[11px] text-emerald-600 font-semibold">Pago</div>
            @endif
          </div>
          <div class="flex gap-1.5">
            @if($reserva)
              <a href="{{ route('passagens.show', $p) }}" class="btn btn-sm btn-accent">💰 Dar baixa</a>
            @else
              <a href="{{ route('passagens.show', $p) }}" class="btn btn-sm btn-ghost">Ver</a>
            @endif
          </div>
        </div>
      @empty
        <p class="text-xs text-slate-400">Nenhuma venda/reserva {{ $viagemId ? 'nessa viagem' : 'nas próximas viagens' }}.</p>
      @endforelse
    </div>
  @empty
    <div class="card p-4 text-sm text-amber-700 bg-amber-50">
      Nenhuma suíte/camarote cadastrada. <a href="{{ route('admin.camarotes') }}" class="underline font-semibold">Cadastrar</a>.
    </div>
  @endforelse
</div>
@endsection
