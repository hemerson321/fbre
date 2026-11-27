@extends('layouts.app')
@section('titulo','Telas de atendimento')
@section('conteudo')
@php
  $emUso = [];
  foreach ($telas as $tt) foreach ($tt->conexoes() as $cid) $emUso[$cid] = $tt->nome;
@endphp
<div class="max-w-4xl mx-auto space-y-4">
  <div class="flex items-center justify-between gap-2 flex-wrap">
    <div>
      <h1 class="text-xl font-extrabold">🖥️ Telas de atendimento</h1>
      <p class="text-sm text-slate-500">Cada tela recebe as conversas de certos números de WhatsApp e tem quem atende: um agente de IA e/ou os operadores que você escolher.</p>
    </div>
    <a href="{{ route('atendimento.index') }}" class="btn btn-sm">💬 Conversas</a>
  </div>

  @if(! $migrado)
    <div class="card p-4 border-amber-300 bg-amber-50 text-sm text-amber-800">⚠️ Falta ativar no servidor: rode <b>php artisan migrate --force</b> depois de atualizar o sistema.</div>
  @endif

  <div class="card p-4 text-sm bg-slate-50">
    <div class="font-bold">💬 Padrão <span class="chip bg-slate-200 text-slate-600 text-[10px] font-bold">sempre existe</span></div>
    <div class="text-xs text-slate-500 mt-1">É a tela que você já usa. Fica com as conversas de todo número de WhatsApp que <b>não</b> estiver em outra tela. Quem atende: os agentes de IA ativos e todos os operadores com permissão de atendimento. Respostas rápidas, etiquetas, funil e CRM valem em todas as telas.</div>
  </div>

  @foreach($telas->concat([null]) as $t)
  @php
    $novo = $t === null;
    $cxSel = $novo ? [] : $t->conexoes();
    $agSel = $novo ? [] : $t->agentes();
    $usSel = $novo ? [] : $t->usuarios();
  @endphp
  <div class="card p-4 space-y-3" x-data="{ atende:'{{ $novo || $t->usa_ia ? 'ia' : 'operadores' }}', agm:'{{ $agSel ? 'sel' : 'todos' }}', opm:'{{ $novo || $t->operadores_todos ? 'todos' : 'sel' }}' }">
    <div class="flex items-center justify-between gap-2">
      <div class="font-bold">{{ $novo ? '➕ Nova tela de atendimento' : '🖥️ '.$t->nome }}</div>
      @unless($novo)
        <form method="post" action="{{ route('atendimento.telas.excluir', $t) }}" onsubmit="return confirm('Remover esta tela? As conversas dos números dela voltam pra tela Padrão.')">@csrf @method('delete')<button class="text-xs text-red-500 hover:underline">excluir</button></form>
      @endunless
    </div>
    <form method="post" action="{{ $novo ? route('atendimento.telas.salvar') : route('atendimento.telas.salvar', $t) }}" class="space-y-3">
      @csrf
      <div><label class="label">Nome da tela</label><input name="nome" class="input" required maxlength="60" value="{{ $novo ? '' : $t->nome }}" placeholder="Ex.: Vendas IA, Encomendas, Suporte"></div>

      <div>
        <label class="label">Números de WhatsApp desta tela</label>
        <div class="grid sm:grid-cols-2 gap-1.5">
          @forelse($conexoes as $c)
            @php $outra = $emUso[$c->id] ?? null; $minha = in_array((int) $c->id, $cxSel, true); @endphp
            <label class="flex items-center gap-2 text-sm rounded-lg border px-2.5 py-2 bg-white">
              <input type="checkbox" name="conexao_ids[]" value="{{ $c->id }}" @checked($minha) class="w-5 h-5 rounded">
              <span class="min-w-0 truncate">{{ $c->rotulo() }} @if($outra && ! $minha)<span class="text-[11px] text-amber-600">· hoje na tela "{{ $outra }}"</span>@endif</span>
            </label>
          @empty
            <div class="text-xs text-slate-400">Nenhum WhatsApp conectado ainda (Administração → Integrações → WhatsApp).</div>
          @endforelse
        </div>
        <p class="text-[11px] text-slate-400 mt-1">A conversa cai na tela pelo número em que o cliente chamou. Um número fica em uma tela só — marcar aqui tira ele da outra.</p>
      </div>

      <div>
        <label class="label">Quem atende esta tela</label>
        <div class="grid sm:grid-cols-2 gap-2">
          <label class="flex items-start gap-2 text-sm rounded-xl border-2 p-3 cursor-pointer" :class="atende==='ia' ? 'border-emerald-400 bg-emerald-50' : 'border-slate-200 bg-white'">
            <input type="radio" name="atende" value="ia" x-model="atende" class="mt-1"> <span><b>🤖 Agente de IA</b><span class="block text-xs text-slate-500">A IA responde. Os operadores da tela acompanham e podem assumir.</span></span></label>
          <label class="flex items-start gap-2 text-sm rounded-xl border-2 p-3 cursor-pointer" :class="atende==='operadores' ? 'border-sky-400 bg-sky-50' : 'border-slate-200 bg-white'">
            <input type="radio" name="atende" value="operadores" x-model="atende" class="mt-1"> <span><b>👤 Só operadores</b><span class="block text-xs text-slate-500">Nenhuma IA responde nesta tela. Não gasta token.</span></span></label>
        </div>
      </div>

      <div x-show="atende==='ia'" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3 space-y-2">
        <div class="flex flex-wrap gap-4 text-sm font-semibold">
          <label class="flex items-center gap-1.5"><input type="radio" name="agentes_modo" value="todos" x-model="agm"> Qualquer agente de IA ativo</label>
          <label class="flex items-center gap-1.5"><input type="radio" name="agentes_modo" value="sel" x-model="agm"> Escolher os agentes</label>
        </div>
        <div x-show="agm==='sel'" x-cloak class="grid sm:grid-cols-2 gap-1.5">
          @forelse($agentes as $a)
            <label class="flex items-center gap-2 text-sm rounded-lg border px-2.5 py-2 bg-white"><input type="checkbox" name="agente_ids[]" value="{{ $a->id }}" @checked(in_array((int) $a->id, $agSel, true)) class="w-5 h-5 rounded"> 🤖 {{ $a->nome }} @unless($a->ativo)<span class="text-[11px] text-slate-400">(inativo)</span>@endunless</label>
          @empty
            <div class="text-xs text-slate-400">Nenhum agente cadastrado (Administração → Integrações → Agentes).</div>
          @endforelse
        </div>
        <p class="text-[11px] text-slate-500">Cada conversa é atendida por <b>um</b> agente: se você marcar mais de um, vale o primeiro que estiver ativo.</p>
      </div>

      <div class="rounded-xl border border-sky-200 bg-sky-50/50 p-3 space-y-2">
        <div class="text-xs font-bold text-slate-600 uppercase">Operadores que entram nesta tela</div>
        <div class="flex flex-wrap gap-4 text-sm font-semibold">
          <label class="flex items-center gap-1.5"><input type="radio" name="operadores_modo" value="todos" x-model="opm"> Todos (com permissão de atendimento)</label>
          <label class="flex items-center gap-1.5"><input type="radio" name="operadores_modo" value="sel" x-model="opm"> Escolher os operadores</label>
        </div>
        <div x-show="opm==='sel'" x-cloak class="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
          @foreach($usuarios as $us)
            <label class="flex items-center gap-2 text-sm rounded-lg border px-2.5 py-2 bg-white"><input type="checkbox" name="user_ids[]" value="{{ $us->id }}" @checked(in_array((int) $us->id, $usSel, true)) class="w-5 h-5 rounded"> <span class="truncate">{{ $us->name }}</span></label>
          @endforeach
        </div>
        <p class="text-[11px] text-slate-500">Administrador sempre entra em todas as telas. O operador ainda precisa ter a permissão de <b>Atendimento</b> no cadastro dele.</p>
      </div>

      <div class="flex justify-end"><button class="btn {{ $novo ? 'btn-accent' : 'btn-primary' }} btn-sm" @disabled(! $migrado)>{{ $novo ? 'Criar tela' : 'Salvar' }}</button></div>
    </form>
  </div>
  @endforeach
</div>
@endsection
