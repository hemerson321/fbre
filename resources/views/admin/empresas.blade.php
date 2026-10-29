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
  <div class="sm:col-span-2"><label class="label">Domínio próprio (opcional)</label><input name="dominio" class="input" placeholder="ex.: ajatopassagem.com.br" autocapitalize="none" inputmode="url"><p class="text-[11px] text-slate-400 mt-0.5">O cliente acessa por esse domínio e vê a marca dele. Aponte o DNS pro mesmo servidor e adicione o domínio no EasyPanel.</p></div>
  <div class="sm:col-span-2">
    <label class="label">Módulos que esta empresa contrata</label>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 mt-1">
      @foreach(\App\Support\Modulos::LISTA as $mk => $info)
        <label class="flex items-center gap-2 border rounded-lg px-2 py-1 text-sm"><input type="checkbox" name="modulos_ativos[]" value="{{ $mk }}" class="w-4 h-4 rounded shrink-0" checked> <span class="truncate">{{ $info[1] }} {{ $info[0] }}</span></label>
      @endforeach
    </div>
    <p class="text-[11px] text-slate-400 mt-0.5">Desmarque os que o cliente não vai usar. A cor de cada módulo o cliente ajusta em "Minha empresa".</p>
  </div>
  <div class="sm:col-span-2 label pt-2">Usuário administrador do cliente</div>
  <div><label class="label">Nome</label><input name="admin_nome" class="input" required></div>
  <div><label class="label">Login</label><input name="admin_login" class="input" required placeholder="ex.: admin.empresa" autocapitalize="none"></div>
  <div><label class="label">Senha</label><input name="admin_senha" class="input" required minlength="6"></div>
  <div class="flex items-end"><button class="btn btn-accent w-full">Criar empresa</button></div>
</form>

<div class="card divide-y">
@foreach($empresas as $e)
  @php $loginUrl = $e->dominio ? 'https://'.$e->dominio : url('/'.$e->slug); @endphp
  <div class="p-3 {{ $e->ativo?'':'opacity-50' }}" x-data="{ ed:false }">
    <div class="flex items-center gap-3">
      @if($e->logo)<img src="/{{ $e->logo }}" class="w-10 h-10 rounded-lg object-cover border">@else<div class="w-10 h-10 rounded-lg bg-slate-200"></div>@endif
      <div class="flex-1 min-w-0">
        <div class="font-semibold">{{ $e->nomeExibicao() }} @if($ativa==$e->id)<span class="chip st-verde">operando</span>@endif @if($e->vencida())<span class="chip st-vermelho">vencida</span>@endif @if(!$e->ativo)<span class="chip st-cinza">inativa</span>@endif</div>
        <div class="text-xs text-slate-500">{{ $e->cnpj }} · {{ $e->users_count }} usuários · {{ $e->encomendas_count }} encomendas · validade {{ $e->validade?->format('d/m/Y') ?? '—' }}</div>
        <div class="text-xs mt-0.5">Login: <a href="{{ $loginUrl }}" target="_blank" class="font-mono text-[var(--cor2)] hover:underline">{{ $loginUrl }}</a></div>
        <form method="post" action="{{ route('super.empresas.dominio',$e) }}" class="flex items-center gap-1 mt-1">@csrf
          <span class="text-xs text-slate-500">Domínio:</span>
          <input name="dominio" value="{{ $e->dominio }}" placeholder="sem domínio próprio" class="input !min-h-0 !py-0.5 !px-1.5 text-xs w-52" autocapitalize="none" inputmode="url">
          <button class="btn btn-ghost btn-sm !py-0.5">Salvar</button>
          @if($e->dominio)<a href="https://{{ $e->dominio }}" target="_blank" class="text-xs text-[var(--cor2)] hover:underline">abrir ↗</a>@endif
        </form>
      </div>
      <div class="flex flex-col gap-1 shrink-0">
        <form method="post" action="{{ route('super.empresas.entrar',$e) }}">@csrf<button class="btn btn-ghost btn-sm w-full">Entrar</button></form>
        <button type="button" class="btn btn-ghost btn-sm w-full" @click="ed=!ed">✏️ Editar</button>
        <form method="post" action="{{ route('super.empresas.toggle',$e) }}">@csrf<button class="btn btn-sm w-full {{ $e->ativo?'btn-danger':'btn-primary' }}">{{ $e->ativo?'Bloquear':'Ativar' }}</button></form>
        @if($ativa!=$e->id)
        <form method="post" action="{{ route('super.empresas.excluir',$e) }}" onsubmit="return confirm('Excluir esta empresa? Remove a empresa e seus usuários. Só funciona se não tiver movimento (encomendas/passagens). Não dá pra desfazer.')">@csrf
          <button class="btn btn-sm w-full bg-red-100 text-red-700 hover:bg-red-200">🗑 Excluir</button>
        </form>
        @endif
      </div>
    </div>
    {{-- edição rápida dos dados da empresa --}}
    <form x-show="ed" x-cloak method="post" action="{{ route('super.empresas.editar',$e) }}" class="grid sm:grid-cols-3 gap-2 mt-2 border-t pt-2">@csrf
      <div><label class="label !text-xs">Razão social</label><input name="nome" class="input !min-h-0 py-1 text-sm" value="{{ $e->nome }}" required></div>
      <div><label class="label !text-xs">Nome fantasia</label><input name="nome_fantasia" class="input !min-h-0 py-1 text-sm" value="{{ $e->nome_fantasia }}"></div>
      <div><label class="label !text-xs">CNPJ</label><input name="cnpj" class="input !min-h-0 py-1 text-sm" value="{{ $e->cnpj }}"></div>
      <div class="sm:col-span-3">
        <label class="label !text-xs">Módulos que esta empresa contrata</label>
        @php $mAtivos = $e->config('modulos_ativos'); $todosM = empty($mAtivos); @endphp
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 mt-1">
          @foreach(\App\Support\Modulos::LISTA as $mk => $info)
            <label class="flex items-center gap-2 border rounded-lg px-2 py-1 text-sm"><input type="checkbox" name="modulos_ativos[]" value="{{ $mk }}" class="w-4 h-4 rounded shrink-0" @checked($todosM || in_array($mk, (array) $mAtivos, true))> <span class="truncate">{{ $info[1] }} {{ $info[0] }}</span></label>
          @endforeach
        </div>
      </div>
      <div class="sm:col-span-3 flex gap-2"><button class="btn btn-accent btn-sm">Salvar dados</button><button type="button" class="btn btn-ghost btn-sm" @click="ed=false">Cancelar</button></div>
    </form>
  </div>
@endforeach
</div>
</div>
@endsection
