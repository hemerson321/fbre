@extends('layouts.app')
@section('titulo','CRM · Atendimento')
@section('conteudo')
@php
  $cores = ['novo' => '#2563eb', 'cotacao' => '#7c3aed', 'aguardando_pagamento' => '#d97706', 'pago' => '#059669', 'finalizado' => '#475569', 'perdido' => '#dc2626'];
@endphp
<div x-data="kanbanCrm()" class="space-y-3">
  <div class="flex items-center justify-between gap-2 flex-wrap">
    <div>
      <h1 class="text-xl font-extrabold">📋 CRM · Funil de atendimento</h1>
      <p class="text-sm text-slate-500">Arraste o cliente de uma coluna para outra. Clique no cartão para abrir a conversa.</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <a href="{{ route('atendimento.index') }}" class="btn btn-sm">💬 Conversas</a>
      <a href="{{ route('atendimento.ferramentas') }}" class="btn btn-sm">🧰 Ferramentas</a>
    </div>
  </div>

  @if($etiquetas->isNotEmpty())
  <div class="flex items-center gap-1.5 flex-wrap text-xs">
    <span class="text-slate-400 font-semibold">Etiqueta:</span>
    <a href="{{ route('atendimento.kanban') }}" class="rounded-full px-2.5 py-1 font-bold {{ $etq ? 'bg-slate-100 text-slate-600' : 'bg-slate-800 text-white' }}">Todas</a>
    @foreach($etiquetas as $e)
      <a href="{{ route('atendimento.kanban', ['etiqueta' => $e->id]) }}" class="rounded-full px-2.5 py-1 font-bold" style="background:{{ $etq === $e->id ? $e->cor : 'color-mix(in srgb, '.$e->cor.' 14%, white)' }};color:{{ $etq === $e->id ? '#fff' : $e->cor }}">{{ $e->nome }}</a>
    @endforeach
  </div>
  @endif

  <div class="flex gap-3 overflow-x-auto pb-3" style="min-height:60vh">
    @foreach($colunas as $k => $nome)
    @php $lista = $porFunil[$k] ?? collect(); $cor = $cores[$k] ?? '#475569'; @endphp
    <div class="w-72 shrink-0 rounded-2xl flex flex-col" style="background:color-mix(in srgb, {{ $cor }} 7%, #f8fafc)"
         @dragover.prevent="sobre='{{ $k }}'" @dragleave="sobre=null" @drop.prevent="soltar('{{ $k }}', $event)"
         :style="sobre==='{{ $k }}' ? 'outline:2px dashed {{ $cor }};outline-offset:-2px' : ''">
      <div class="px-3 py-2.5 flex items-center justify-between">
        <div class="font-extrabold text-sm" style="color:{{ $cor }}">{{ $nome }}</div>
        <span class="rounded-full px-2 py-0.5 text-[11px] font-bold text-white" style="background:{{ $cor }}" data-cont="{{ $k }}">{{ $lista->count() }}</span>
      </div>
      <div class="px-2 pb-2 space-y-2 flex-1 overflow-y-auto" data-col="{{ $k }}" style="max-height:70vh">
        @foreach($lista as $c)
        <a href="{{ route('atendimento.show', $c) }}" draggable="true" data-id="{{ $c->id }}"
           @dragstart="arrastar($event, {{ $c->id }})"
           class="block rounded-xl bg-white border border-slate-200 p-2.5 shadow-sm hover:shadow-md transition cursor-grab">
          <div class="flex items-center justify-between gap-2">
            <span class="font-semibold text-sm truncate">{{ $c->nomeExibicao() }}</span>
            @if($c->nao_lidas)
              <span class="min-w-[18px] text-center rounded-full bg-emerald-500 text-white text-[10px] font-bold px-1">{{ $c->nao_lidas }}</span>
            @endif
          </div>
          <div class="text-[11px] text-slate-400">{{ $c->cliente_telefone }} · {{ optional($c->ultima_msg_em)->format('d/m H:i') }}</div>
          <div class="text-xs text-slate-600 truncate mt-0.5">{{ $c->ultima_msg_texto }}</div>
          <div class="flex flex-wrap gap-1 mt-1.5">
            <span class="chip text-[10px] bg-{{ $c->cor() }}-100 text-{{ $c->cor() }}-700">{{ $c->statusLabel() }}</span>
            @foreach((array) $c->etiquetas as $eid)
              @if(isset($etiquetas[$eid]))
                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" style="background:color-mix(in srgb, {{ $etiquetas[$eid]->cor }} 15%, white);color:{{ $etiquetas[$eid]->cor }}">{{ $etiquetas[$eid]->nome }}</span>
              @endif
            @endforeach
            @if($c->atendente)
              <span class="chip text-[10px] bg-slate-100 text-slate-600">👤 {{ $c->atendente->name }}</span>
            @endif
          </div>
        </a>
        @endforeach
      </div>
    </div>
    @endforeach
  </div>
</div>
<script>
function kanbanCrm(){
  return {
    sobre: null, id: null,
    arrastar(ev, id){ this.id = id; try { ev.dataTransfer.setData('text/plain', String(id)); ev.dataTransfer.effectAllowed = 'move'; } catch(e){} },
    async soltar(funil, ev){
      this.sobre = null;
      var id = this.id || parseInt((ev.dataTransfer && ev.dataTransfer.getData('text/plain')) || '0', 10); this.id = null;
      if (!id) return;
      var card = document.querySelector('[data-id="'+id+'"]'), col = document.querySelector('[data-col="'+funil+'"]');
      if (!card || !col || card.parentNode === col) return;
      var de = card.parentNode; col.insertBefore(card, col.firstChild);
      [de, col].forEach(function(c){ var b = document.querySelector('[data-cont="'+c.dataset.col+'"]'); if (b) b.textContent = c.querySelectorAll('[data-id]').length; });
      try {
        var r = await fetch('/passagens/atendimento/'+id+'/funil', { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN': (window.FBRE && FBRE.csrf) || document.querySelector('meta[name=csrf-token]').content }, credentials:'same-origin', body: JSON.stringify({ funil: funil }) });
        if (!r.ok) location.reload();
      } catch(e){ location.reload(); }
    }
  };
}
</script>
@endsection
