@extends('layouts.app')
@section('titulo','Minhas vendas')
@section('conteudo')
@php $br = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'); @endphp
<div class="max-w-3xl mx-auto space-y-3">
  <div class="flex items-center justify-between gap-2">
    <h1 class="text-xl font-bold leading-tight">Minhas vendas <span class="block text-slate-400 font-normal text-sm">{{ auth()->user()->name }}</span></h1>
    @if($ehAgente)<a href="{{ route('meus-acertos') }}" class="btn btn-ghost btn-sm whitespace-nowrap no-print">🤝 Acertos</a>@endif
  </div>

  {{-- filtros: lancha + viagem --}}
  <form method="get" class="card p-3 grid grid-cols-2 gap-2 no-print">
    <div><label class="label">Transporte</label>
      <select name="transporte_id" class="input !min-h-[42px] w-full" onchange="this.form.viagem.value=''; this.form.submit()">
        <option value="">Todos</option>
        @foreach($transportes as $t)<option value="{{ $t->id }}" @selected($transporteId === $t->id)>{{ $t->nome }}</option>@endforeach
      </select></div>
    <div><label class="label">Data da viagem</label>
      <select name="viagem" class="input !min-h-[42px] w-full" onchange="this.form.submit()">
        <option value="">Último mês (todas)</option>
        @foreach($viagens as $v)<option value="{{ $v['valor'] }}" @selected($viagemSel === $v['valor'])>{{ $v['rotulo'] }}</option>@endforeach
      </select></div>
  </form>

  {{-- resumo: vendi / minha comissão / devo repassar --}}
  <div class="grid grid-cols-2 gap-2">
    <div class="card p-3">
      <div class="text-[11px] font-bold text-slate-500 uppercase">🎟️ Vendi <span class="font-normal normal-case text-slate-400">· {{ $resumo['periodo'] }}</span></div>
      <div class="text-xl font-extrabold leading-tight">{{ $br($resumo['vendi']) }}</div>
      <div class="text-[11px] text-slate-400">{{ $resumo['qtd'] }} passagem(ns) · {{ $resumo['passageiros'] }} passageiro(s)</div>
    </div>
    <div class="card p-3">
      <div class="text-[11px] font-bold text-slate-500 uppercase">💰 Minha comissão</div>
      <div class="text-xl font-extrabold leading-tight text-emerald-600">{{ $br($resumo['comissao']) }}</div>
      <div class="text-[11px] text-slate-400">o que fica comigo</div>
    </div>
    <div class="card p-3 col-span-2 {{ $resumo['devo'] > 0 ? 'border-rose-300 bg-rose-50' : 'border-emerald-300 bg-emerald-50' }}">
      <div class="flex items-end justify-between gap-2">
        <div>
          <div class="text-[11px] font-bold uppercase {{ $resumo['devo'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $resumo['devo'] > 0 ? '🔴 Devo repassar (tudo em aberto)' : '✅ Tudo repassado' }}</div>
          <div class="text-2xl font-extrabold leading-tight {{ $resumo['devo'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $br($resumo['devo']) }}</div>
        </div>
      </div>
      @if($resumo['devo'] > 0)
      <div class="mt-2 pt-2 border-t border-rose-200 space-y-1 text-sm">
        @if($resumo['devoAgente'] > 0)
          <div class="flex justify-between gap-2"><span>🤝 Para o agente</span><b>{{ $br($resumo['devoAgente']) }}</b></div>
        @endif
        @if($resumo['devoEmpresa'] > 0)
          <div class="flex justify-between gap-2"><span>🏢 Para a empresa</span><b>{{ $br($resumo['devoEmpresa']) }}</b></div>
        @endif
        @foreach($resumo['devoDonos'] as $d)
          <div class="flex justify-between gap-2"><span class="min-w-0 truncate">🚤 Dono da {{ $d['lancha'] }}{{ $d['dono'] ? ' ('.$d['dono'].')' : '' }} <span class="text-slate-400 text-xs">· {{ $d['qtd'] }} pass.</span></span><b class="shrink-0">{{ $br($d['valor']) }}</b></div>
        @endforeach
      </div>
      @endif
    </div>
  </div>

  {{-- pagar / detalhes do acerto: fechado por padrão --}}
  @if($painelAgente ?? null)
  <details class="card overflow-hidden no-print">
    <summary class="p-3 font-bold text-sm cursor-pointer select-none">💳 Pagar o que devo (PIX) e ver detalhes do acerto</summary>
    <div class="p-3 pt-0 space-y-4">
      @include('acertos._painel', $painelAgente)
    </div>
  </details>
  @endif

  {{-- vendas --}}
  <div class="card overflow-hidden">
    <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b flex items-center justify-between">
      <span>Vendas · {{ $resumo['periodo'] }} ({{ $resumo['qtd'] }})</span>
    </div>
    @forelse($vendas as $v)
      <a href="{{ route('passagens.show', $v['id']) }}" class="flex items-center justify-between gap-3 px-3 py-2 border-b last:border-0 hover:bg-slate-50">
        <div class="min-w-0">
          <div class="font-semibold text-sm truncate">{{ $v['trecho'] }} <span class="text-slate-400 font-normal">· {{ $v['pax'] }}p</span></div>
          <div class="text-[11px] text-slate-500 truncate">#{{ $v['numero'] }}{{ $v['viagem'] ? ' · viagem '.$v['viagem'] : '' }}{{ $v['lancha'] ? ' · '.$v['lancha'] : '' }}</div>
        </div>
        <div class="text-right shrink-0">
          <div class="font-extrabold text-sm">{{ $br($v['total']) }}</div>
          <div class="text-[11px]"><span class="text-emerald-600 font-semibold">comissão {{ $br($v['comissao']) }}</span></div>
          <div class="text-[11px] font-semibold {{ $v['quitado'] ? 'text-slate-400' : 'text-rose-600' }}">{{ $v['quitado'] ? '✅ repassado' : '🔴 repassar '.$br($v['repasse']).($v['para'] === 'dono' ? ' ao dono' : '') }}</div>
        </div>
      </a>
    @empty
      <div class="p-6 text-center text-sm text-slate-400">Nenhuma venda nesse filtro.</div>
    @endforelse
    @if($resumo['qtd'] > $limite)
      <div class="p-2 text-center text-[11px] text-amber-700 bg-amber-50">Mostrando as {{ $limite }} vendas mais recentes de {{ $resumo['qtd'] }}. Os valores do resumo consideram todas.</div>
    @endif
  </div>
</div>
@include('partials.pulse')
@endsection
