@extends('layouts.app')
@section('titulo','Meu relatório')
@section('conteudo')
<div class="space-y-4">
<div class="flex items-center justify-between flex-wrap gap-2"><h1 class="text-xl font-bold">Meu relatório <span class="text-slate-400 font-normal text-base">· {{ auth()->user()->name }}</span></h1>
  <div class="flex items-center gap-2 no-print">
    <div class="inline-flex rounded-xl border overflow-hidden text-sm">
      <a href="{{ request()->fullUrlWithQuery(['modo'=>'resumido']) }}" class="px-3 py-1.5 font-semibold {{ $modo==='resumido' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">Resumido</a>
      <a href="{{ request()->fullUrlWithQuery(['modo'=>'analitico']) }}" class="px-3 py-1.5 font-semibold {{ $modo==='analitico' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">Analítico</a>
    </div>
    <a href="{{ route('relatorio.meu.exportar', request()->query()) }}" class="btn btn-ghost btn-sm">⬇️ Exportar CSV</a>
    <button onclick="window.print()" class="btn btn-ghost btn-sm">🖨 Imprimir</button>
  </div></div>

<x-periodo :periodo="$periodo" :inicio="$inicio" :fim="$fim">
  <input type="hidden" name="modo" value="{{ $modo }}">
  <div><label class="label">Forma</label>
    <select name="canal" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todas as formas</option>
      @foreach(\App\Models\Pagamento::CANAIS as $ck=>$cv)<option value="{{ $ck }}" @selected(($canal ?? '')===$ck)>{{ $cv }}</option>@endforeach</select></div>
</x-periodo>

<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-2">
  <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">Cadastrei</div><div class="text-xl font-extrabold">{{ $totais['cadastros'] }}</div><div class="text-xs text-slate-400">R$ {{ number_format($totais['cadastros_valor'],2,',','.') }}</div></div>
  <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">Recebi</div><div class="text-xl font-extrabold text-emerald-600">R$ {{ number_format($porMeio->sum(),2,',','.') }}</div></div>
  @foreach(['dinheiro','pix_manual','pix_auto','debito','credito'] as $m)
    <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">{{ $meios[$m] }}</div><div class="text-lg font-extrabold">R$ {{ number_format($porMeio[$m] ?? 0,2,',','.') }}</div></div>
  @endforeach
</div>

@if($modo==='resumido')
  <div class="card p-4 text-sm text-slate-600">
    Resumo do período — <b>{{ $totais['cadastros'] }}</b> cadastro(s) · recebido <b>R$ {{ number_format($porMeio->sum(),2,',','.') }}</b> · <b>{{ $totais['entregas'] }}</b> entrega(s).
    <div class="text-slate-400 mt-1">Para ver item a item, use o modo <b>Analítico</b>. Para a lista completa do período, use <b>Exportar CSV</b>.</div>
  </div>
@else
@php $muito = max($totais['cadastros'], $totais['recebido_qtd'], $totais['entregas']) > $limite; @endphp
@if($muito)
  <div class="card p-3 text-xs text-amber-700 bg-amber-50 border border-amber-200">Período grande: mostrando os <b>{{ $limite }}</b> mais recentes de cada lista. Para tudo, use <b>⬇️ Exportar CSV</b>.</div>
@endif
<div class="grid lg:grid-cols-2 gap-4">
  <div class="card overflow-hidden">
    <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Encomendas que cadastrei ({{ $totais['cadastros'] }})</div>
    <div class="divide-y text-sm">
      @forelse($cadastros as $e)
        <a href="{{ route('encomendas.show',$e) }}" class="flex justify-between p-3 hover:bg-slate-50 row-{{ $e->cor() }}"><div><b>#{{ $e->numeroFormatado() }}</b> {{ $e->destino }} · {{ $e->destinatario_nome }}<div class="text-xs text-slate-400">{{ $e->created_at->format('d/m H:i') }}</div></div><b>R$ {{ number_format((float)$e->total,2,',','.') }}</b></a>
      @empty <div class="p-6 text-center text-slate-400">Nenhum cadastro.</div> @endforelse
    </div>
  </div>
  <div class="space-y-4">
    <div class="card overflow-hidden">
      <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Recebimentos ({{ $totais['recebido_qtd'] }})</div>
      <div class="divide-y text-sm">
        @forelse($recebimentos as $p)
          <div class="flex justify-between p-3"><div><b>#{{ $p->encomenda?->numeroFormatado() }}</b> <span class="chip bg-slate-100 text-slate-600">{{ $meios[$p->canal] ?? \App\Models\Pagamento::MEIOS[$p->meio] ?? $p->meio }}</span><div class="text-xs text-slate-400">{{ $p->created_at->format('d/m H:i') }}</div></div><b>R$ {{ number_format((float)$p->valor,2,',','.') }}</b></div>
        @empty <div class="p-6 text-center text-slate-400">Nenhum recebimento.</div> @endforelse
      </div>
    </div>
    <div class="card overflow-hidden">
      <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Entregas que dei baixa ({{ $totais['entregas'] }})</div>
      <div class="divide-y text-sm">
        @forelse($entregas as $ev)
          <div class="p-3"><b>#{{ $ev->encomenda?->numeroFormatado() }}</b> {{ $ev->encomenda?->destinatario_nome }} <span class="text-xs text-slate-400">{{ $ev->created_at->format('d/m H:i') }}{{ ($ev->dados['para'] ?? null) ? ' · retirou: '.$ev->dados['para'] : '' }}</span></div>
        @empty <div class="p-6 text-center text-slate-400">Nenhuma entrega.</div> @endforelse
      </div>
    </div>
  </div>
</div>
@endif
</div>
@endsection
