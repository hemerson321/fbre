@extends('layouts.app')
@section('titulo','Acertos')
@section('conteudo')
@php $usaRev = \App\Support\Tenant::empresa()?->config('usa_revendedores'); @endphp
<div class="max-w-3xl mx-auto space-y-4">
  <div><h1 class="text-xl font-bold">Acertos</h1>
    <p class="text-xs text-slate-500">Confira o caixa dos operadores de balcão e dê baixa no que os agentes externos devem repassar.</p></div>

  {{-- filtros: período + transporte + viagem + operador/agente --}}
  <x-periodo :periodo="$periodo" :inicio="$inicio" :fim="$fim">
    <div><label class="label">Transporte</label>
      <select name="transporte_id" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todos</option>
        @foreach($transportes as $t)<option value="{{ $t->id }}" @selected((string)$transporteId===(string)$t->id)>{{ $t->nome }}</option>@endforeach</select></div>
    @if($viagens->count())
    <div><label class="label">Viagem</label>
      <select name="viagem_id" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todas</option>
        @foreach($viagens as $v)<option value="{{ $v->id }}" @selected((string)$viagemId===(string)$v->id)>{{ $v->numeroFormatado() }} · {{ $v->nome }}</option>@endforeach</select></div>
    @endif
    <div><label class="label">Operador / agente</label>
      <select name="user_id" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todos</option>
        @foreach($pessoas as $p)<option value="{{ $p->id }}" @selected((string)$opFiltro===(string)$p->id)>{{ $p->name }}{{ $p->is_revendedor ? ' (agente)' : '' }}</option>@endforeach</select></div>
  </x-periodo>

  @php
    $hidden = ['periodo'=>$periodo,'inicio'=>request('inicio'),'hora_inicio'=>request('hora_inicio'),'fim'=>request('fim'),'hora_fim'=>request('hora_fim'),'transporte_id'=>$transporteId,'viagem_id'=>$viagemId,'user_id'=>$opFiltro];
  @endphp

  {{-- ===== OPERADORES DE BALCÃO ===== --}}
  <div>
    <div class="flex items-center gap-2 mb-2 flex-wrap">
      <span class="text-base">💵</span>
      <h2 class="font-bold">Operadores de balcão</h2>
      <span class="text-xs text-slate-400 flex-1 min-w-[140px]">— cada “Conferir” já fecha TODAS as vendas do operador no período</span>
      @if($ops->contains(fn ($l) => $l['qtd'] > 0))
        <form method="post" action="{{ route('admin.acertos.operadores.baixa-todos') }}" onsubmit="return confirm('Conferir o caixa de TODOS os operadores no filtro atual?')">@csrf
          @foreach($hidden as $hk=>$hv)<input type="hidden" name="{{ $hk }}" value="{{ $hv }}">@endforeach
          <button class="btn btn-primary btn-sm whitespace-nowrap">✓ Conferir todos</button>
        </form>
      @endif
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
          <form method="post" action="{{ route('admin.acertos.baixa-operador',$l['op']) }}" onsubmit="return confirm('Conferir o caixa de {{ $l['op']->name }}? Marca {{ $l['qtd'] }} lançamentos como conferidos.')">@csrf
            @foreach($hidden as $hk=>$hv)<input type="hidden" name="{{ $hk }}" value="{{ $hv }}">@endforeach
            <button class="btn btn-accent btn-sm whitespace-nowrap">✓ Conferir</button>
          </form>
        @else
          <span class="text-emerald-600 text-sm font-semibold whitespace-nowrap">✓ em dia</span>
        @endif
      </div>
    @empty
      <div class="card p-6 text-center text-slate-400 text-sm">Sem movimento de operadores no filtro.</div>
    @endforelse
  </div>

  {{-- ===== DONOS DE TRANSPORTE ===== --}}
  @if($donos->count())
  <div>
    <div class="flex items-center gap-2 mb-2 flex-wrap">
      <span class="text-base">⛴️</span>
      <h2 class="font-bold">Donos de transporte</h2>
      <span class="text-xs text-slate-400 flex-1 min-w-[140px]">— quanto a empresa deve pagar ao dono de cada lancha</span>
    </div>
    @foreach($donos as $l)
      <div class="card p-4 flex items-center gap-3 mb-2">
        <div class="flex-1 min-w-0">
          <div class="font-semibold">{{ $l['emb']->nome }} <span class="text-xs text-slate-400">· dono: {{ $l['emb']->dono_nome }}</span></div>
          <div class="text-xs text-slate-500">
            <span class="font-bold {{ $l['dono']>0 ? 'text-amber-600':'text-emerald-600' }}">Pagar ao dono: R$ {{ number_format($l['dono'],2,',','.') }}</span>
            <span class="ml-1">({{ $l['qtd'] }} passagens · empresa fica {{ $l['pct'] }}%)</span>
            · já pago R$ {{ number_format($l['pago'],2,',','.') }}
          </div>
        </div>
        <div class="flex flex-col gap-1 items-end">
          <a href="{{ route('admin.acertos.dono-relatorio',$l['emb']) }}{{ $viagemId ? '?viagem_id='.$viagemId : '' }}" class="btn btn-ghost btn-sm whitespace-nowrap">📲 Relatório</a>
          @if($l['dono']>0)
          <form method="post" action="{{ route('admin.acertos.baixa-dono',$l['emb']) }}" onsubmit="return confirm('Marcar como pago ao dono de {{ $l['emb']->nome }}?')">@csrf
            @foreach($hidden as $hk=>$hv)<input type="hidden" name="{{ $hk }}" value="{{ $hv }}">@endforeach
            <button class="btn btn-accent btn-sm whitespace-nowrap w-full">Pagar dono</button>
          </form>
          @else
          <span class="text-emerald-600 text-sm font-semibold whitespace-nowrap">✓ em dia</span>
          @endif
        </div>
      </div>
    @endforeach
  </div>
  @endif

  {{-- ===== AGENTES EXTERNOS (revendedores) ===== --}}
  @if($usaRev)
  <div>
    <div class="flex items-center gap-2 mb-2 flex-wrap">
      <span class="text-base">🤝</span>
      <h2 class="font-bold">Agentes externos</h2>
      <span class="text-xs text-slate-400 flex-1 min-w-[140px]">— o que cada um deve repassar</span>
      @if($linhas->contains(fn ($l) => $l['total'] > 0))
        <form method="post" action="{{ route('admin.acertos.agentes.baixa-todos') }}" onsubmit="return confirm('Dar baixa no acerto de TODOS os agentes no filtro atual?')">@csrf
          @foreach($hidden as $hk=>$hv)<input type="hidden" name="{{ $hk }}" value="{{ $hv }}">@endforeach
          <button class="btn btn-primary btn-sm whitespace-nowrap">Dar baixa em todos</button>
        </form>
      @endif
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
            @foreach($hidden as $hk=>$hv)<input type="hidden" name="{{ $hk }}" value="{{ $hv }}">@endforeach
            <button class="btn btn-accent btn-sm whitespace-nowrap">Dar baixa</button>
          </form>
        @else
          <span class="text-emerald-600 text-sm font-semibold whitespace-nowrap">✓ em dia</span>
        @endif
      </div>
    @empty
      <div class="card p-6 text-center text-slate-400 text-sm">Nenhum agente externo no filtro.</div>
    @endforelse
  </div>
  @endif
</div>
@endsection
