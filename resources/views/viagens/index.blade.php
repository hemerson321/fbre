@extends('layouts.app')
@section('titulo','Viagens')
@section('conteudo')
<div class="space-y-4">
<div class="flex items-center justify-between">
  <h1 class="text-xl font-bold">Viagens</h1>
  <form method="get"><select name="status" class="input !min-h-[38px]" onchange="this.form.submit()">
    <option value="">Todas</option>
    @foreach($status as $k=>$l)<option value="{{ $k }}" @selected($filtroStatus===$k)>{{ $l }}</option>@endforeach
  </select></form>
</div>

<p class="text-sm text-slate-500">As viagens nascem sozinhas quando se cadastra uma encomenda numa rota. Aqui você acompanha, muda o status e vê o caixa de cada viagem.</p>

<div class="hidden lg:block card overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-left text-[11px] uppercase text-slate-500">
      <tr><th class="p-3">Viagem</th><th class="p-3">Rota</th><th class="p-3">Partida</th><th class="p-3">Embarcação</th><th class="p-3 text-center">Encomendas</th><th class="p-3 text-right">Total</th><th class="p-3 text-right">Recebido</th><th class="p-3">Status</th><th class="p-3"></th></tr>
    </thead>
    <tbody class="divide-y">
    @forelse($viagens as $v)
      <tr class="hover:bg-slate-50">
        <td class="p-3 font-bold">{{ $v->numeroFormatado() }}</td>
        <td class="p-3">{{ $v->rota?->nome ?? '—' }}<div class="text-xs text-slate-400">{{ $v->nome }}</div></td>
        <td class="p-3 whitespace-nowrap">{{ $v->data_partida?->format('d/m/Y') ?? '—' }}</td>
        <td class="p-3">{{ $v->embarcacao?->nome ?? '—' }}</td>
        <td class="p-3 text-center">{{ $v->encomendas_count }}</td>
        <td class="p-3 text-right font-semibold">R$ {{ number_format($v->total_valor ?? 0,2,',','.') }}</td>
        <td class="p-3 text-right text-emerald-600">R$ {{ number_format($v->total_recebido ?? 0,2,',','.') }}</td>
        <td class="p-3"><span class="chip {{ ['planejada'=>'st-amarelo','em_andamento'=>'st-verde','concluida'=>'st-cinza','cancelada'=>'st-vermelho'][$v->status] }}">{{ $status[$v->status] }}</span></td>
        <td class="p-3 text-right whitespace-nowrap">
          <a href="{{ route('encomendas.index', ['viagem_id'=>$v->id]) }}" class="btn btn-ghost btn-sm">Encomendas</a>
        </td>
      </tr>
    @empty
      <tr><td colspan="9" class="p-8 text-center text-slate-400">Nenhuma viagem ainda.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>

<div class="lg:hidden space-y-2">
  @forelse($viagens as $v)
    <div class="card p-3">
      <div class="flex justify-between"><div class="font-bold">{{ $v->numeroFormatado() }} <span class="font-normal text-slate-400 text-xs">{{ $v->data_partida?->format('d/m') }}</span></div>
      <span class="chip {{ ['planejada'=>'st-amarelo','em_andamento'=>'st-verde','concluida'=>'st-cinza','cancelada'=>'st-vermelho'][$v->status] }}">{{ $status[$v->status] }}</span></div>
      <div class="text-sm">{{ $v->rota?->nome }} · {{ $v->encomendas_count }} enc. · R$ {{ number_format($v->total_valor ?? 0,2,',','.') }}</div>
      <a href="{{ route('encomendas.index', ['viagem_id'=>$v->id]) }}" class="btn btn-ghost btn-sm mt-2 w-full">Ver encomendas</a>
    </div>
  @empty
    <div class="card p-8 text-center text-slate-400">Nenhuma viagem ainda.</div>
  @endforelse
</div>
{{ $viagens->links() }}
</div>
@endsection
