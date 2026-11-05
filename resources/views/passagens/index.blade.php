@extends('layouts.app')
@section('titulo','Passagens')
@php $__e=\App\Support\Tenant::empresa(); $cor = (($__e?->config('cores_por_modulo', true) ?? true) ? \App\Support\Modulos::hex('passagem') : ($__e?->cor_primaria ?? '#0b1a33')); @endphp
@section('conteudo')
<div class="space-y-4 max-w-5xl mx-auto">
  <div class="rounded-2xl px-4 py-3 text-white flex items-center gap-3" style="background:{{ $cor }}">
    <span class="text-2xl">🧾</span><div class="font-bold">Passagens / embarque</div>
    @can('cadastra', null)@endcan
    <a href="{{ route('passagens.manifesto', array_merge(request()->query(), ['print'=>1])) }}" target="_blank" class="ml-auto btn btn-sm bg-white/20 text-white">📋 Lista (PDF)</a>
    <a href="{{ route('passagens.create') }}" class="btn btn-sm bg-white/20 text-white">+ Nova</a>
  </div>

  {{-- filtro --}}
  <form method="get" class="card p-3 grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
    <input name="busca" value="{{ $f['busca'] ?? '' }}" placeholder="Nome, telefone ou nº" class="input col-span-2">
    <input type="date" name="inicio" value="{{ $f['inicio'] ?? '' }}" class="input">
    <input type="date" name="fim" value="{{ $f['fim'] ?? '' }}" class="input">
    <select name="status_pagamento" class="input">
      <option value="">Pagamento: todos</option>
      <option value="pago" @selected(($f['status_pagamento'] ?? '')==='pago')>Pago</option>
      <option value="parcial" @selected(($f['status_pagamento'] ?? '')==='parcial')>Parcial</option>
      <option value="pendente" @selected(($f['status_pagamento'] ?? '')==='pendente')>Não pago</option>
    </select>
    <select name="embarcou" class="input">
      <option value="">Embarque: todos</option>
      <option value="1" @selected(($f['embarcou'] ?? '')==='1')>Embarcou</option>
      <option value="0" @selected(($f['embarcou'] ?? '')==='0')>Não embarcou</option>
    </select>
    @if($verTodos)
    <select name="escopo" class="input">
      <option value="todos" @selected(($escopo ?? 'todos')==='todos')>Ver: todos</option>
      <option value="meu" @selected(($escopo ?? '')==='meu')>Ver: só os meus</option>
    </select>
    @endif
    <button class="btn text-white col-span-2 sm:col-span-1" style="background:{{ $cor }}">Filtrar</button>
  </form>

  {{-- totais --}}
  @php $verCanc = ($f['cancelada'] ?? '')==='1'; @endphp
  <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
    <div class="card p-3"><div class="text-xs text-slate-500">Passagens</div><div class="text-lg font-bold">{{ $totais['qtd'] }}</div></div>
    <div class="card p-3"><div class="text-xs text-slate-500">Passageiros</div><div class="text-lg font-bold">{{ $totais['passageiros'] }}</div></div>
    <div class="card p-3"><div class="text-xs text-slate-500">Recebido</div><div class="text-lg font-bold text-emerald-600">R$ {{ number_format($totais['recebido'],2,',','.') }}</div></div>
    <div class="card p-3"><div class="text-xs text-slate-500">A receber</div><div class="text-lg font-bold text-red-600">R$ {{ number_format($totais['a_receber'],2,',','.') }}</div></div>
    <a href="{{ request()->fullUrlWithQuery(['cancelada'=> $verCanc ? null : '1']) }}" class="card p-3 transition hover:bg-slate-50 {{ $verCanc ? 'ring-2 ring-slate-400 bg-slate-50' : '' }}">
      <div class="text-xs text-slate-500">❌ Cancelados {{ $verCanc ? '· vendo' : '' }}</div>
      <div class="text-lg font-bold text-slate-500">{{ $totais['cancelados'] }}</div>
      <div class="text-[11px] text-slate-400">R$ {{ number_format($totais['cancelados_valor'] ?? 0,2,',','.') }}</div>
    </a>
  </div>
  @if($verCanc)
  <div class="text-xs text-amber-600 -mt-2">Mostrando só as passagens <b>canceladas</b> do período. <a href="{{ request()->fullUrlWithQuery(['cancelada'=>null]) }}" class="underline">voltar pras ativas</a></div>
  @endif

  {{-- lista --}}
  <div class="card divide-y">
    @forelse($passagens as $p)
      <a href="{{ route('passagens.show',$p) }}" class="flex items-center gap-3 p-3 hover:bg-slate-50 row-{{ $p->cor() }}">
        <div class="flex-1 min-w-0">
          <div class="font-semibold">@if($p->tipo==='camarote')🛏️ @endif#{{ $p->numeroFormatado() }} · {{ $p->origem }} → {{ $p->destino }}</div>
          <div class="text-xs text-slate-500 truncate">{{ $p->qtd_passageiros }} pax · {{ $p->comprador_nome ?: '—' }} · vendeu: {{ $p->operador?->name }}@if($p->embarcou) · <span class="text-emerald-600 font-semibold">✅ check-in{{ $p->embarcouPor ? ': '.$p->embarcouPor->name : '' }}</span>@elseif(!$p->cancelada) · <span class="text-amber-600 font-semibold">⏳ sem check-in</span>@endif</div>
        </div>
        <div class="text-right">
          <div class="font-bold">R$ {{ number_format($p->total,2,',','.') }}</div>
          <span class="chip st-{{ $p->cor() }}">{{ $p->statusTexto() }}</span>
        </div>
      </a>
    @empty
      <div class="p-6 text-center text-slate-400">Nenhuma passagem no filtro.</div>
    @endforelse
  </div>
  <div>{{ $passagens->links() }}</div>
</div>
@endsection
