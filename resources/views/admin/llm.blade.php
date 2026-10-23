@extends('layouts.app')
@section('titulo','Provedores de IA')
@section('conteudo')
<div class="max-w-3xl mx-auto space-y-5">
  <div>
    <h1 class="text-xl font-bold">Provedores de IA (LLM)</h1>
    <p class="text-sm text-slate-500">Cadastre quantos quiser (Claude, OpenAI). Depois, cada <b>agente</b> escolhe qual provedor usar. Reutilizável.</p>
  </div>

  @foreach($provedores as $pv)
  <div class="card p-4 space-y-3">
    <div class="flex items-center justify-between gap-2">
      <div class="font-bold">{{ $pv->rotulo() }} @if(!$pv->ativo)<span class="text-xs text-slate-400">(inativo)</span>@endif</div>
      <form method="post" action="{{ route('admin.llm.testar',$pv) }}">@csrf<button class="btn btn-ghost btn-sm">🔌 Testar</button></form>
    </div>
    <form method="post" action="{{ route('admin.llm.salvar',$pv) }}" class="grid sm:grid-cols-2 gap-3">@csrf
      <div><label class="label">Nome</label><input name="nome" class="input" value="{{ $pv->nome }}" required maxlength="80"></div>
      <div><label class="label">Provedor</label><select name="provider" class="input">@foreach(\App\Models\LlmProvedor::PROVIDERS as $k=>$l)<option value="{{ $k }}" @selected($pv->provider==$k)>{{ $l }}</option>@endforeach</select></div>
      <div class="sm:col-span-2"><label class="label">Token (secreto)</label><input name="token" type="password" class="input font-mono text-sm" placeholder="•••• deixe em branco para manter" autocomplete="off"></div>
      <div><label class="label">Modelo (opcional)</label><input name="modelo" class="input font-mono text-sm" value="{{ $pv->modelo }}" placeholder="ex.: claude-3-5-haiku-latest"></div>
      <div><label class="label">Base URL (opcional)</label><input name="base_url" class="input font-mono text-sm" value="{{ $pv->base_url }}" placeholder="padrão da API"></div>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="ativo" value="1" @checked($pv->ativo) class="w-5 h-5 rounded"> Ativo</label>
      <div class="sm:col-span-2 flex justify-end border-t pt-3"><button class="btn btn-primary btn-sm">Salvar</button></div>
    </form>
    <form method="post" action="{{ route('admin.llm.excluir',$pv) }}" onsubmit="return confirm('Remover?')">@csrf @method('delete')<button class="text-xs text-red-500 hover:underline">excluir</button></form>
  </div>
  @endforeach

  <div class="card p-4 space-y-3">
    <div class="font-bold text-sm text-slate-600 uppercase">➕ Novo provedor</div>
    <form method="post" action="{{ route('admin.llm.salvar') }}" class="grid sm:grid-cols-2 gap-3">@csrf
      <div><label class="label">Nome</label><input name="nome" class="input" placeholder="Ex.: Claude principal" required maxlength="80"></div>
      <div><label class="label">Provedor</label><select name="provider" class="input">@foreach(\App\Models\LlmProvedor::PROVIDERS as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
      <div class="sm:col-span-2"><label class="label">Token (secreto)</label><input name="token" type="password" class="input font-mono text-sm" placeholder="sk-ant-... / sk-..." autocomplete="off"></div>
      <div class="sm:col-span-2"><label class="label">Modelo (opcional — descobre sozinho se vazio)</label><input name="modelo" class="input font-mono text-sm" placeholder="ex.: claude-3-5-haiku-latest / gpt-4o-mini"></div>
      <div class="sm:col-span-2 flex justify-end"><button class="btn btn-accent btn-sm">Criar provedor</button></div>
    </form>
  </div>
</div>
@endsection
