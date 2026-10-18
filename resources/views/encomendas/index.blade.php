@extends('layouts.app')
@section('titulo','Encomendas')
@section('conteudo')
<div x-data="{ filtros:{{ request()->boolean('protocolo') ? 'true' : 'false' }} }" class="space-y-4">

<div class="flex items-center justify-between gap-2">
  <h1 class="text-xl font-bold">Encomendas</h1>
  <div class="flex gap-2">
    <button class="btn btn-ghost btn-sm lg:hidden" @click="filtros=!filtros">🔍 Filtros</button>
    @if(auth()->user()->podeCadastrar())
      <a href="{{ route('encomendas.create') }}" class="btn btn-accent btn-sm">➕ Nova</a>
    @endif
  </div>
</div>

{{-- FILTROS --}}
<form method="get" class="card p-3 lg:p-4" :class="filtros?'':'hidden lg:block'" x-data="{ mais:{{ collect($f)->only(['hora_inicio','hora_fim','viagem_id','rota_id','origem','destino','embarcacao_id','status_pagamento','entregue','forma'])->filter(fn($v)=>$v!==null && $v!=='')->isNotEmpty() ? 'true':'false' }} }">
  <datalist id="locais">@foreach($localidades as $l)<option value="{{ $l }}">@endforeach</datalist>

  {{-- linha principal: busca + período --}}
  <div class="flex flex-col lg:flex-row gap-2 lg:items-end">
    <div class="flex-1">
      <label class="label">Buscar</label>
      <div class="relative">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔎</span>
        <input name="busca" value="{{ $f['busca'] ?? '' }}" class="input !pl-9" placeholder="Nº, nome ou telefone">
      </div>
    </div>
    <div class="flex gap-2">
      <div><label class="label">De</label><input type="date" name="inicio" value="{{ $f['inicio'] ?? '' }}" class="input !min-h-[44px]"></div>
      <div><label class="label">Até</label><input type="date" name="fim" value="{{ $f['fim'] ?? '' }}" class="input !min-h-[44px]"></div>
    </div>
    <div class="flex gap-2">
      <button class="btn btn-primary !min-h-[44px]">Filtrar</button>
      <button type="button" class="btn btn-ghost !min-h-[44px]" @click="mais=!mais" x-text="mais ? 'Menos filtros' : 'Mais filtros'"></button>
    </div>
  </div>

  {{-- filtros avançados --}}
  <div x-show="mais" x-cloak class="mt-3 pt-3 border-t border-slate-100 grid gap-2 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
    <div><label class="label">Hora de</label><input type="time" name="hora_inicio" value="{{ $f['hora_inicio'] ?? '' }}" class="input"></div>
    <div><label class="label">Hora até</label><input type="time" name="hora_fim" value="{{ $f['hora_fim'] ?? '' }}" class="input"></div>
    @if($viagens->count())
    <div><label class="label">Viagem</label>
      <select name="viagem_id" class="input"><option value="">Todas</option>
        @foreach($viagens as $v)<option value="{{ $v->id }}" @selected(($f['viagem_id'] ?? '')==$v->id)>{{ $v->numeroFormatado() }} · {{ $v->nome }}</option>@endforeach</select></div>
    @endif
    @if($rotas->count())
    <div><label class="label">Rota</label>
      <select name="rota_id" class="input"><option value="">Todas</option>
        @foreach($rotas as $r)<option value="{{ $r->id }}" @selected(($f['rota_id'] ?? '')==$r->id)>{{ $r->nome }}</option>@endforeach</select></div>
    @endif
    <div><label class="label">Origem</label><input name="origem" list="locais" value="{{ $f['origem'] ?? '' }}" class="input"></div>
    <div><label class="label">Destino</label><input name="destino" list="locais" value="{{ $f['destino'] ?? '' }}" class="input"></div>
    <div><label class="label">Transporte</label>
      <select name="embarcacao_id" class="input"><option value="">Todos</option>
        @foreach($embarcacoes as $b)<option value="{{ $b->id }}" @selected(($f['embarcacao_id'] ?? '')==$b->id)>{{ $b->nome }}</option>@endforeach</select></div>
    <div><label class="label">Pagamento</label>
      <select name="status_pagamento" class="input"><option value="">Todos</option>
        @foreach(['pago'=>'Pago','parcial'=>'Parcial','pendente'=>'Não pago'] as $k=>$l)<option value="{{ $k }}" @selected(($f['status_pagamento'] ?? '')===$k)>{{ $l }}</option>@endforeach</select></div>
    <div><label class="label">Entregue</label>
      <select name="entregue" class="input"><option value="">Todos</option>
        <option value="1" @selected(($f['entregue'] ?? '')==='1')>Sim</option><option value="0" @selected(($f['entregue'] ?? '')==='0')>Não</option></select></div>
    <div><label class="label">Forma</label>
      <select name="forma" class="input"><option value="">Todas</option>
        @foreach(\App\Models\Encomenda::FORMAS as $k=>$l)<option value="{{ $k }}" @selected(($f['forma'] ?? '')===$k)>{{ $l }}</option>@endforeach</select></div>
  </div>

  {{-- ações --}}
  <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-slate-100">
    <a href="{{ route('encomendas.index') }}" class="btn btn-ghost btn-sm">Limpar filtros</a>
    <span class="flex-1"></span>
    <button type="submit" formtarget="_blank" formaction="{{ route('imprimir.protocolo') }}" class="btn btn-sm {{ request()->boolean('protocolo') ? 'btn-accent' : 'btn-ghost' }}">🖨 Protocolo</button>
    <button type="submit" formtarget="_blank" formaction="{{ route('imprimir.listagem') }}" class="btn btn-ghost btn-sm">🖨 Listagem</button>
  </div>
