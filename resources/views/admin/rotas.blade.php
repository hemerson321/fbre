@extends('layouts.app')
@section('titulo','Rotas e calendário')
@section('conteudo')
<div class="max-w-4xl mx-auto space-y-4" x-data="{ editando: {{ request('editar') ? (int) request('editar') : 'null' }} }">
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
<div class="space-y-3" id="rotas-lista">
@forelse($rotas as $r)
  <div class="card p-4 {{ $r->ativo?'':'opacity-60' }}" data-id="{{ $r->id }}">
    <div class="drag-rota cursor-grab select-none text-slate-300 leading-none mb-1" title="Segure e arraste pra mudar a ordem">⠿ <span class="text-[11px] align-middle text-slate-400">arraste pra reordenar</span></div>
    <div class="flex items-start justify-between gap-2">
      <div>
        @if($r->embarcacao)
          <div class="flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center gap-1 bg-slate-800 text-white rounded-lg px-2.5 py-1 font-bold text-base">🚤 {{ $r->embarcacao->nome }}</span>
            @if(!$r->ativo)<span class="chip st-cinza">inativa</span>@endif
          </div>
          <div class="font-semibold text-slate-700 mt-1">{{ $r->nome }}</div>
        @else
          <div class="font-bold text-lg">{{ $r->nome }} @if(!$r->ativo)<span class="chip st-cinza">inativa</span>@endif</div>
        @endif
        <div class="text-sm text-slate-600 mt-1">{{ $r->resumoCalendario() }}</div>
      </div>
      <div class="flex gap-2 shrink-0">
        <button class="btn btn-ghost btn-sm" @click="editando = editando==={{ $r->id }} ? null : {{ $r->id }}">Editar</button>
        <form method="post" action="{{ route('admin.rotas.duplicar',$r) }}">@csrf<button class="btn btn-ghost btn-sm" title="Criar uma copia desta rota">⧉ Copiar</button></form>
        <form method="post" action="{{ route('admin.rotas.toggle',$r) }}">@csrf<button class="btn btn-sm {{ $r->ativo?'btn-ghost':'btn-primary' }}">{{ $r->ativo?'Desativar':'Ativar' }}</button></form>
        <form method="post" action="{{ route('admin.rotas.excluir',$r) }}" onsubmit="return confirm('Excluir a rota {{ $r->nome }}? Se tiver passagens, sera apenas desativada.')">@csrf @method('DELETE')<button class="btn btn-sm" style="color:#dc2626">Excluir</button></form>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.3/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  var el = document.getElementById('rotas-lista');
  if (el && window.Sortable) {
    window.Sortable.create(el, {
      handle: '.drag-rota', animation: 150, ghostClass: 'opacity-40',
      onEnd: function(){
        var ids = Array.prototype.slice.call(el.querySelectorAll('[data-id]')).map(function(c){ return c.getAttribute('data-id'); });
        fetch('{{ route('admin.rotas.reordenar') }}', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
          body: JSON.stringify({ ordem: ids })
        }).catch(function(){});
      }
    });
  }
});
</script>
@endsection
