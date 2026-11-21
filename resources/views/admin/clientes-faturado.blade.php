@extends('layouts.app')
@section('titulo','Clientes faturado')
@section('conteudo')
@php
  if (! function_exists('_docTipoView')) { function _docTipoView($doc){ $d = preg_replace('/\D/','',(string)$doc); return strlen($d)===14 ? 'CNPJ' : (strlen($d)>=11 ? 'CPF' : ''); } }
@endphp
<div class="max-w-4xl mx-auto space-y-4" x-data="{ editId:null }">

  <div class="flex items-center justify-between gap-2">
    <h1 class="text-xl font-bold">🧾 Clientes faturado</h1>
    <form method="get" class="flex gap-2">
      <input type="text" name="q" value="{{ $q }}" placeholder="Buscar nome, empresa ou documento" class="input w-56">
      <button class="btn btn-sm">Buscar</button>
    </form>
  </div>

  @if(session('ok'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-3 py-2 text-sm">{{ session('ok') }}</div>@endif
  @if(session('erro'))<div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-3 py-2 text-sm">{{ session('erro') }}</div>@endif

  {{-- NOVO CLIENTE --}}
  <form method="post" action="{{ route('admin.clientes-faturado.store') }}" class="card p-3 space-y-2" x-data="{doc:'', get tipo(){ const d=this.doc.replace(/\D/g,''); return d.length===14?'CNPJ':(d.length>=11?'CPF':''); }}">
    @csrf
    <div class="font-semibold text-sm text-slate-600">➕ Novo cliente faturado</div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
      <div><label class="label">Nome / responsável *</label><input name="nome" required class="input w-full" placeholder="Ex.: João da Silva"></div>
      <div><label class="label">Empresa (razão social)</label><input name="empresa" class="input w-full" placeholder="Ex.: Comercial Rio Negro LTDA"></div>
      <div>
        <label class="label">CPF ou CNPJ</label>
        <div class="flex items-center gap-2">
          <input name="documento" x-model="doc" inputmode="numeric" class="input flex-1" placeholder="Só números">
          <span class="text-[11px] font-bold px-2 py-1 rounded shrink-0" :class="tipo==='CNPJ'?'bg-indigo-100 text-indigo-700':(tipo==='CPF'?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-400')" x-text="tipo || '—'"></span>
        </div>
      </div>
      <div><label class="label">Telefone</label><input name="telefone" class="input w-full" placeholder="(opcional)"></div>
    </div>
    <div class="text-right"><button class="btn btn-accent btn-sm">Cadastrar</button></div>
  </form>

  {{-- LISTA --}}
  <div class="card divide-y">
    <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Cadastrados ({{ $clientes->total() }})</div>
    @forelse($clientes as $c)
      <div class="p-3 {{ $c->ativo?'':'opacity-50' }}">
        <div class="flex items-center gap-3">
          <div class="flex-1 min-w-0">
            <div class="font-semibold truncate">{{ $c->nome }}@if($c->empresa) <span class="text-slate-500">· {{ $c->empresa }}</span>@endif @if(!$c->ativo)<span class="chip st-cinza">inativo</span>@endif</div>
            <div class="text-xs text-slate-500">
              @if($c->documento)<span class="chip {{ ($c->doc_tipo ?: _docTipoView($c->documento))==='CNPJ' ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $c->doc_tipo ?: _docTipoView($c->documento) }}</span> {{ $c->documento }}@else<span class="text-slate-400">sem documento</span>@endif
              @if($c->telefone) · 📞 {{ $c->telefone }}@endif
            </div>
          </div>
          <button type="button" class="btn btn-ghost btn-sm" @click="editId = (editId==={{ $c->id }} ? null : {{ $c->id }})">Editar</button>
          <form method="post" action="{{ route('admin.clientes-faturado.destroy',$c) }}" onsubmit="return confirm('Remover o cliente {{ $c->nome }}?')">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-rose-600">🗑</button></form>
        </div>

        {{-- editar inline --}}
        <form method="post" action="{{ route('admin.clientes-faturado.update',$c) }}" class="mt-2 rounded-xl border border-slate-200 bg-slate-50 p-2 space-y-2" x-show="editId==={{ $c->id }}" x-cloak x-data="{doc:@js($c->documento), get tipo(){ const d=(this.doc||'').replace(/\D/g,''); return d.length===14?'CNPJ':(d.length>=11?'CPF':''); }}">
          @csrf
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <div><label class="label">Nome *</label><input name="nome" required value="{{ $c->nome }}" class="input w-full"></div>
            <div><label class="label">Empresa</label><input name="empresa" value="{{ $c->empresa }}" class="input w-full"></div>
            <div>
              <label class="label">CPF ou CNPJ</label>
              <div class="flex items-center gap-2">
                <input name="documento" x-model="doc" inputmode="numeric" class="input flex-1">
                <span class="text-[11px] font-bold px-2 py-1 rounded shrink-0" :class="tipo==='CNPJ'?'bg-indigo-100 text-indigo-700':(tipo==='CPF'?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-400')" x-text="tipo || '—'"></span>
              </div>
            </div>
            <div><label class="label">Telefone</label><input name="telefone" value="{{ $c->telefone }}" class="input w-full"></div>
          </div>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="ativo" value="1" @checked($c->ativo) class="w-4 h-4 rounded"> Ativo</label>
          <div class="text-right"><button class="btn btn-accent btn-sm">Salvar</button></div>
        </form>
      </div>
    @empty
      <div class="p-6 text-center text-slate-400">Nenhum cliente faturado cadastrado{{ $q ? ' pra essa busca' : '' }}.</div>
    @endforelse
    @if($clientes->hasPages())<div class="p-3">{{ $clientes->links() }}</div>@endif
  </div>

</div>
@endsection
