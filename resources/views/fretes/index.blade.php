@extends('layouts.app')
@section('titulo','Fretes')
@php $cor = \App\Support\Modulos::hex('frete'); @endphp
@section('conteudo')
<div class="space-y-4 max-w-5xl mx-auto">
  <div class="rounded-2xl px-4 py-3 text-white flex items-center gap-3" style="background:{{ $cor }}">
    <span class="text-2xl">🚢</span><div class="font-bold">Fretes / entrega</div>
    @if(auth()->user()->permite('frete','cadastra'))<a href="{{ route('fretes.create') }}" class="ml-auto btn btn-sm bg-white/20 text-white">+ Novo</a>@endif
  </div>
  <form method="get" class="card p-3 grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
    <input name="busca" value="{{ $f['busca'] ?? '' }}" placeholder="Nome, carga ou nº" class="input col-span-2">
    <input type="date" name="inicio" value="{{ $f['inicio'] ?? '' }}" class="input">
    <input type="date" name="fim" value="{{ $f['fim'] ?? '' }}" class="input">
    <select name="status_pagamento" class="input"><option value="">Pagamento: todos</option>
      <option value="pago" @selected(($f['status_pagamento'] ?? '')==='pago')>Pago</option>
      <option value="parcial" @selected(($f['status_pagamento'] ?? '')==='parcial')>Parcial</option>
      <option value="pendente" @selected(($f['status_pagamento'] ?? '')==='pendente')>Não pago</option></select>
    <select name="entregue" class="input"><option value="">Entrega: todos</option>
      <option value="1" @selected(($f['entregue'] ?? '')==='1')>Entregue</option>
      <option value="0" @selected(($f['entregue'] ?? '')==='0')>Não entregue</option></select>
    <button class="btn text-white col-span-2 sm:col-span-1" style="background:{{ $cor }}">Filtrar</button>
  </form>
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
    <div class="card p-3"><div class="text-xs text-slate-500">Fretes</div><div class="text-lg font-bold">{{ $totais['qtd'] }}</div></div>
    <div class="card p-3"><div class="text-xs text-slate-500">Peso total</div><div class="text-lg font-bold">{{ number_format($totais['peso'],0,',','.') }} kg</div></div>
    <div class="card p-3"><div class="text-xs text-slate-500">Recebido</div><div class="text-lg font-bold text-emerald-600">R$ {{ number_format($totais['recebido'],2,',','.') }}</div></div>
    <div class="card p-3"><div class="text-xs text-slate-500">A receber</div><div class="text-lg font-bold text-red-600">R$ {{ number_format($totais['a_receber'],2,',','.') }}</div></div>
  </div>
  <div class="card divide-y">
    @forelse($fretes as $fr)
      <a href="{{ route('fretes.show',$fr) }}" class="flex items-center gap-3 p-3 hover:bg-slate-50 row-{{ $fr->cor() }}">
        <div class="flex-1 min-w-0">
          <div class="font-semibold">#{{ $fr->numeroFormatado() }} · {{ $fr->origem }} → {{ $fr->destino }}</div>
          <div class="text-xs text-slate-500 truncate">{{ \Illuminate\Support\Str::limit($fr->descricao,40) }} · {{ $fr->remetente_nome }}</div>
        </div>
        <div class="text-right"><div class="font-bold">R$ {{ number_format($fr->total,2,',','.') }}</div><span class="chip st-{{ $fr->cor() }}">{{ $fr->statusTexto() }}</span></div>
      </a>
    @empty <div class="p-6 text-center text-slate-400">Nenhum frete no filtro.</div> @endforelse
  </div>
  <div>{{ $fretes->links() }}</div>
</div>
@endsection
