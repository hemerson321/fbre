@extends('layouts.app')
@section('titulo','Suítes / Camarotes')
@section('conteudo')
<div class="max-w-3xl mx-auto space-y-5">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold">🛏️ Suítes / Camarotes</h1>
      <p class="text-sm text-slate-500">Cadastre os tipos de suíte/camarote (capacidade e o que tem) e o preço por trecho — igual à passagem. Pessoas além da capacidade entram como adicional na venda.</p>
    </div>
    <a href="{{ route('passagens.camarote.create') }}" class="btn btn-sm btn-primary">🎫 Vender</a>
  </div>

  @foreach($camarotes as $c)
  <div class="card p-4 space-y-3" x-data="{ab:false, precos:{{ \Illuminate\Support\Js::from($c->precos->map(fn($p)=>['origem'=>$p->origem,'destino'=>$p->destino,'valor'=>number_format((float)$p->valor,2,'.','')])->values()) }} }">
    <div class="flex items-center justify-between">
      <div class="cursor-pointer" @click="ab=!ab">
        <span class="font-bold">🛏️ {{ $c->nome }}</span>
        <span class="text-xs text-slate-400">· até {{ $c->capacidade }} pessoa(s) · {{ $c->precos->count() }} trecho(s) @if(!$c->ativo)· inativo @endif</span>
      </div>
      <span class="text-slate-400 cursor-pointer" @click="ab=!ab" x-text="ab?'▲':'▼'"></span>
    </div>

    <div x-show="ab" x-cloak class="pt-2 border-t space-y-4">
      {{-- dados do tipo --}}
      <form method="POST" action="{{ route('admin.camarotes.salvar', $c) }}" class="space-y-3">
        @csrf
        <div class="grid grid-cols-2 gap-2">
          <label class="block"><span class="text-xs text-slate-500">Nome</span>
            <input name="nome" value="{{ $c->nome }}" class="input" required></label>
          <label class="block"><span class="text-xs text-slate-500">Capacidade (pessoas)</span>
            <input name="capacidade" type="number" min="1" max="50" value="{{ $c->capacidade }}" class="input" required></label>
        </div>
        <label class="block"><span class="text-xs text-slate-500">Descrição (o que tem: TV, ar-condicionado, cama de casal…)</span>
          <textarea name="descricao" rows="2" class="input py-2">{{ $c->descricao }}</textarea></label>
        <div class="grid grid-cols-2 gap-2 items-end">
          <label class="block"><span class="text-xs text-slate-500">Adicional por pessoa extra (R$)</span>
            <input name="valor_adicional" type="number" step="0.01" min="0" value="{{ number_format((float)$c->valor_adicional,2,'.','') }}" class="input"></label>
          <label class="flex items-center gap-2 pb-3"><input type="checkbox" name="ativo" value="1" @checked($c->ativo) class="w-5 h-5"> <span class="text-sm">Ativo</span></label>
        </div>
        <div class="flex gap-2">
          <button class="btn btn-sm btn-primary">Salvar dados</button>
          <button form="del-cam-{{ $c->id }}" class="btn btn-sm btn-danger ml-auto" onclick="return confirm('Remover este camarote e seus preços?')">Remover</button>
        </div>
      </form>
      <form id="del-cam-{{ $c->id }}" method="POST" action="{{ route('admin.camarotes.excluir', $c) }}" class="hidden">@csrf @method('DELETE')</form>

      {{-- preços por trecho --}}
      <form method="POST" action="{{ route('admin.camarotes.precos', $c) }}" class="space-y-2">
        @csrf
        <div class="text-xs font-semibold text-slate-600 uppercase">Preço por trecho</div>
        <template x-for="(p,i) in precos" :key="i">
          <div class="flex gap-2 items-center">
            <select :name="`precos[${i}][origem]`" x-model="p.origem" class="input flex-1 py-2 text-sm">
              <option value="">Origem…</option>
              @foreach($localidades as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach
            </select>
            <span class="text-slate-400">→</span>
            <select :name="`precos[${i}][destino]`" x-model="p.destino" class="input flex-1 py-2 text-sm">
              <option value="">Destino…</option>
              @foreach($localidades as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach
            </select>
            <input :name="`precos[${i}][valor]`" x-model="p.valor" type="number" step="0.01" min="0" placeholder="R$" class="input w-28 py-2 text-sm">
            <button type="button" @click="precos.splice(i,1)" class="text-red-500 text-lg px-1">✕</button>
          </div>
        </template>
        <div class="flex gap-2">
          <button type="button" @click="precos.push({origem:'',destino:'',valor:''})" class="btn btn-sm btn-ghost">+ Trecho</button>
          <button class="btn btn-sm btn-accent ml-auto">Salvar preços</button>
        </div>
      </form>
    </div>
  </div>
  @endforeach

  {{-- novo camarote --}}
  <div class="card p-4 space-y-3">
    <div class="font-bold text-sm text-slate-600 uppercase">➕ Novo tipo de suíte/camarote</div>
    <form method="POST" action="{{ route('admin.camarotes.salvar') }}" class="space-y-3">
      @csrf
      <div class="grid grid-cols-2 gap-2">
        <label class="block"><span class="text-xs text-slate-500">Nome</span>
          <input name="nome" placeholder="Ex.: Suíte Master" class="input" required></label>
        <label class="block"><span class="text-xs text-slate-500">Capacidade (pessoas)</span>
          <input name="capacidade" type="number" min="1" max="50" value="2" class="input" required></label>
      </div>
      <label class="block"><span class="text-xs text-slate-500">Descrição (TV, ar-condicionado, cama de casal…)</span>
        <textarea name="descricao" rows="2" class="input py-2"></textarea></label>
      <label class="block"><span class="text-xs text-slate-500">Adicional por pessoa extra (R$)</span>
        <input name="valor_adicional" type="number" step="0.01" min="0" value="0" class="input"></label>
      <input type="hidden" name="ativo" value="1">
      <button class="btn btn-primary">Criar</button>
      <p class="text-xs text-slate-400">Depois de criar, abra o card pra definir o preço por trecho.</p>
    </form>
  </div>
</div>
@endsection
