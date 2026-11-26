@extends('layouts.app')
@section('titulo','Digital Signage')
@section('conteudo')
@php
  $totMidias = $telas->sum(fn ($t) => $t->midias->count());
  $online = $telas->filter(fn ($t) => $t->online())->count();
  $diasNome = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
  $modoRot = ['sequencia' => ['🔁', 'Sequência', '#2563eb'], 'intervalo' => ['⏱️', 'A cada', '#7c3aed'], 'horario' => ['⏰', 'Horário', '#db2777']];
@endphp
@include('signage._engine')
<style>
  .sgx-hero{background:linear-gradient(120deg,#4c1d95,#7c3aed 45%,#db2777);color:#fff;border-radius:20px;position:relative;overflow:hidden}
  .sgx-hero:before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 85% 10%,rgba(255,255,255,.28),transparent 35%),radial-gradient(circle at 10% 110%,rgba(255,255,255,.18),transparent 40%)}
  .sgx-tv{background:#0b0f19;border-radius:18px;padding:10px;box-shadow:0 18px 40px -18px rgba(15,23,42,.65), inset 0 0 0 2px #1f2937;position:relative}
  .sgx-tv .tela{border-radius:10px;overflow:hidden;background:#000;position:relative}
  .sgx-tv iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
  .sgx-pe{width:34%;height:8px;background:#0b0f19;border-radius:0 0 10px 10px;margin:0 auto}
  .sgx-thumb{width:84px;height:48px;border-radius:8px;overflow:hidden;background:#0f172a;flex:none;position:relative}
  .sgx-thumb.v{width:40px;height:70px}
  .sgx-thumb img,.sgx-thumb video{width:100%;height:100%;object-fit:cover}
  .sgx-lay{border-radius:10px;overflow:hidden;position:relative;aspect-ratio:16/10;cursor:pointer;outline:3px solid transparent;outline-offset:2px;transition:.15s}
  .sgx-lay.sel{outline-color:#7c3aed}
  .sgx-chip{display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:2px 9px;font-size:11px;font-weight:700}
</style>

<div x-data="sgAdmin()" class="space-y-5">
  {{-- ===== topo ===== --}}
  <div class="sgx-hero p-5 sm:p-7">
    <div class="relative flex items-center justify-between gap-4 flex-wrap">
      <div class="min-w-0">
        <div class="text-[11px] font-bold uppercase tracking-[.2em] opacity-80">Marketing · Mídia indoor</div>
        <h1 class="text-2xl sm:text-3xl font-black leading-tight">📺 Digital Signage</h1>
        <p class="text-sm opacity-90 mt-1 max-w-2xl">Cadastre os pontos (TVs e totens), suba fotos, vídeos e avisos e programe o que passa em cada tela — em sequência, a cada X minutos ou em horário marcado.</p>
      </div>
      <button type="button" @click="novaTela()" class="relative rounded-xl bg-white text-violet-700 font-extrabold px-5 py-3 shadow-lg hover:scale-[1.02] transition">＋ Nova tela</button>
    </div>
    <div class="relative grid grid-cols-3 gap-3 mt-5 max-w-xl">
      <div class="rounded-xl bg-white/15 backdrop-blur px-4 py-3"><div class="text-2xl font-black">{{ $telas->count() }}</div><div class="text-[11px] uppercase tracking-wide opacity-80">telas / pontos</div></div>
      <div class="rounded-xl bg-white/15 backdrop-blur px-4 py-3"><div class="text-2xl font-black">{{ $online }}</div><div class="text-[11px] uppercase tracking-wide opacity-80">ligadas agora</div></div>
      <div class="rounded-xl bg-white/15 backdrop-blur px-4 py-3"><div class="text-2xl font-black">{{ $totMidias }}</div><div class="text-[11px] uppercase tracking-wide opacity-80">mídias e avisos</div></div>
    </div>
  </div>

  {{-- ===== como funciona ===== --}}
  <div class="grid sm:grid-cols-3 gap-3 text-sm">
    <div class="card p-4"><div class="font-bold">1 · Cadastre o ponto</div><div class="text-slate-500 text-[13px]">Nome, local e se a tela é horizontal ou vertical. O sistema gera um link só dela.</div></div>
    <div class="card p-4"><div class="font-bold">2 · Suba o conteúdo</div><div class="text-slate-500 text-[13px]">Foto, vídeo ou aviso em texto (10 modelos prontos, o texto se ajusta sozinho ao tamanho da tela).</div></div>
    <div class="card p-4"><div class="font-bold">3 · Abra o link na TV Box</div><div class="text-slate-500 text-[13px]">Em modo quiosque / tela cheia. A tela se atualiza sozinha quando você muda a programação.</div></div>
  </div>

  @if($telas->isEmpty())
  <div class="card p-10 text-center">
    <div class="text-5xl mb-2">📺</div>
    <div class="font-bold text-lg">Nenhuma tela cadastrada ainda</div>
    <div class="text-slate-500 text-sm mb-4">Comece criando o primeiro ponto. Dá pra ver a demonstração aqui mesmo, antes de ligar a TV.</div>
    <button type="button" @click="novaTela()" class="btn btn-primary">＋ Criar a primeira tela</button>
  </div>
  @endif

  {{-- ===== telas ===== --}}
  @foreach($telas as $t)
  @php $link = route('signage.player', $t->token); @endphp
  <div class="card overflow-hidden">
    <div class="p-4 border-b flex items-center justify-between gap-3 flex-wrap" style="background:linear-gradient(90deg,#faf5ff,#fff)">
      <div class="min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="text-lg font-extrabold text-slate-800">{{ $t->nome }}</span>
          <span class="sgx-chip" style="background:#ede9fe;color:#5b21b6">{{ $t->vertical() ? '▯ Vertical' : '▭ Horizontal' }}</span>
          @if(! $t->ativo)
            <span class="sgx-chip" style="background:#f1f5f9;color:#64748b">⏸ pausada</span>
          @elseif($t->online())
            <span class="sgx-chip" style="background:#dcfce7;color:#166534"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> ligada agora</span>
          @else
            <span class="sgx-chip" style="background:#fef3c7;color:#92400e">desligada{{ $t->visto_em ? ' · vista '.$t->visto_em->diffForHumans() : ' · nunca abriu' }}</span>
          @endif
        </div>
        <div class="text-[13px] text-slate-500 mt-0.5">📍 {{ $t->local ?: 'Local não informado' }}</div>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" class="btn btn-sm" @click="editarTela(@js(['id' => $t->id, 'nome' => $t->nome, 'local' => $t->local, 'orientacao' => $t->orientacao, 'ativo' => $t->ativo]))">✏️ Editar</button>
        <form method="post" action="{{ route('marketing.signage.tela.excluir', $t) }}" onsubmit="return confirm(@js('Excluir a tela '.$t->nome.' e todas as mídias dela? Não dá pra desfazer.'))">
          @csrf @method('DELETE')
          <button class="btn btn-sm" style="color:#b91c1c">🗑 Excluir</button>
        </form>
      </div>
    </div>

    <div class="p-4 grid lg:grid-cols-[minmax(0,380px)_1fr] gap-5">
      {{-- demonstração ao vivo --}}
      <div>
        <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-2">Demonstração ao vivo</div>
        <div class="mx-auto" style="max-width:{{ $t->vertical() ? '220px' : '380px' }}">
          <div class="sgx-tv">
            <div class="tela" style="aspect-ratio:{{ $t->vertical() ? '9/16' : '16/9' }}">
              <iframe src="{{ $link }}?demo=1" loading="lazy" title="Demonstração {{ $t->nome }}"></iframe>
            </div>
          </div>
          <div class="sgx-pe"></div>
        </div>
        <div class="mt-3 rounded-xl border bg-slate-50 p-2.5">
          <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-1">Link da TV Box (quiosque / tela cheia)</div>
          <div class="flex items-center gap-1.5">
            <input readonly value="{{ $link }}" class="input !min-h-[36px] text-xs flex-1" onclick="this.select()">
            <button type="button" class="btn btn-sm" @click="copiar(@js($link), $event)">Copiar</button>
            <a href="{{ $link }}" target="_blank" class="btn btn-sm">Abrir</a>
          </div>
        </div>
      </div>

      {{-- programação --}}
      <div class="min-w-0">
        <div class="flex items-center justify-between gap-2 mb-2 flex-wrap">
          <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Programação · {{ $t->midias->count() }} item(ns)</div>
          <div class="flex gap-1.5">
            <button type="button" class="btn btn-sm btn-primary" @click="novaMidia({{ $t->id }}, @js($t->orientacao), 'midia')">🖼️ Foto / Vídeo</button>
            <button type="button" class="btn btn-sm btn-accent" @click="novaMidia({{ $t->id }}, @js($t->orientacao), 'texto')">📝 Aviso em texto</button>
          </div>
        </div>
        <div class="divide-y rounded-xl border bg-white">
          @forelse($t->midias as $m)
          @php
            $mr = $modoRot[$m->modo] ?? $modoRot['sequencia'];
            $quando = $m->modo === 'intervalo' ? 'A cada '.$m->intervalo_min.' min' : ($m->modo === 'horario' ? implode(' · ', (array) $m->horarios) : 'Sequência');
            $dj = $m->paraPlayer();
            $ed = ['id' => $m->id, 'tipo' => $m->tipo, 'titulo' => $m->titulo, 'texto' => $m->texto, 'layout' => (int) $m->layout, 'duracao' => (int) $m->duracao,
              'modo' => $m->modo, 'intervalo_min' => $m->intervalo_min, 'horarios' => implode(', ', (array) $m->horarios), 'dias' => array_map('intval', (array) $m->dias),
              'data_inicio' => optional($m->data_inicio)->toDateString(), 'data_fim' => optional($m->data_fim)->toDateString(), 'som' => (bool) $m->som, 'ativo' => (bool) $m->ativo,
              'ordem' => (int) $m->ordem, 'url' => $m->url('arquivo'), 'audio' => $m->url('audio')];
          @endphp
          <div class="p-2.5 flex items-center gap-3 {{ $m->ativo ? '' : 'opacity-50' }}">
            <div class="sgx-thumb {{ $t->vertical() ? 'v' : '' }}">
              @if($m->tipo === 'imagem')
                <img src="{{ $m->url('arquivo') }}" alt="" loading="lazy">
              @elseif($m->tipo === 'video')
                <video src="{{ $m->url('arquivo') }}#t=0.5" preload="metadata" muted></video>
                <span class="absolute inset-0 flex items-center justify-center text-white text-lg" style="text-shadow:0 1px 4px #000">▶</span>
              @else
                <div class="sg-stage" style="position:absolute;inset:0" x-init="$nextTick(() => SG.desenhar($el, @js($dj), {empresa: @js($empresa)}))"></div>
              @endif
            </div>
            <div class="min-w-0 flex-1">
              <div class="font-semibold text-sm truncate">{{ $m->titulo ?: ($m->tipo === 'texto' ? \Illuminate\Support\Str::limit($m->texto, 60) : ($m->tipo === 'video' ? 'Vídeo' : 'Imagem')) }}</div>
              <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                <span class="sgx-chip" style="background:#f1f5f9;color:#334155">{{ ['imagem' => '🖼️ Foto', 'video' => '🎬 Vídeo', 'texto' => '📝 '.($layouts[$m->layout] ?? 'Aviso')][$m->tipo] ?? $m->tipo }}</span>
                <span class="sgx-chip" style="background:color-mix(in srgb, {{ $mr[2] }} 12%, white);color:{{ $mr[2] }}">{{ $mr[0] }} {{ $quando }}</span>
                <span class="sgx-chip" style="background:#f8fafc;color:#64748b">{{ $m->duracao > 0 ? $m->duracao.'s na tela' : 'até o fim do vídeo' }}</span>
                @if($m->dias)
                  <span class="sgx-chip" style="background:#f8fafc;color:#64748b">{{ implode(' ', array_map(fn ($d) => $diasNome[$d] ?? '', (array) $m->dias)) }}</span>
                @endif
                @if($m->audio)
                  <span class="sgx-chip" style="background:#ecfeff;color:#0e7490">🔊 áudio</span>
                @endif
                @if(! $m->vigente())
                  <span class="sgx-chip" style="background:#fee2e2;color:#991b1b">fora do período</span>
                @endif
              </div>
            </div>
            <div class="flex items-center gap-1 flex-none">
              <form method="post" action="{{ route('marketing.signage.midia.ativar', $m) }}">@csrf
                <button class="btn btn-sm" title="{{ $m->ativo ? 'Pausar' : 'Ativar' }}">{{ $m->ativo ? '⏸' : '▶️' }}</button>
              </form>
              <button type="button" class="btn btn-sm" title="Editar" @click="editarMidia({{ $t->id }}, @js($t->orientacao), @js($ed))">✏️</button>
              <form method="post" action="{{ route('marketing.signage.midia.excluir', $m) }}" onsubmit="return confirm('Excluir esta mídia?')">@csrf @method('DELETE')
                <button class="btn btn-sm" title="Excluir" style="color:#b91c1c">🗑</button>
              </form>
            </div>
          </div>
          @empty
          <div class="p-6 text-center text-slate-400 text-sm">Nada programado ainda. Adicione uma foto, um vídeo ou um aviso em texto.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
  @endforeach

  {{-- ===== modal: tela ===== --}}
  <div x-show="mt.open" x-cloak class="fixed inset-0 z-50 bg-slate-900/60 flex items-center justify-center p-3" @keydown.escape.window="mt.open=false">
    <form method="post" :action="mt.action" class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden" @click.outside="mt.open=false">
      @csrf
      <div class="px-5 py-4 text-white font-extrabold text-lg" style="background:linear-gradient(120deg,#6d28d9,#db2777)" x-text="mt.id ? 'Editar tela' : 'Nova tela / ponto'"></div>
      <div class="p-5 space-y-3">
        <div><label class="label">Nome da tela</label><input name="nome" x-model="mt.nome" required maxlength="120" class="input" placeholder="Ex.: TV do Porto de Manaus"></div>
        <div><label class="label">Onde fica (local / ponto)</label><input name="local" x-model="mt.local" maxlength="200" class="input" placeholder="Ex.: Guichê 3 — Porto da Manaus Moderna"></div>
        <div>
          <label class="label">Posição da tela</label>
          <input type="hidden" name="orientacao" :value="mt.orientacao">
          <div class="grid grid-cols-2 gap-2">
            <button type="button" @click="mt.orientacao='horizontal'" :class="mt.orientacao==='horizontal' ? 'border-violet-600 bg-violet-50 text-violet-700' : 'border-slate-200 text-slate-600'" class="rounded-xl border-2 p-3 flex flex-col items-center gap-1.5 font-semibold text-sm">
              <span class="block rounded bg-slate-800" style="width:64px;height:36px"></span>Horizontal (TV deitada)</button>
            <button type="button" @click="mt.orientacao='vertical'" :class="mt.orientacao==='vertical' ? 'border-violet-600 bg-violet-50 text-violet-700' : 'border-slate-200 text-slate-600'" class="rounded-xl border-2 p-3 flex flex-col items-center gap-1.5 font-semibold text-sm">
              <span class="block rounded bg-slate-800" style="width:28px;height:48px"></span>Vertical (totem)</button>
          </div>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="ativo" value="0"><input type="checkbox" name="ativo" value="1" x-model="mt.ativo" class="w-5 h-5 rounded"> Tela ativa (desmarcado = fica só com o nome da empresa e o relógio)</label>
      </div>
      <div class="px-5 py-3 bg-slate-50 flex justify-end gap-2">
        <button type="button" class="btn" @click="mt.open=false">Cancelar</button>
        <button class="btn btn-primary">Salvar tela</button>
      </div>
    </form>
  </div>

  {{-- ===== modal: mídia ===== --}}
  <div x-show="mm.open" x-cloak class="fixed inset-0 z-50 bg-slate-900/60 flex items-start sm:items-center justify-center p-3 overflow-y-auto" @keydown.escape.window="mm.open=false">
    <form method="post" :action="mm.action" enctype="multipart/form-data" class="bg-white rounded-2xl w-full max-w-5xl shadow-2xl overflow-hidden my-4">
      @csrf
      <input type="hidden" name="tipo" :value="mm.tipo">
      <input type="hidden" name="layout" :value="mm.layout">
      <input type="hidden" name="modo" :value="mm.modo">
      <div class="px-5 py-4 text-white flex items-center justify-between" style="background:linear-gradient(120deg,#6d28d9,#db2777)">
        <div class="font-extrabold text-lg" x-text="(mm.id ? 'Editar ' : 'Adicionar ') + (mm.tipo==='texto' ? 'aviso em texto' : 'foto / vídeo')"></div>
        <button type="button" class="text-2xl leading-none opacity-80 hover:opacity-100" @click="mm.open=false">×</button>
      </div>

      <div class="grid lg:grid-cols-[1fr_minmax(0,380px)]">
        <div class="p-5 space-y-4 max-h-[78vh] overflow-y-auto">
          <div class="grid grid-cols-2 gap-2" x-show="!mm.id">
            <button type="button" @click="mm.tipo='midia'; previa()" :class="mm.tipo==='midia' ? 'border-violet-600 bg-violet-50 text-violet-700' : 'border-slate-200 text-slate-600'" class="rounded-xl border-2 p-2.5 font-bold text-sm">🖼️ Foto ou vídeo</button>
            <button type="button" @click="mm.tipo='texto'; previa()" :class="mm.tipo==='texto' ? 'border-violet-600 bg-violet-50 text-violet-700' : 'border-slate-200 text-slate-600'" class="rounded-xl border-2 p-2.5 font-bold text-sm">📝 Aviso em texto</button>
          </div>

          {{-- foto / vídeo --}}
          <div x-show="mm.tipo==='midia'" class="space-y-3">
            <div>
              <label class="label">Arquivo (foto JPG/PNG/WebP/GIF ou vídeo MP4/WebM — até 200 MB)</label>
              <input type="file" name="arquivo" accept="image/*,video/mp4,video/webm,video/quicktime" class="input py-2" @change="arquivo($event)">
              <p class="text-[11px] text-slate-400 mt-1" x-show="mm.id && mm.url">Deixe vazio para manter o arquivo atual.</p>
            </div>
            <div><label class="label">…ou cole o link direto do arquivo (opcional)</label><input type="url" name="arquivo_url" class="input" placeholder="https://…/video.mp4" x-model="mm.arquivo_url" @input.debounce.400ms="previa()"></div>
            <div><label class="label">Nome (só pra você identificar)</label><input name="titulo" x-model="mm.titulo" maxlength="120" class="input" placeholder="Ex.: Promoção de novembro" :disabled="mm.tipo!=='midia'"></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="som" value="1" x-model="mm.som" class="w-5 h-5 rounded"> Vídeo com som (se a TV Box bloquear, toca sem som)</label>
          </div>

          {{-- aviso em texto --}}
          <div x-show="mm.tipo==='texto'" class="space-y-3">
            <div>
              <label class="label">Modelo do aviso</label>
              <div class="grid grid-cols-5 gap-2">
                @foreach($layouts as $n => $nomeLay)
                <div>
                  <div class="sgx-lay" :class="mm.layout==={{ $n }} ? 'sel' : ''" @click="mm.layout={{ $n }}; previa()">
                    <div class="sg-stage" style="position:absolute;inset:0" x-init="$nextTick(() => miniLayout($el, {{ $n }}))"></div>
                  </div>
                  <div class="text-[10px] text-center text-slate-500 mt-1 font-semibold">{{ $nomeLay }}</div>
                </div>
                @endforeach
              </div>
            </div>
            <div><label class="label">Título da faixa (opcional)</label><input name="titulo" x-model="mm.titulo" maxlength="120" class="input" placeholder="Em branco usa o nome do modelo (AVISO, ATENÇÃO…)" @input="previa()" :disabled="mm.tipo!=='texto'"></div>
            <div>
              <label class="label">Texto do aviso</label>
              <textarea name="texto" x-model="mm.texto" rows="4" maxlength="2000" class="input !min-h-[110px] py-2" placeholder="Ex.: Embarque para Tefé às 05:00. Chegue com 1 hora de antecedência." @input="previa()"></textarea>
              <p class="text-[11px] text-slate-400 mt-1">O tamanho da letra se ajusta sozinho para caber na tela, horizontal ou vertical. Enter quebra a linha.</p>
            </div>
          </div>

          {{-- áudio --}}
          <div class="rounded-xl border p-3 bg-slate-50">
            <label class="label">🔊 Áudio junto (opcional — MP3, até 30 MB)</label>
            <input type="file" name="audio" accept="audio/*" class="input py-2">
            <label class="flex items-center gap-2 text-xs mt-1.5" x-show="mm.audio"><input type="checkbox" name="remover_audio" value="1" class="w-4 h-4 rounded"> Remover o áudio atual</label>
          </div>

          {{-- quando exibir --}}
          <div class="rounded-xl border p-3">
            <div class="label !mb-2">Quando exibir</div>
            <div class="grid sm:grid-cols-3 gap-2">
              <button type="button" @click="mm.modo='sequencia'" :class="mm.modo==='sequencia' ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-600'" class="rounded-xl border-2 p-2.5 text-left">
                <div class="font-bold text-sm">🔁 Sequência</div><div class="text-[11px] opacity-80">Fica rodando, um depois do outro</div></button>
              <button type="button" @click="mm.modo='intervalo'" :class="mm.modo==='intervalo' ? 'border-violet-600 bg-violet-50 text-violet-700' : 'border-slate-200 text-slate-600'" class="rounded-xl border-2 p-2.5 text-left">
                <div class="font-bold text-sm">⏱️ A cada X minutos</div><div class="text-[11px] opacity-80">Interrompe a sequência e volta</div></button>
              <button type="button" @click="mm.modo='horario'" :class="mm.modo==='horario' ? 'border-pink-600 bg-pink-50 text-pink-700' : 'border-slate-200 text-slate-600'" class="rounded-xl border-2 p-2.5 text-left">
                <div class="font-bold text-sm">⏰ Horário marcado</div><div class="text-[11px] opacity-80">Entra na hora certa e depois volta</div></button>
            </div>
            <div class="grid sm:grid-cols-2 gap-3 mt-3">
              <div x-show="mm.modo==='intervalo'"><label class="label">A cada quantos minutos</label><input type="number" name="intervalo_min" min="1" max="1440" x-model="mm.intervalo_min" class="input" placeholder="Ex.: 5"></div>
              <div x-show="mm.modo==='horario'" class="sm:col-span-2"><label class="label">Horários (separe por vírgula)</label><input name="horarios" x-model="mm.horarios" class="input" placeholder="Ex.: 08:00, 12:30, 17:45"></div>
              <div>
                <label class="label">Tempo na tela (segundos)</label>
                <input type="number" name="duracao" min="0" max="3600" x-model="mm.duracao" class="input">
                <p class="text-[11px] text-slate-400 mt-1">Vídeo: 0 = toca até o fim.</p>
              </div>
              <div x-show="mm.modo==='sequencia'"><label class="label">Ordem na sequência</label><input type="number" name="ordem" min="0" max="9999" x-model="mm.ordem" class="input"></div>
            </div>
            <div class="mt-3">
              <label class="label">Dias da semana (nenhum marcado = todos os dias)</label>
              <div class="flex flex-wrap gap-1.5">
                @foreach($diasNome as $i => $dn)
                <label class="cursor-pointer"><input type="checkbox" name="dias[]" value="{{ $i }}" x-model="mm.dias" class="peer sr-only"><span class="inline-block rounded-lg border-2 border-slate-200 px-2.5 py-1 text-xs font-bold text-slate-500 peer-checked:border-violet-600 peer-checked:bg-violet-50 peer-checked:text-violet-700">{{ $dn }}</span></label>
                @endforeach
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3 mt-3">
              <div><label class="label">Começa em (opcional)</label><input type="date" name="data_inicio" x-model="mm.data_inicio" class="input"></div>
              <div><label class="label">Termina em (opcional)</label><input type="date" name="data_fim" x-model="mm.data_fim" class="input"></div>
            </div>
            <label class="flex items-center gap-2 text-sm mt-3"><input type="hidden" name="ativo" value="0"><input type="checkbox" name="ativo" value="1" x-model="mm.ativo" class="w-5 h-5 rounded"> Ativa (desmarcado = fica guardada, sem passar)</label>
          </div>
        </div>

        {{-- prévia --}}
        <div class="p-5 bg-slate-900 flex flex-col items-center justify-center gap-3">
          <div class="text-[11px] font-bold uppercase tracking-[.18em] text-slate-400">Prévia na tela <span x-text="mm.orient==='vertical' ? 'vertical' : 'horizontal'"></span></div>
          <div class="sgx-tv w-full" :style="mm.orient==='vertical' ? 'max-width:230px' : 'max-width:360px'">
            <div class="tela" :style="'aspect-ratio:' + (mm.orient==='vertical' ? '9/16' : '16/9')">
              <div class="sg-stage" x-ref="previa" style="position:absolute;inset:0"></div>
            </div>
          </div>
          <div class="text-[11px] text-slate-500 text-center">É assim que aparece na TV. Depois de salvar, a demonstração ao vivo da tela já mostra a programação completa.</div>
        </div>
      </div>

      <div class="px-5 py-3 bg-slate-50 flex justify-end gap-2 border-t">
        <button type="button" class="btn" @click="mm.open=false">Cancelar</button>
        <button class="btn btn-primary" x-text="mm.id ? 'Salvar alterações' : 'Adicionar à tela'"></button>
      </div>
    </form>
  </div>
</div>

<script>
function sgAdmin(){
  var EMPRESA = @json($empresa);
  var BASE_TELA = @json(url('/marketing/signage/tela'));
  return {
    mt: { open:false, id:null, nome:'', local:'', orientacao:'horizontal', ativo:true, action:'' },
    mm: { open:false, id:null, telaId:null, orient:'horizontal', tipo:'midia', titulo:'', texto:'', layout:1, duracao:10, modo:'sequencia', intervalo_min:'', horarios:'', dias:[], data_inicio:'', data_fim:'', som:false, ativo:true, ordem:0, url:'', audio:'', arquivo_url:'', action:'', blob:null, blobTipo:null },
    novaTela(){ this.mt = { open:true, id:null, nome:'', local:'', orientacao:'horizontal', ativo:true, action: BASE_TELA }; },
    editarTela(t){ this.mt = { open:true, id:t.id, nome:t.nome||'', local:t.local||'', orientacao:t.orientacao||'horizontal', ativo:!!t.ativo, action: BASE_TELA + '/' + t.id }; },
    novaMidia(telaId, orient, tipo){
      this.mm = { open:true, id:null, telaId:telaId, orient:orient, tipo:tipo, titulo:'', texto:'', layout:1, duracao:(tipo==='texto'?12:10), modo:'sequencia', intervalo_min:'', horarios:'', dias:[], data_inicio:'', data_fim:'', som:false, ativo:true, ordem:'', url:'', audio:'', arquivo_url:'', action: BASE_TELA + '/' + telaId + '/midia', blob:null, blobTipo:null };
      this.$nextTick(() => this.previa());
    },
    editarMidia(telaId, orient, m){
      this.mm = { open:true, id:m.id, telaId:telaId, orient:orient, tipo:(m.tipo==='texto'?'texto':'midia'), tipoReal:m.tipo, titulo:m.titulo||'', texto:m.texto||'', layout:m.layout||1, duracao:m.duracao, modo:m.modo||'sequencia', intervalo_min:m.intervalo_min||'', horarios:m.horarios||'', dias:(m.dias||[]).map(String), data_inicio:m.data_inicio||'', data_fim:m.data_fim||'', som:!!m.som, ativo:!!m.ativo, ordem:m.ordem, url:m.url||'', audio:m.audio||'', arquivo_url:'', action: BASE_TELA + '/' + telaId + '/midia/' + m.id, blob:null, blobTipo:null };
      this.$nextTick(() => this.previa());
    },
    arquivo(e){
      var f = e.target.files && e.target.files[0];
      if (this.mm.blob) { try { URL.revokeObjectURL(this.mm.blob); } catch(x){} }
      this.mm.blob = f ? URL.createObjectURL(f) : null;
      this.mm.blobTipo = f ? (f.type.indexOf('video') === 0 ? 'video' : 'imagem') : null;
      if (f && this.mm.blobTipo === 'video' && !this.mm.id) this.mm.duracao = 0;
      if (f && !this.mm.titulo) this.mm.titulo = f.name.replace(/\.[^.]+$/, '').slice(0, 120);
      this.previa();
    },
    previa(){
      var el = this.$refs.previa; if (!el || !window.SG) return;
      var it;
      if (this.mm.tipo === 'texto') {
        it = { tipo:'texto', layout:this.mm.layout, titulo:this.mm.titulo, texto:this.mm.texto };
      } else {
        var url = this.mm.blob || this.mm.arquivo_url || this.mm.url || '';
        var tipo = this.mm.blob ? this.mm.blobTipo : (this.mm.arquivo_url ? (/\.(mp4|webm|mov|m4v)(\?.*)?$/i.test(this.mm.arquivo_url) ? 'video' : 'imagem') : (this.mm.tipoReal || 'imagem'));
        it = { tipo: tipo, url: url };
      }
      var self = this;
      requestAnimationFrame(function(){
        var v = SG.desenhar(el, it, { empresa: EMPRESA, previa: true });
        if (v) { v.muted = true; var p = v.play(); if (p && p.catch) p.catch(function(){}); }
      });
    },
    miniLayout(el, n){ SG.desenhar(el, { tipo:'texto', layout:n, titulo:'', texto:'Seu aviso aqui' }, { empresa:'' }); },
    copiar(txt, ev){
      var b = ev.currentTarget, ok = function(){ var o = b.textContent; b.textContent = 'Copiado ✓'; setTimeout(function(){ b.textContent = o; }, 1500); };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(txt).then(ok).catch(function(){});
      else { var i = b.previousElementSibling; if (i && i.select) { i.select(); try { document.execCommand('copy'); ok(); } catch(x){} } }
    }
  };
}
</script>
@endsection
