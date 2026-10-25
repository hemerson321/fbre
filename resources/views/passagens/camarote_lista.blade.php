@extends('layouts.app')
@section('titulo','Camarotes / embarque')
@php $__e=\App\Support\Tenant::empresa(); $cor = (($__e?->config('cores_por_modulo', true) ?? true) ? \App\Support\Modulos::hex('camarote') : ($__e?->cor_primaria ?? '#0b1a33')); @endphp
@section('conteudo')
<div class="space-y-4 max-w-5xl mx-auto">
  <div class="rounded-2xl px-4 py-3 text-white flex items-center gap-3" style="background:{{ $cor }}">
    <span class="text-2xl">🛏️</span><div class="font-bold">Camarotes / embarque</div>
    <a href="{{ route('passagens.manifesto', array_merge(request()->query(), ['print'=>1])) }}" target="_blank" class="ml-auto btn btn-sm bg-white/20 text-white">📋 Lista (PDF)</a>
    <a href="{{ route('passagens.camarote.create') }}" class="btn btn-sm bg-white/20 text-white">+ Vender</a>
  </div>

  {{-- filtro detalhado --}}
  <form method="get" class="card p-3 grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
    <input name="busca" value="{{ $f['busca'] ?? '' }}" placeholder="Nome, telefone ou nº" class="input col-span-2">
    <input type="date" name="inicio" value="{{ $f['inicio'] ?? '' }}" class="input">
    <input type="date" name="fim" value="{{ $f['fim'] ?? '' }}" class="input">

    <select name="origem" class="input">
      <option value="">Origem: todas</option>
      @foreach($localidades as $l)<option value="{{ $l }}" @selected(($f['origem'] ?? '')===$l)>{{ $l }}</option>@endforeach
    </select>
    <select name="destino" class="input">
      <option value="">Destino: todos</option>
      @foreach($localidades as $l)<option value="{{ $l }}" @selected(($f['destino'] ?? '')===$l)>{{ $l }}</option>@endforeach
    </select>
    <select name="status_pagamento" class="input">
      <option value="">Pagamento: todos</option>
      <option value="pago" @selected(($f['status_pagamento'] ?? '')==='pago')>Pago</option>
      <option value="parcial" @selected(($f['status_pagamento'] ?? '')==='parcial')>Reserva (parcial)</option>
      <option value="pendente" @selected(($f['status_pagamento'] ?? '')==='pendente')>Não pago</option>
    </select>
    <select name="embarcou" class="input">
      <option value="">Embarque: todos</option>
      <option value="1" @selected(($f['embarcou'] ?? '')==='1')>Embarcou</option>
      <option value="0" @selected(($f['embarcou'] ?? '')==='0')>Não embarcou</option>
    </select>

    <select name="user_id" class="input col-span-2 sm:col-span-1">
      <option value="">Operador: todos</option>
      @foreach($operadores as $op)<option value="{{ $op->id }}" @selected((string)($f['user_id'] ?? '')===(string)$op->id)>{{ $op->name }}</option>@endforeach
    </select>
    <button class="btn text-white col-span-2 sm:col-span-1" style="background:{{ $cor }}">Filtrar</button>
    <a href="{{ route('passagens.camarote.lista') }}" class="btn btn-ghost col-span-2 sm:col-span-1">Limpar</a>
  </form>

  {{-- totais --}}
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
    <div class="card p-3"><div class="text-xs text-slate-500">Camarotes</div><div class="text-lg font-bold">{{ $totais['qtd'] }}</div></div>
    <div class="card p-3"><div class="text-xs text-slate-500">Passageiros</div><div class="text-lg font-bold">{{ $totais['passageiros'] }}</div></div>
    <div class="card p-3"><div class="text-xs text-slate-500">Recebido</div><div class="text-lg font-bold text-emerald-600">R$ {{ number_format($totais['recebido'],2,',','.') }}</div></div>
    <div class="card p-3"><div class="text-xs text-slate-500">A receber</div><div class="text-lg font-bold text-red-600">R$ {{ number_format($totais['a_receber'],2,',','.') }}</div></div>
  </div>

  {{-- lista --}}
  <div class="card divide-y">
    @forelse($passagens as $p)
      <a href="{{ route('passagens.show',$p) }}" class="flex items-center gap-3 p-3 hover:bg-slate-50 row-{{ $p->cor() }}">
        <div class="flex-1 min-w-0">
          <div class="font-semibold">🛏️ {{ $p->camarote?->nome ?: 'Camarote' }} · #{{ $p->numeroFormatado() }} · {{ $p->origem }} → {{ $p->destino }}</div>
          <div class="text-xs text-slate-500 truncate">
            {{ $p->qtd_passageiros }} pax · {{ $p->comprador_nome ?: '—' }} · {{ $p->operador?->name }}
            @if($p->data_viagem)· {{ $p->data_viagem->format('d/m/Y') }}@endif
          </div>
        </div>
        <div class="text-right">
          <div class="font-bold">R$ {{ number_format($p->total,2,',','.') }}</div>
          <span class="chip st-{{ $p->cor() }}">{{ $p->statusTexto() }}</span>
          @if($p->devido>0.009)<div class="text-[11px] text-amber-600 font-semibold">falta R$ {{ number_format($p->devido,2,',','.') }}</div>@endif
        </div>
      </a>
    @empty
      <div class="p-6 text-center text-slate-400">Nenhum camarote no filtro.</div>
    @endforelse
  </div>
  <div>{{ $passagens->links() }}</div>
</div>
@endsection
