@extends('layouts.app')
@section('titulo','Minhas vendas')
@section('conteudo')
<div class="space-y-4">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-bold">Minhas vendas <span class="text-slate-400 font-normal text-base">· {{ auth()->user()->name }}</span></h1>
    <div class="flex items-center gap-2 no-print">
      <a href="{{ route('passagens.minhas-vendas.exportar', request()->query()) }}" class="btn btn-ghost btn-sm">⬇️ Exportar CSV</a>
      <a href="{{ route('passagens.caixa-viagem', array_merge(request()->only(['inicio','fim']), ['user_id'=>auth()->id()])) }}" target="_blank" class="btn btn-ghost btn-sm">📄 Prestação (PDF)</a>
      <button onclick="window.print()" class="btn btn-ghost btn-sm">🖨 Imprimir</button>
    </div>
  </div>

  <x-periodo :periodo="$periodo" :inicio="$inicio" :fim="$fim">
    <div><label class="label">Forma</label>
      <select name="canal" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todas as formas</option>
        @foreach(\App\Models\Pagamento::CANAIS as $ck=>$cv)<option value="{{ $ck }}" @selected(($canal ?? '')===$ck)>{{ $cv }}</option>@endforeach</select></div>
  </x-periodo>

  <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
    <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">Passagens</div><div class="text-xl font-extrabold">{{ $totais['qtd'] }}</div><div class="text-xs text-slate-400">{{ $totais['passageiros'] }} passageiro(s)</div></div>
    <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">Total vendido</div><div class="text-xl font-extrabold">R$ {{ number_format($totais['total'],2,',','.') }}</div></div>
    <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">Recebido</div><div class="text-xl font-extrabold text-emerald-600">R$ {{ number_format($totais['recebido'],2,',','.') }}</div></div>
    <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">A receber</div><div class="text-xl font-extrabold text-amber-600">R$ {{ number_format($totais['devido'],2,',','.') }}</div></div>
  </div>

  @if($painelAgente ?? null)
  <div class="space-y-4">
    @include('acertos._painel', $painelAgente)
  </div>
  @endif

  @if($totais['qtd'] > $limite)
    <div class="card p-3 text-xs text-amber-700 bg-amber-50 border border-amber-200">Período grande: mostrando as <b>{{ $limite }}</b> vendas mais recentes de <b>{{ $totais['qtd'] }}</b>. Para a lista completa, use <b>⬇️ Exportar CSV</b>.</div>
  @endif
  <div class="card overflow-hidden">
    <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Vendas no período ({{ $totais['qtd'] }})</div>
    @include('passagens._manifesto_tabela', ['linhas' => $linhas])
  </div>
  <div class="text-[11px] text-slate-500 flex flex-wrap gap-2 px-1">Legenda:
    <span style="background:#dbeafe;padding:1px 7px;border-radius:4px">Agente/Município</span>
    <span style="background:#dcfce7;padding:1px 7px;border-radius:4px">Idoso</span>
    <span style="background:#fef9c3;padding:1px 7px;border-radius:4px">PCD</span>
    <span style="background:#fee2e2;color:#b91c1c;padding:1px 7px;border-radius:4px">Cancelado</span>
  </div>
</div>
@include('partials.pulse')
@endsection
