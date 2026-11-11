@extends('layouts.app')
@section('titulo','Acertos')
@section('conteudo')
<div class="max-w-2xl mx-auto space-y-4">
  <div class="flex items-center justify-between flex-wrap gap-2">
    <h1 class="text-xl font-bold">🤝 Acertos <span class="text-slate-400 font-normal text-base">· {{ auth()->user()->name }}</span></h1>
    <a href="{{ route('passagens.minhas-vendas') }}" class="btn btn-ghost btn-sm">📊 Minhas vendas</a>
  </div>
  <p class="text-xs text-slate-500">O que você deve repassar para a empresa e o que deve pagar direto aos donos das lanchas.</p>

  {{-- filtro opcional por viagem --}}
  @if($datasViagem->count() || ($temViagens && $viagens->count()))
  <form method="get" class="card p-3 flex flex-wrap items-end gap-2 text-sm">
    <div class="w-full text-[11px] text-slate-400 font-semibold uppercase">Filtrar por viagem (opcional)</div>
    @if($datasViagem->count())
    <div class="flex-1 min-w-[150px]">
      <label class="label !text-xs">📅 Data da viagem</label>
      <select name="data_viagem" class="input !min-h-[38px]" onchange="this.form.submit()">
        <option value="">Todas as datas</option>
        @foreach($datasViagem as $dv)@php $dvs=\Illuminate\Support\Carbon::parse($dv)->toDateString(); @endphp<option value="{{ $dvs }}" @selected((string)($dataViagem ?? '')===$dvs)>{{ \Illuminate\Support\Carbon::parse($dv)->format('d/m/Y') }}</option>@endforeach
      </select>
    </div>
    @endif
    @if($temViagens && $viagens->count())
    <div class="flex-1 min-w-[150px]">
      <label class="label !text-xs">🧭 Viagem</label>
      <select name="viagem_id" class="input !min-h-[38px]" onchange="this.form.submit()">
        <option value="">Todas as viagens</option>
        @foreach($viagens as $v)<option value="{{ $v->id }}" @selected((int)($viagemId ?? 0)===(int)$v->id)>{{ optional($v->rota)->nome ?? ('Viagem #'.$v->id) }}{{ $v->data_partida ? ' · '.\Illuminate\Support\Carbon::parse($v->data_partida)->format('d/m') : '' }}</option>@endforeach
      </select>
    </div>
    @endif
    @if($dataViagem || $viagemId)<a href="{{ route('meus-acertos') }}" class="btn btn-ghost btn-sm">limpar</a>@endif
  </form>
  @endif

  @include('acertos._painel')

</div>
@endsection
