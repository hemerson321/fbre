@extends('layouts.app')
@section('titulo','Rotas e calendário')
@section('conteudo')
<div class="max-w-4xl mx-auto space-y-4" x-data="{ editando:null }">
<div class="flex items-center justify-between">
  <div>
    <h1 class="text-xl font-bold">Rotas e calendário</h1>
    <p class="text-sm text-slate-500">Cada rota tem suas paradas com dia da semana e hora. As viagens são criadas sozinhas a partir disso quando o operador cadastra uma encomenda.</p>
  </div>
  <button class="btn btn-accent btn-sm" @click="editando = editando==='nova' ? null : 'nova'">➕ Nova rota</button>
</div>

{{-- FORM NOVA ROTA --}}
<div x-show="editando==='nova'" x-cloak>
  @include('admin._rota_form', ['rota' => null])
</div>

{{-- LISTA --}}
<div class="space-y-3">
@forelse($rotas as $r)
  <div class="card p-4 {{ $r->ativo?'':'opacity-60' }}">
    <div class="flex items-start justify-between gap-2">
      <div>
        <div class="font-bold text-lg">{{ $r->nome }} @if(!$r->ativo)<span class="chip st-cinza">inativa</span>@endif</div>
        <div class="text-sm text-slate-600 mt-1">{{ $r->resumoCalendario() }}</div>
        @if($r->embarcacao)<div class="text-xs text-slate-400 mt-1">Embarcação: {{ $r->embarcacao->nome }}</div>@endif
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn btn-ghost btn-sm" @click="editando = editando==={{ $r->id }} ? null : {{ $r->id }}">Editar</button>
        <form method="post" action="{{ route('admin.rotas.toggle',$r) }}">@csrf<button class="btn btn-sm {{ $r->ativo?'btn-ghost':'btn-primary' }}">{{ $r->ativo?'Desativar':'Ativar' }}</button></form>
      </div>
    </div>
    <div x-show="editando==={{ $r->id }}" x-cloak class="mt-4 pt-4 border-t">
      @include('admin._rota_form', ['rota' => $r])
    </div>
  </div>
@empty
  <div class="card p-8 text-center text-slate-400">Nenhuma rota cadastrada. Crie a primeira (ex.: Manaus seg → Coari ter → Tefé sex).</div>
@endforelse
</div>
</div>
@endsection
