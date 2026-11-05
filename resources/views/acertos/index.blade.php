@extends('layouts.app')
@section('titulo','Acertos')
@section('conteudo')
@php $usaRev = \App\Support\Tenant::empresa()?->config('usa_revendedores'); @endphp
<div class="max-w-3xl mx-auto space-y-4">
  <div><h1 class="text-xl font-bold">Acertos</h1>
    <p class="text-xs text-slate-500">Confira o caixa dos operadores de balcão e dê baixa no que os agentes externos devem repassar.</p></div>

  {{-- período (afeta o conferir dos operadores) --}}
  <x-periodo :periodo="$periodo" :inicio="$inicio" :fim="$fim"></x-periodo>

  {{-- ===== OPERADORES DE BALCÃO ===== --}}
  <div>
    <div class="flex items-center gap-2 mb-2">
      <span class="text-base">💵</span>
      <h2 class="font-bold">Operadores de balcão</h2>
      <span class="text-xs text-slate-400">— conferir o caixa do período</span>
    </div>
    @forelse($ops as $l)
      <div class="card p-4 flex items-center gap-3 mb-2">
        <div class="flex-1 min-w-0">
          <div class="font-semibold">{{ $l['op']->name }}</div>
          <div class="text-xs text-slate-500 flex flex-wrap gap-x-3 gap-y-0.5 mt-0.5">
            <span>💵 Dinheiro em mãos: <b class="text-slate-700">R$ {{ number_format($l['dinheiro'],2,',','.') }}</b></span>
            @if($l['pix']>0)<span>💠 PIX: R$ {{ number_format($l['pix'],2,',','.') }}</span>@endif
            <span>Total a conferir: <b class="{{ $l['total']>0 ? 'text-amber-600':'text-emerald-600' }}">R$ {{ number_format($l['total'],2,',','.') }}</b> ({{ $l['qtd'] }})</span>
            @if($l['conferido']>0)<span class="text-emerald-600">✓ já conferido R$ {{ number_format($l['conferido'],2,',','.') }}</span>@endif
          </div>
        </div>
        @if($l['qtd']>0)
          <form method="post" action="{{ route('admin.acertos.baixa-operador',$l['op']) }}" onsubmit="return confirm('Conferir o caixa de {{ $l['op']->name }} no período? Marca {{ $l['qtd'] }} lançamentos como conferidos.')">@csrf
            <input type="hidden" name="periodo" value="{{ $periodo }}">
            <input type="hidden" name="inicio" value="{{ request('inicio') }}">
            <input type="hidden" name="hora_inicio" value="{{ request('hora_inicio') }}">
            <input type="hidden" name="fim" value="{{ request('fim') }}">
            <input type="hidden" name="hora_fim" value="{{ request('hora_fim') }}">
            <button class="btn btn-accent btn-sm whitespace-nowrap">✓ Conferir</button>
          </form>
        @else
          <span class="text-emerald-600 text-sm font-semibold whitespace-nowrap">✓ em dia</span>
        @endif
      </div>
    @empty
      <div class="card p-6 text-center text-slate-400 text-sm">Sem movimento de operadores no período.</div>
    @endforelse
  </div>

  {{-- ===== AGENTES EXTERNOS (revendedores) ===== --}}
  @if($usaRev)
  <div>
    <div class="flex items-center gap-2 mb-2">
      <span class="text-base">🤝</span>
      <h2 class="font-bold">Agentes externos</h2>
      <span class="text-xs text-slate-400">— o que cada um deve repassar</span>
    </div>
    @forelse($linhas as $l)
      <div class="card p-4 flex items-center gap-3 mb-2">
        <div class="flex-1 min-w-0">
          <div class="font-semibold">{{ $l['rev']->name }}</div>
          <div class="text-xs text-slate-500">
            <span class="font-bold {{ $l['total']>0 ? 'text-red-600':'text-emerald-600' }}">Deve repassar: R$ {{ number_format($l['total'],2,',','.') }}</span>
            <span class="ml-1">({{ $l['qtd'] }} passagens)</span>
            · já acertado R$ {{ number_format($l['acertado'],2,',','.') }}
          </div>
        </div>
        @if($l['total']>0)
          <form method="post" action="{{ route('admin.acertos.baixa',$l['rev']) }}" onsubmit="return confirm('Dar baixa no acerto de {{ $l['rev']->name }}? Marca {{ $l['qtd'] }} passagens como acertadas.')">@csrf
            <button class="btn btn-accent btn-sm whitespace-nowrap">Dar baixa</button>
          </form>
        @else
          <span class="text-emerald-600 text-sm font-semibold whitespace-nowrap">✓ em dia</span>
        @endif
      </div>
    @empty
      <div class="card p-6 text-center text-slate-400 text-sm">Nenhum agente externo cadastrado. Marque um usuário como revendedor em Usuários.</div>
    @endforelse
  </div>
  @endif
</div>
@endsection
