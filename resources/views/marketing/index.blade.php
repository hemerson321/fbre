@extends('layouts.app')
@section('titulo', 'Áudio indoor')
@section('conteudo')
@php
  $diasLabel = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
@endphp
<div class="space-y-4" x-data="{ tipo:'tts' }">

  {{-- Cabeçalho --}}
  <div class="rounded-2xl bg-white px-4 py-3 flex items-center justify-between gap-3 flex-wrap"
       style="border:1px solid #e2e8f0; box-shadow: inset 4px 0 0 var(--cor1), 0 1px 2px rgba(15,23,42,.04)">
    <div class="flex items-center gap-3 min-w-0">
      <div class="w-11 h-11 rounded-xl flex items-center justify-center text-xl shrink-0" style="background:color-mix(in srgb, var(--cor1) 12%, white)">🔊</div>
      <div class="min-w-0">
        <h1 class="text-lg font-extrabold leading-tight" style="color:var(--cor1)">Áudio indoor <span class="text-slate-400 font-normal text-sm">· áudio terminal</span></h1>
        <div class="text-[12px] text-slate-500">Crie áudios falados, programe por horário/dia e toque agora nos terminais (caixas de som).</div>
      </div>
    </div>
    <a href="{{ route('marketing.signage') }}" class="btn btn-ghost btn-sm">📺 Ir pro TV indoor</a>
  </div>

  {{-- TERMINAIS --}}
  <div class="card overflow-hidden">
    <div class="p-3 border-b bg-slate-50 flex items-center justify-between gap-2 flex-wrap">
      <div class="font-bold text-sm text-slate-700">🖥 Terminais <span class="text-[11px] font-normal text-slate-400">· onde o áudio toca (tablet/PC com caixa de som)</span></div>
    </div>
    <div class="p-3 grid gap-3 lg:grid-cols-2">
      <form method="post" action="{{ route('marketing.terminal.salvar') }}" class="rounded-xl border border-slate-200 p-3 space-y-2">
        @csrf
        <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Novo terminal</div>
        <div><label class="label">Nome</label><input name="nome" class="input" required placeholder="Ex.: Balcão principal"></div>
        <div><label class="label">Local (opcional)</label><input name="local" class="input" placeholder="Ex.: guichê de embarque"></div>
        <button class="btn btn-primary btn-sm">Criar terminal</button>
      </form>
      <div class="space-y-2">
        @forelse($terminais as $t)
        <div class="rounded-xl border border-slate-200 p-3 flex items-center gap-2 flex-wrap">
          <div class="min-w-0 flex-1">
            <div class="font-semibold text-slate-800 truncate">🖥 {{ $t->nome }}
              <span class="ml-1 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $t->online() ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }}">{{ $t->online() ? 'ligado' : 'desligado' }}</span>
            </div>
            <div class="text-[11px] text-slate-400 truncate">{{ $t->local ?: 'sem local' }}</div>
            <input readonly value="{{ route('audio.player', $t->token) }}" class="mt-1 w-full text-[11px] rounded-lg border border-slate-200 bg-slate-50 px-2 py-1" onclick="this.select()">
          </div>
          <div class="flex items-center gap-1">
            <a href="{{ route('audio.player', $t->token) }}" target="_blank" class="btn btn-ghost btn-sm">▶ Abrir</a>
            <form method="post" action="{{ route('marketing.terminal.excluir', $t) }}" onsubmit="return confirm('Excluir o terminal {{ $t->nome }}? As programações dele também saem.')">@csrf @method('DELETE')
              <button class="btn btn-ghost btn-sm text-rose-600">🗑</button>
            </form>
          </div>
        </div>
        @empty
        <div class="text-center text-slate-400 text-sm p-4">Nenhum terminal ainda. Crie um e abra o link num tablet com som.</div>
        @endforelse
      </div>
    </div>
  </div>

  {{-- ÁUDIOS --}}
  <div class="card overflow-hidden">
    <div class="p-3 border-b bg-slate-50 font-bold text-sm text-slate-700">🎙 Áudios</div>
    <div class="p-3 grid gap-3 lg:grid-cols-2">
      <form method="post" action="{{ route('marketing.audio.salvar') }}" enctype="multipart/form-data" class="rounded-xl border border-slate-200 p-3 space-y-2">
        @csrf
        <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Novo áudio</div>
        <div><label class="label">Nome</label><input name="nome" class="input" required placeholder="Ex.: Chamada de embarque Codajás"></div>
        <div class="grid grid-cols-2 gap-2">
          <button type="button" @click="tipo='tts'" :class="tipo==='tts' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-300'" class="rounded-xl border py-2 text-sm font-bold">🗣 Falado (texto)</button>
          <button type="button" @click="tipo='arquivo'" :class="tipo==='arquivo' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-300'" class="rounded-xl border py-2 text-sm font-bold">📁 Arquivo</button>
        </div>
        <input type="hidden" name="tipo" :value="tipo">
        <div x-show="tipo==='tts'" x-cloak class="space-y-2">
          <div><label class="label">Texto que vai ser falado</label><textarea name="texto" class="input" rows="3" placeholder="Ex.: Senhores passageiros, o barco para Codajás parte às 14 horas."></textarea></div>
        </div>
        <div x-show="tipo==='arquivo'" x-cloak class="space-y-2">
          <div><label class="label">Arquivo de áudio (mp3, ogg, wav…)</label><input type="file" name="arquivo" accept="audio/*" class="input"></div>
          <div><label class="label">…ou cole o link do áudio</label><input name="arquivo_url" class="input" placeholder="https://..."></div>
        </div>
        <button class="btn btn-primary btn-sm">Salvar áudio</button>
      </form>
      <div class="space-y-2">
        @forelse($audios as $a)
        <div class="rounded-xl border border-slate-200 p-3">
          <div class="flex items-center gap-2 flex-wrap">
            <div class="min-w-0 flex-1">
              <div class="font-semibold text-slate-800 truncate">{{ $a->tipo === 'arquivo' ? '📁' : '🗣' }} {{ $a->nome }}
                @unless($a->ativo)<span class="ml-1 rounded-full bg-slate-100 text-slate-400 px-2 py-0.5 text-[10px] font-bold">pausado</span>@endunless
              </div>
              <div class="text-[11px] text-slate-400 truncate">{{ $a->tipo === 'arquivo' ? 'arquivo de áudio' : \Illuminate\Support\Str::limit((string) $a->texto, 70) }}</div>
            </div>
            <form method="post" action="{{ route('marketing.audio.excluir', $a) }}" onsubmit="return confirm('Excluir o áudio {{ $a->nome }}?')">@csrf @method('DELETE')
              <button class="btn btn-ghost btn-sm text-rose-600">🗑</button>
            </form>
          </div>
          <form method="post" action="{{ route('marketing.audio.tocar', $a) }}" class="mt-2 flex items-center gap-2 flex-wrap">
            @csrf
            <select name="terminal_id" class="input !min-h-[34px] !py-1 max-w-[180px] text-[13px]">
              <option value="">Todos os terminais</option>
              @foreach($terminais as $t)<option value="{{ $t->id }}">{{ $t->nome }}</option>@endforeach
            </select>
            <button class="btn btn-sm text-white" style="background:#059669">▶ Tocar agora</button>
          </form>
        </div>
        @empty
        <div class="text-center text-slate-400 text-sm p-4">Nenhum áudio ainda.</div>
        @endforelse
      </div>
    </div>
  </div>

  {{-- PROGRAMAÇÃO --}}
  <div class="card overflow-hidden">
    <div class="p-3 border-b bg-slate-50 font-bold text-sm text-slate-700">⏰ Programação <span class="text-[11px] font-normal text-slate-400">· toca sozinho no dia e horário</span></div>
    <div class="p-3 grid gap-3 lg:grid-cols-2">
      <form method="post" action="{{ route('marketing.prog.salvar') }}" class="rounded-xl border border-slate-200 p-3 space-y-2">
        @csrf
        <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Nova programação</div>
        <div><label class="label">Áudio</label>
          <select name="audio_id" class="input" required>
            <option value="">— escolher —</option>
            @foreach($audios as $a)<option value="{{ $a->id }}">{{ $a->nome }}</option>@endforeach
          </select>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div><label class="label">Horário</label><input type="time" name="hora" class="input" required></div>
          <div><label class="label">Terminal</label>
            <select name="terminal_id" class="input">
              <option value="">Todos</option>
              @foreach($terminais as $t)<option value="{{ $t->id }}">{{ $t->nome }}</option>@endforeach
            </select>
          </div>
        </div>
        <div>
          <label class="label">Dias da semana <span class="text-slate-400 font-normal">(nenhum = todos)</span></label>
          <div class="flex flex-wrap gap-1.5 mt-1">
            @foreach($diasLabel as $i => $dl)
            <label class="cursor-pointer"><input type="checkbox" name="dias[]" value="{{ $i }}" class="peer sr-only"><span class="inline-block rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-500 peer-checked:bg-slate-800 peer-checked:text-white peer-checked:border-slate-800">{{ $dl }}</span></label>
            @endforeach
          </div>
        </div>
        <button class="btn btn-primary btn-sm">Agendar</button>
      </form>
      <div class="space-y-2">
        @forelse($programacoes as $p)
        <div class="rounded-xl border border-slate-200 p-3 flex items-center gap-2 flex-wrap">
          <div class="text-center shrink-0 rounded-lg bg-slate-100 px-2.5 py-1"><div class="text-base font-extrabold text-slate-800 leading-none">{{ $p->hora }}</div></div>
          <div class="min-w-0 flex-1">
            <div class="font-semibold text-slate-800 truncate">{{ optional($p->audio)->nome ?? '—' }} @unless($p->ativo)<span class="text-[10px] text-slate-400">(pausado)</span>@endunless</div>
            <div class="text-[11px] text-slate-400 truncate">
              {{ optional($p->terminal)->nome ?? 'Todos os terminais' }} ·
              @php $ds = array_map('intval', (array) $p->dias); @endphp
              {{ empty($ds) ? 'todos os dias' : collect($ds)->map(fn ($d) => $diasLabel[$d] ?? '')->implode(', ') }}
            </div>
          </div>
          <form method="post" action="{{ route('marketing.prog.excluir', $p) }}" onsubmit="return confirm('Remover esta programação?')">@csrf @method('DELETE')
            <button class="btn btn-ghost btn-sm text-rose-600">🗑</button>
          </form>
        </div>
        @empty
        <div class="text-center text-slate-400 text-sm p-4">Nenhuma programação ainda.</div>
        @endforelse
      </div>
    </div>
  </div>

  {{-- DISPAROS RECENTES --}}
  @if($disparos->isNotEmpty())
  <div class="card overflow-hidden">
    <div class="p-3 border-b bg-slate-50 font-bold text-sm text-slate-700">🕘 Tocados recentemente (manual)</div>
    <div class="divide-y text-sm">
      @foreach($disparos as $d)
      <div class="flex items-center justify-between gap-2 p-2.5">
        <div class="truncate">▶ {{ optional($d->audio)->nome ?? '—' }} <span class="text-[11px] text-slate-400">· {{ optional($d->terminal)->nome ?? 'todos' }}</span></div>
        <div class="text-[11px] text-slate-400 whitespace-nowrap">{{ optional($d->created_at)->format('d/m H:i') }}</div>
      </div>
      @endforeach
    </div>
  </div>
  @endif

</div>
@endsection