</form>

{{-- TOTAIS --}}
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-2">
  @php $card = fn($l,$v,$c='') => '<div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">'.$l.'</div><div class="text-lg font-extrabold '.$c.'">'.$v.'</div></div>'; @endphp
  {!! $card('Encomendas', $totais['qtd'].' <span class="text-xs font-normal text-slate-400">· '.$totais['volumes'].' vol.</span>') !!}
  {!! $card('Total', 'R$ '.number_format($totais['total'],2,',','.')) !!}
  {!! $card('Recebido', 'R$ '.number_format($totais['recebido'],2,',','.'), 'text-emerald-600') !!}
  {!! $card('A receber', 'R$ '.number_format($totais['a_receber'],2,',','.'), 'text-red-600') !!}
  {!! $card('Descontos', 'R$ '.number_format($totais['desconto'],2,',','.'), 'text-amber-600') !!}
  <div class="card p-3 text-xs space-y-0.5">
    @foreach(\App\Models\Pagamento::MEIOS as $k=>$l) @if($k!=='desconto')
      <div class="flex justify-between"><span class="text-slate-500">{{ $l }}</span><b>R$ {{ number_format($porMeio[$k] ?? 0,2,',','.') }}</b></div>
    @endif @endforeach
  </div>
</div>

{{-- LEGENDA --}}
<div class="flex flex-wrap gap-3 text-xs text-slate-500">
  <span><i class="inline-block w-3 h-3 rounded-sm bg-emerald-500 align-middle"></i> Pago e entregue</span>
  <span><i class="inline-block w-3 h-3 rounded-sm bg-amber-500 align-middle"></i> Pago, não entregue</span>
  <span><i class="inline-block w-3 h-3 rounded-sm bg-red-500 align-middle"></i> Não pago</span>
</div>

{{-- LISTA: tabela no desktop, cards no mobile --}}
<div class="hidden lg:block card overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-left text-[11px] uppercase text-slate-500">
      <tr><th class="p-3">Nº</th><th class="p-3">Data</th><th class="p-3">Rota</th><th class="p-3">Remetente</th><th class="p-3">Destinatário</th><th class="p-3 text-center">Vol.</th><th class="p-3 text-right">Total</th><th class="p-3 text-right">Devido</th><th class="p-3">Status</th><th class="p-3"></th></tr>
    </thead>
    <tbody class="divide-y">
    @forelse($encomendas as $e)
      <tr class="row-{{ $e->cor() }} hover:bg-slate-50">
        <td class="p-3 font-bold">{{ $e->numeroFormatado() }}</td>
        <td class="p-3 text-slate-500 whitespace-nowrap">{{ $e->created_at->format('d/m H:i') }}</td>
        <td class="p-3 whitespace-nowrap">{{ $e->origem }} → {{ $e->destino }}<div class="text-xs text-slate-400">{{ $e->embarcacao?->nome }}</div></td>
        <td class="p-3">{{ $e->remetente_nome }}<div class="text-xs text-slate-400">{{ $e->remetente_telefone }}</div></td>
        <td class="p-3">{{ $e->destinatario_nome }}<div class="text-xs text-slate-400">{{ $e->destinatario_telefone }}</div></td>
        <td class="p-3 text-center">{{ $e->volumes }}</td>
        <td class="p-3 text-right font-semibold">{{ number_format($e->total,2,',','.') }}</td>
        <td class="p-3 text-right {{ $e->devido>0?'text-red-600 font-bold':'text-slate-400' }}">{{ number_format($e->devido,2,',','.') }}</td>
        <td class="p-3"><span class="chip st-{{ $e->cor() }}">{{ $e->statusTexto() }}</span></td>
        <td class="p-3 text-right whitespace-nowrap">
          <a href="{{ route('encomendas.show',$e) }}" class="btn btn-ghost btn-sm">Abrir</a>
        </td>
      </tr>
    @empty
      <tr><td colspan="10" class="p-8 text-center text-slate-400">Nenhuma encomenda no período.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>

<div class="lg:hidden space-y-2">
  @forelse($encomendas as $e)
    <a href="{{ route('encomendas.show',$e) }}" class="card block p-3 row-{{ $e->cor() }}">
      <div class="flex justify-between items-start gap-2">
        <div class="min-w-0">
          <div class="font-extrabold">#{{ $e->numeroFormatado() }} <span class="text-slate-400 font-normal text-xs">{{ $e->created_at->format('d/m H:i') }}</span></div>
          <div class="text-sm truncate">{{ $e->origem }} → {{ $e->destino }} <span class="text-slate-400">· {{ $e->volumes }} vol.</span></div>
        </div>
        <div class="text-right shrink-0">
          <div class="font-extrabold">R$ {{ number_format($e->total,2,',','.') }}</div>
          @if($e->devido>0)<div class="text-xs text-red-600 font-bold">devido {{ number_format($e->devido,2,',','.') }}</div>@endif
        </div>
      </div>
      <div class="mt-1 text-sm text-slate-600 truncate"><b>De:</b> {{ $e->remetente_nome }} · <b>Para:</b> {{ $e->destinatario_nome }}</div>
      <div class="mt-1"><span class="chip st-{{ $e->cor() }}">{{ $e->statusTexto() }}</span></div>
    </a>
  @empty
    <div class="card p-8 text-center text-slate-400">Nenhuma encomenda no período.</div>
  @endforelse
</div>

{{ $encomendas->links() }}
</div>
@endsection
