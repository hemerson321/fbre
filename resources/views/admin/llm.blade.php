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

  <div class="card p-4 text-xs text-slate-600 space-y-1.5 bg-sky-50 border-sky-200">
    <div class="font-bold text-sm text-slate-700">💡 Qual modelo colocar</div>
    <div><b>DeepSeek:</b> <code>deepseek-flash</code> (barato, bom pra ser o principal) · <code>deepseek-v4-pro</code> (mais forte). Em branco usa o <code>deepseek-flash</code>.</div>
    <div><b>GLM (Z.ai):</b> <code>glm-4.7</code> (padrão se ficar em branco) · <code>glm-4.7-flashx</code> (mais barato) · <code>glm-5.3</code> (mais forte, sempre "pensa" e gasta mais saída).</div>
    <div><b>Claude:</b> <code>claude-haiku-5-5</code> (barato) · <code>claude-sonnet-5-5</code> · <code>claude-opus-5-5</code> (mais caro). <b>Base URL</b> só é usada por DeepSeek e GLM, e só se a sua conta tiver outro endereço de API.</div>
    <div class="pt-1 border-t border-sky-200"><b>Como economizar sem errar:</b> no agente, coloque um modelo barato como <b>principal</b> e um forte como <b>escalonamento</b>. O barato atende o dia a dia; quando ele fica em dúvida, se enrola ou falha, o sistema repete aquela resposta no modelo forte. Cada resposta escalonada custa o preço do modelo forte (com Opus é o mais caro) — acompanhe em Atendimento → 🧮 Custo IA.</div>
  </div>

  <div class="card p-4 space-y-3">
    <div class="font-bold text-sm text-slate-600 uppercase">➕ Novo provedor</div>
    <form method="post" action="{{ route('admin.llm.salvar') }}" class="grid sm:grid-cols-2 gap-3">@csrf
      <div><label class="label">Nome</label><input name="nome" class="input" placeholder="Ex.: Claude principal" required maxlength="80"></div>
      <div><label class="label">Provedor</label><select name="provider" class="input">@foreach(\App\Models\LlmProvedor::PROVIDERS as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
      <div class="sm:col-span-2"><label class="label">Token (secreto)</label><input name="token" type="password" class="input font-mono text-sm" placeholder="chave da API do provedor" autocomplete="off"></div>
      <div class="sm:col-span-2"><label class="label">Modelo (opcional — descobre sozinho se vazio)</label><input name="modelo" class="input font-mono text-sm" placeholder="ex.: deepseek-flash / glm-4.7 / claude-haiku-5-5"></div>
      <div class="sm:col-span-2 flex justify-end"><button class="btn btn-accent btn-sm">Criar provedor</button></div>
    </form>
  </div>
</div>
@endsection
