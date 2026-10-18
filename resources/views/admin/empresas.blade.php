@extends('layouts.app')
@section('titulo','Empresas')
@section('conteudo')
<div class="max-w-5xl mx-auto space-y-4" x-data="{ nova:false }">
<div class="flex items-center justify-between"><h1 class="text-xl font-bold">Empresas (clientes)</h1><button class="btn btn-accent btn-sm" @click="nova=!nova">➕ Nova empresa</button></div>

<form method="post" action="{{ route('super.empresas.store') }}" class="card p-4 grid sm:grid-cols-2 gap-3" x-show="nova" x-cloak>@csrf
  <div class="sm:col-span-2 font-bold">Nova empresa</div>
  <div><label class="label">Razão social</label><input name="nome" class="input" required></div>
  <div><label class="label">Nome fantasia</label><input name="nome_fantasia" class="input"></div>
  <div><label class="label">CNPJ</label><input name="cnpj" class="input"></div>
  <div><label class="label">Validade do contrato</label><input name="validade" type="date" class="input" value="{{ now()->addYear()->toDateString() }}"></div>
  <div class="sm:col-span-2 label pt-2">Usuário administrador do cliente</div>
  <div><label class="label">Nome</label><input name="admin_nome" class="input" required></div>
  <div><label class="label">Login</label><input name="admin_login" class="input" required placeholder="ex.: admin.empresa" autocapitalize="none"></div>
  <div><label class="label">Senha</label><input name="admin_senha" class="input" required minlength="6"></div>
  <div class="flex items-end"><button class="btn btn-accent w-full">Criar empresa</button></div>
</form>

<div class="card divide-y">
@foreach($empresas as $e)
  <div class="flex items-center gap-3 p-3 {{ $e->ativo?'':'opacity-50' }}">
    @if($e->logo)<img src="/{{ $e->logo }}" class="w-10 h-10 rounded-lg object-cover border">@else<div class="w-10 h-10 rounded-lg bg-slate-200"></div>@endif
    <div class="flex-1 min-w-0">
      <div class="font-semibold">{{ $e->nomeExibicao() }} @if($ativa==$e->id)<span class="chip st-verde">operando</span>@endif @if($e->vencida())<span class="chip st-vermelho">vencida</span>@endif @if(!$e->ativo)<span class="chip st-cinza">inativa</span>@endif</div>
      <div class="text-xs text-slate-500">{{ $e->cnpj }} · {{ $e->users_count }} usuários · {{ $e->encomendas_count }} encomendas · validade {{ $e->validade?->format('d/m/Y') ?? '—' }}</div>
      <div class="text-xs mt-0.5">Login: <a href="{{ url('/'.$e->slug) }}" target="_blank" class="font-mono text-[var(--cor2)] hover:underline">{{ url('/'.$e->slug) }}</a></div>
    </div>
    <form method="post" action="{{ route('super.empresas.entrar',$e) }}">@csrf<button class="btn btn-ghost btn-sm">Entrar</button></form>
    <form method="post" action="{{ route('super.empresas.toggle',$e) }}">@csrf<button class="btn btn-sm {{ $e->ativo?'btn-danger':'btn-primary' }}">{{ $e->ativo?'Bloquear':'Ativar' }}</button></form>
  </div>
@endforeach
</div>
</div>
@endsection
