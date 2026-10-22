@extends('layouts.app')
@section('titulo','WhatsApp')
@section('conteudo')
<div class="max-w-2xl mx-auto space-y-4">
  <div>
    <h1 class="text-xl font-bold">Conexões de WhatsApp</h1>
    <p class="text-xs text-slate-500">Cadastre uma ou mais conexões (oficial ou não oficial). Depois, em <b>Minha empresa</b>, escolha qual usar em cada serviço (encomenda, passagem...). Reutilizável por todos os módulos.</p>
  </div>

  @if(session('erro'))<div class="rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('erro') }}</div>@endif

  {{-- NOVA CONEXÃO --}}
  <form method="post" action="{{ route('admin.whatsapp.salvar') }}" class="card p-4 space-y-3" x-data="{tipo:'nao_oficial'}">@csrf
    <div class="font-semibold text-sm">Nova conexão</div>
    <div class="grid sm:grid-cols-2 gap-3">
      <div><label class="label">Nome</label><input name="nome" class="input" required placeholder="Ex.: Evolution principal"></div>
      <div><label class="label">Tipo</label>
        <select name="tipo" x-model="tipo" class="input">
          @foreach(\App\Models\WhatsappConexao::TIPOS as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
        </select></div>
    </div>
    {{-- não oficial --}}
    <div x-show="tipo==='nao_oficial'" class="grid sm:grid-cols-2 gap-3">
      <div class="sm:col-span-2"><label class="label">URL base (Evolution)</label><input name="base_url" class="input" placeholder="https://evo.seudominio.com"></div>
      <div><label class="label">Instância</label><input name="instancia" class="input" placeholder="nome-da-instancia"></div>
      <div><label class="label">API Key</label><input name="api_key" class="input" placeholder="chave da instância"></div>
    </div>
    {{-- oficial --}}
    <div x-show="tipo==='oficial'" x-cloak class="grid sm:grid-cols-2 gap-3">
      <div><label class="label">Phone Number ID</label><input name="phone_number_id" class="input" placeholder="ex.: 123456789012345"></div>
      <div><label class="label">WhatsApp Business ID</label><input name="business_id" class="input" placeholder="opcional"></div>
      <div class="sm:col-span-2"><label class="label">Token (access token)</label><input name="token" class="input" placeholder="EAAG..."></div>
      <div><label class="label">Versão da API</label><input name="api_version" class="input" value="v21.0"></div>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="ativo" value="1" checked class="w-5 h-5 rounded"> Ativa</label>
    <button class="btn btn-accent">Salvar conexão</button>
  </form>

  {{-- LISTA --}}
  @forelse($conexoes as $c)
    <form method="post" action="{{ route('admin.whatsapp.salvar',$c) }}" class="card p-4 space-y-3 {{ $c->ativo?'':'opacity-60' }}" x-data="{tipo:'{{ $c->tipo }}'}">@csrf
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full {{ $c->ativo?'bg-emerald-500':'bg-slate-300' }}"></span>
        <span class="font-semibold text-sm flex-1">{{ $c->nome }}</span>
        <span class="text-xs text-slate-500">{{ \App\Models\WhatsappConexao::TIPOS[$c->tipo] ?? $c->tipo }}</span>
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        <div><label class="label">Nome</label><input name="nome" class="input" value="{{ $c->nome }}" required></div>
        <div><label class="label">Tipo</label>
          <select name="tipo" x-model="tipo" class="input">
            @foreach(\App\Models\WhatsappConexao::TIPOS as $k=>$l)<option value="{{ $k }}" @selected($c->tipo===$k)>{{ $l }}</option>@endforeach
          </select></div>
      </div>
      <div x-show="tipo==='nao_oficial'" class="grid sm:grid-cols-2 gap-3">
        <div class="sm:col-span-2"><label class="label">URL base</label><input name="base_url" class="input" value="{{ $c->base_url }}"></div>
        <div><label class="label">Instância</label><input name="instancia" class="input" value="{{ $c->instancia }}"></div>
        <div><label class="label">API Key</label><input name="api_key" class="input" value="{{ $c->api_key }}"></div>
      </div>
      <div x-show="tipo==='oficial'" x-cloak class="grid sm:grid-cols-2 gap-3">
        <div><label class="label">Phone Number ID</label><input name="phone_number_id" class="input" value="{{ $c->phone_number_id }}"></div>
        <div><label class="label">Business ID</label><input name="business_id" class="input" value="{{ $c->business_id }}"></div>
        <div class="sm:col-span-2"><label class="label">Token</label><input name="token" class="input" value="{{ $c->token }}"></div>
        <div><label class="label">Versão da API</label><input name="api_version" class="input" value="{{ $c->api_version }}"></div>
      </div>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="ativo" value="1" @checked($c->ativo) class="w-5 h-5 rounded"> Ativa</label>
      <div class="flex flex-wrap gap-2 items-center">
        <button class="btn btn-primary btn-sm">Salvar</button>
        <button type="button" onclick="if(confirm('Remover esta conexão?')) this.closest('.card').querySelector('.form-excluir').submit()" class="btn btn-ghost btn-sm text-red-500">Remover</button>
      </div>
      {{-- teste rápido --}}
      <div class="border-t pt-3 flex flex-wrap items-end gap-2">
        <div class="flex-1 min-w-[160px]"><label class="label !text-xs">Testar envio (telefone com DDD)</label>
          <input form="teste-{{ $c->id }}" name="telefone" class="input !min-h-[38px]" placeholder="92 99999-9999"></div>
        <button form="teste-{{ $c->id }}" class="btn btn-ghost btn-sm">Enviar teste</button>
      </div>
    </form>
    <form id="teste-{{ $c->id }}" method="post" action="{{ route('admin.whatsapp.testar',$c) }}" class="hidden">@csrf</form>
    <form method="post" action="{{ route('admin.whatsapp.excluir',$c) }}" class="form-excluir hidden">@csrf @method('DELETE')</form>
  @empty
    <div class="text-sm text-slate-400 text-center py-4">Nenhuma conexão ainda. Cadastre a primeira acima.</div>
  @endforelse
</div>
@endsection
