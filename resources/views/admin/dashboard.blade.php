@extends('layouts.app')
@section('titulo','Dashboard')
@section('conteudo')
@php
  use App\Support\Modulos;
  $brl = fn($v) => 'R$ '.number_format((float)$v,2,',','.');
  $maxMod = max(1, $porModulo->max('valor') ?? 1);
  $maxCanal = max(1, collect($porCanal)->max() ?? 1);
  $cadTotal = array_sum($cad);
@endphp

<div class="space-y-4 max-w-6xl mx-auto">
  <div class="flex items-center gap-3">
    <span class="text-2xl">📊</span>
    <div><div class="text-xl font-extrabold">Dashboard gerencial</div><div class="text-xs text-slate-500">Visão de todos os módulos</div></div>
  </div>

  <x-periodo :periodo="$periodo" :inicio="$inicio" :fim="$fim"></x-periodo>

  {{-- ===== KPIs ===== --}}
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="rounded-2xl p-4 text-white shadow" style="background:linear-gradient(135deg,#0b1a33,#1e3a5f)">
      <div class="text-[11px] uppercase opacity-70">Caixa (líquido)</div>
      <div class="text-2xl font-extrabold">{{ $brl($liquido) }}</div>
      <div class="text-[10px] opacity-70">entradas {{ $brl($entradas) }} · saídas {{ $brl($saidas) }}</div>
    </div>
    <div class="rounded-2xl p-4 shadow bg-white">
      <div class="text-[11px] uppercase text-slate-500">Recebido no período</div>
      <div class="text-2xl font-extrabold text-emerald-600">{{ $brl($entradas) }}</div>
      <div class="text-[10px] text-slate-400">todas as formas</div>
    </div>
    <div class="rounded-2xl p-4 shadow bg-white">
      <div class="text-[11px] uppercase text-slate-500">A receber (em aberto)</div>
      <div class="text-2xl font-extrabold text-red-600">{{ $brl($aReceber) }}</div>
      <div class="text-[10px] text-slate-400">{{ $totalDevedores }} pedido(s) em aberto</div>
    </div>
    <div class="rounded-2xl p-4 shadow bg-white">
      <div class="text-[11px] uppercase text-slate-500">Cadastros no período</div>
      <div class="text-2xl font-extrabold">{{ $cadTotal }}</div>
      <div class="text-[10px] text-slate-400">enc {{ $cad['encomenda'] }} · pas {{ $cad['passagem'] }} · cam {{ $cad['camarote'] }} · frete {{ $cad['frete'] }}</div>
    </div>
  </div>

  <div class="grid lg:grid-cols-2 gap-4">
    {{-- ===== Receita por módulo ===== --}}
    <div class="card p-4">
      <div class="font-bold text-sm text-slate-500 uppercase mb-3">Recebido por módulo</div>
      <div class="space-y-2">
        @forelse($porModulo->sortByDesc('valor') as $mod=>$row)
          @php $cor = Modulos::hex($mod); @endphp
          <div>
            <div class="flex justify-between text-sm"><span class="font-semibold">{{ Modulos::icone($mod) }} {{ Modulos::rotulo($mod) }}</span><span class="font-bold">{{ $brl($row->valor) }}</span></div>
            <div class="h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full" style="width:{{ round($row->valor/$maxMod*100) }}%;background:{{ $cor }}"></div></div>
          </div>
        @empty
          <div class="text-sm text-slate-400 py-4 text-center">Sem recebimentos no período.</div>
        @endforelse
      </div>
    </div>

    {{-- ===== Receita por forma ===== --}}
    <div class="card p-4">
      <div class="font-bold text-sm text-slate-500 uppercase mb-3">Recebido por forma</div>
      <div class="space-y-2">
        @php $temCanal = collect($porCanal)->sum() > 0; @endphp
        @if($temCanal)
          @foreach($canais as $ck=>$cl)
            @php $v = (float)($porCanal[$ck] ?? 0); @endphp
            @if($v>0)
            <div>
              <div class="flex justify-between text-sm"><span class="font-semibold">{{ $cl }}</span><span class="font-bold">{{ $brl($v) }}</span></div>
              <div class="h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full bg-indigo-500" style="width:{{ round($v/$maxCanal*100) }}%"></div></div>
            </div>
            @endif
          @endforeach
        @else
          <div class="text-sm text-slate-400 py-4 text-center">Sem recebimentos no período.</div>
        @endif
      </div>
    </div>
  </div>

  <div class="grid lg:grid-cols-2 gap-4">
    {{-- ===== Quem mais viaja ===== --}}
    <div class="card overflow-hidden">
      <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b flex items-center gap-2">🧍 Quem mais viaja <span class="text-[10px] font-normal normal-case text-slate-400">(passageiros)</span></div>
      <div class="divide-y">
        @forelse($topPassageiros as $i=>$p)
          <div class="flex items-center gap-3 p-3 text-sm">
            <span class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-500">{{ $i+1 }}</span>
            <div class="flex-1 min-w-0 font-semibold truncate">{{ $p->nome }}</div>
            <div class="text-right"><div class="font-bold">{{ $p->qtd }} viagem(ns)</div><div class="text-[11px] text-slate-400">{{ $brl($p->valor) }}</div></div>
          </div>
        @empty <div class="p-6 text-center text-slate-400 text-sm">Sem passageiros no período.</div> @endforelse
      </div>
    </div>

    {{-- ===== Quem mais envia ===== --}}
    <div class="card overflow-hidden">
      <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b flex items-center gap-2">📦 Quem mais envia <span class="text-[10px] font-normal normal-case text-slate-400">(encomendas + fretes)</span></div>
      <div class="divide-y">
        @forelse($topRemetentes as $i=>$r)
          <div class="flex items-center gap-3 p-3 text-sm">
            <span class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-500">{{ $i+1 }}</span>
            <div class="flex-1 min-w-0 font-semibold truncate">{{ $r->nome }}</div>
            <div class="text-right"><div class="font-bold">{{ $r->qtd }} envio(s)</div><div class="text-[11px] text-slate-400">{{ $brl($r->valor) }}</div></div>
          </div>
        @empty <div class="p-6 text-center text-slate-400 text-sm">Sem envios no período.</div> @endforelse
      </div>
    </div>
  </div>

  {{-- ===== Devedores há mais tempo ===== --}}
  <div class="card overflow-hidden">
    <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b flex items-center gap-2">⏳ Devendo há mais tempo <span class="text-[10px] font-normal normal-case text-slate-400">(em aberto, todos os módulos)</span></div>
    <div class="divide-y">
      @forelse($devedores as $d)
        @php $dias = (int) $d->data->diffInDays(now()); $cor = Modulos::hex($d->modulo); @endphp
        <a href="{{ $d->url }}" class="flex items-center gap-3 p-3 text-sm hover:bg-slate-50">
          <span class="chip shrink-0" style="background:{{ $cor }}20;color:{{ $cor }}">{{ Modulos::icone($d->modulo) }} #{{ $d->numero }}</span>
          <div class="flex-1 min-w-0 font-semibold truncate">{{ $d->cliente }}</div>
          <div class="text-right">
            <div class="font-bold text-red-600">{{ $brl($d->devido) }}</div>
            <div class="text-[11px] {{ $dias>=15 ? 'text-red-500 font-bold' : 'text-slate-400' }}">{{ $dias }} dia(s) · {{ $d->data->format('d/m/Y') }}</div>
          </div>
        </a>
      @empty <div class="p-6 text-center text-slate-400 text-sm">Ninguém devendo. 🎉</div> @endforelse
    </div>
    @if($totalDevedores > $devedores->count())
      <div class="p-2 text-center text-[11px] text-slate-400">Mostrando os {{ $devedores->count() }} mais antigos de {{ $totalDevedores }} em aberto.</div>
    @endif
  </div>

  {{-- ===== Top rotas ===== --}}
  @if($topRotas->count())
  <div class="card p-4">
    <div class="font-bold text-sm text-slate-500 uppercase mb-3">🗺️ Rotas mais movimentadas</div>
    <div class="flex flex-wrap gap-2">
      @foreach($topRotas as $r)
        <span class="chip bg-slate-100 text-slate-700">{{ $r->rota }} <b class="ml-1">{{ $r->qtd }}</b></span>
      @endforeach
    </div>
  </div>
  @endif
</div>
@endsection
