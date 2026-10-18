@extends('layouts.app')
@section('titulo','Usuários')
@section('conteudo')
<div class="max-w-4xl mx-auto space-y-4">
<div class="flex items-center justify-between"><h1 class="text-xl font-bold">Usuários</h1><a href="{{ route('admin.usuarios.create') }}" class="btn btn-accent btn-sm">➕ Novo usuário</a></div>
<div class="card divide-y">
@foreach($usuarios as $u)
  <div class="flex items-center gap-3 p-3 {{ $u->ativo?'':'opacity-50' }}">
    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold" style="background:var(--cor2)">{{ strtoupper(substr($u->name,0,1)) }}</div>
    <div class="flex-1 min-w-0"><div class="font-semibold">{{ $u->name }} @if($u->admin)<span class="chip bg-slate-800 text-white">admin</span>@endif @if(!$u->ativo)<span class="chip st-cinza">inativo</span>@endif</div>
      <div class="text-xs text-slate-500 truncate">login: <b>{{ $u->login }}</b> · {{ $u->setor }}</div>
      <div class="text-xs mt-1 flex gap-1 flex-wrap">
        <span class="chip {{ $u->podeCadastrar()?'st-verde':'st-cinza' }}">cadastra</span>
        <span class="chip {{ $u->podeBaixar()?'st-verde':'st-cinza' }}">dá baixa</span>
        <span class="chip {{ $u->podeVerCaixa()?'st-verde':'st-cinza' }}">vê caixa</span>
      </div></div>
    <a href="{{ route('admin.usuarios.edit',$u) }}" class="btn btn-ghost btn-sm">Editar</a>
  </div>
@endforeach
</div>
</div>
@endsection
