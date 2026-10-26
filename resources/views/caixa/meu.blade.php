@extends('layouts.app')
@section('titulo','Meu relatório')
@section('conteudo')
<div class="space-y-4">
<div class="flex items-center justify-between"><h1 class="text-xl font-bold">Meu relatório <span class="text-slate-400 font-normal text-base">· {{ auth()->user()->name }}</span></h1>
  <button onclick="window.print()" class="btn btn-ghost btn-sm no-print">🖨 Imprimir</button></div>

<x-periodo :periodo="$periodo" :inicio="$inicio" :fim="$fim" />

<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-2">
  <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">Cadastrei</div><div class="text-xl font-extrabold">{{ $cadastros->count() }}</div><div class="text-xs text-slate-400">R$ {{ number_format($cadastros->sum('total'),2,',','.') }}</div></div>
  <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">Recebi</div><div class="text-xl font-extrabold text-emerald-600">R$ {{ number_format($porMeio->sum(),2,',','.') }}</div></div>
  @foreach(['dinheiro','pix_manual','pix_auto','debito','credito'] as $m)
    <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">{{ $meios[$m] }}</div><div class="text-lg font-extrabold">R$ {{ number_format($porMeio[$m] ?? 0,2,',','.') }}</div></div>
  @endforeach
</div>

<div class="grid lg:grid-cols-2 gap-4">
  <div class="card overflow-hidden">
    <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Encomendas que cadastrei ({{ $cadastros->count() }})</div>
    <div class="divide-y text-sm">
      @forelse($cadastros as $e)
        <a href="{{ route('encomendas.show',$e) }}" class="flex justify-between p-3 hover:bg-slate-50 row-{{ $e->cor() }}"><div><b>#{{ $e->numeroFormatado() }}</b> {{ $e->destino }} · {{ $e->destinatario_nome }}<div class="text-xs text-slate-400">{{ $e->created_at->format('d/m H:i') }}</div></div><b>R$ {{ number_format($e->total,2,',','.') }}</b></a>
      @empty <div class="p-6 text-center text-slate-400">Nenhum cadastro.</div> @endforelse
    </div>
  </div>
  <div class="space-y-4">
    <div class="card overflow-hidden">
      <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Recebimentos ({{ $recebimentos->count() }})</div>
      <div class="divide-y text-sm">
        @forelse($recebimentos as $p)
          <div class="flex justify-between p-3"><div><b>#{{ $p->encomenda?->numeroFormatado() }}</b> <span class="chip bg-slate-100 text-slate-600">{{ $meios[$p->canal] ?? \App\Models\Pagamento::MEIOS[$p->meio] ?? $p->meio }}</span><div class="text-xs text-slate-400">{{ $p->created_at->format('d/m H:i') }}</div></div><b>R$ {{ number_format($p->valor,2,',','.') }}</b></div>
        @empty <div class="p-6 text-center text-slate-400">Nenhum recebimento.</div> @endforelse
      </div>
    </div>
    <div class="card overflow-hidden">
      <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Entregas que dei baixa ({{ $entregas->count() }})</div>
      <div class="divide-y text-sm">
        @forelse($entregas as $ev)
          <div class="p-3"><b>#{{ $ev->encomenda?->numeroFormatado() }}</b> {{ $ev->encomenda?->destinatario_nome }} <span class="text-xs text-slate-400">{{ $ev->created_at->format('d/m H:i') }}{{ ($ev->dados['para'] ?? null) ? ' · retirou: '.$ev->dados['para'] : '' }}</span></div>
        @empty <div class="p-6 text-center text-slate-400">Nenhuma entrega.</div> @endforelse
      </div>
    </div>
  </div>
</div>
</div>
@endsection
