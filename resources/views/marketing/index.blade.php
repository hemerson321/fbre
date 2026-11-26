@extends('layouts.app')
@section('titulo', 'Áudio indoor')
@section('conteudo')
@php
  $diasLabel = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
@endphp
<div class="space-y-4">

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
    <a href="{{ route('marketing.signage') }}" class="btn btn-ghost btn-sm">🖼️ Ir pra Tela indoor</a>
  </div>

  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 items-start">
  {{-- TERMINAIS --}}
  <div class="card overflow-hidden">
    <div class="p-3 border-b bg-slate-50 flex items-center justify-between gap-2 flex-wrap">
      <div class="font-bold text-sm text-slate-700">🖥 Terminais <span class="text-[11px] font-normal text-slate-400">· onde o áudio toca (tablet/PC com caixa de som)</span></div>
    </div>
    <div class="p-3 space-y-3">
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
  <div class="card overflow-hidden" x-data="{
      modalNovo:false, editId:0,
      baseAudio:@js(url('/marketing/audio')), baseTestar:@js(route('marketing.audio.testar')), csrf:@js(csrf_token()),
      f:{ nome:'', tipo:'tts', texto:'', provedor:'google', voz:'nova', vozGoogle:'pt-BR-Neural2-A', estilo:'' },
      testando:false, testMsg:'', testErro:'', audioEl:null,
      resetF(){ this.f={ nome:'', tipo:'tts', texto:'', provedor:'google', voz:'nova', vozGoogle:'pt-BR-Neural2-A', estilo:'' }; this.testMsg=''; this.testErro=''; },
      abrirNovo(){ this.editId=0; this.resetF(); this.modalNovo=true; },
      fecharNovo(){ this.modalNovo=false; },
      abrirEditar(a){ this.modalNovo=false; this.resetF(); this.f.nome=a.nome||''; this.f.tipo=a.tipo||'tts'; this.f.texto=a.texto||''; this.f.estilo=a.estilo||''; this.f.provedor=a.provedor||'google'; if(this.f.provedor==='google'){ this.f.vozGoogle=a.voz||'pt-BR-Neural2-A'; } else { this.f.voz=a.voz||'nova'; } this.editId=a.id; },
      fecharEditar(){ this.editId=0; this.testMsg=''; this.testErro=''; },
      async testar(){
        if(this.f.tipo!=='tts'){ return; }
        if(!(this.f.texto||'').trim()){ this.testErro='Escreva o texto primeiro.'; this.testMsg=''; return; }
        this.testando=true; this.testMsg=''; this.testErro='';
        try{
          const fd=new FormData();
          fd.append('texto', this.f.texto||'');
          fd.append('provedor', this.f.provedor||'google');
          fd.append('voz', this.f.provedor==='google' ? (this.f.vozGoogle||'') : (this.f.voz||''));
          fd.append('estilo', this.f.estilo||'');
          const r=await fetch(this.baseTestar, {method:'POST', headers:{'X-CSRF-TOKEN':this.csrf,'Accept':'application/json'}, body:fd});
          const d=await r.json();
          if(d.ok){ try{ if(this.audioEl){ this.audioEl.pause(); } this.audioEl=new Audio(d.audio); this.audioEl.play(); }catch(e){} this.testMsg='Tocando pela voz do '+(d.provedor==='google'?'Google':'OpenAI')+' ✓'; }
          else { this.testErro=d.msg||'Não deu pra gerar a voz.'; }
        }catch(e){ this.testErro='Erro: '+e; }
        this.testando=false;
      }
    }">
    <div class="p-3 border-b bg-slate-50 flex items-center justify-between gap-2">
      <div class="font-bold text-sm text-slate-700">🎙 Áudios</div>
      <button type="button" @click="abrirNovo()" class="btn btn-primary btn-sm">+ Novo áudio</button>
    </div>
    <div class="p-3 space-y-2">
      @forelse($audios as $a)
      <div class="rounded-xl border overflow-hidden" :class="editId === {{ $a->id }} ? 'border-amber-300' : 'border-slate-200'">
        <div class="p-3">
          <div class="flex items-center gap-2 flex-wrap">
            <div class="min-w-0 flex-1">
              <div class="font-semibold text-slate-800 truncate">{{ $a->tipo === 'arquivo' ? '📁' : '🗣' }} {{ $a->nome }}
                @unless($a->ativo)<span class="ml-1 rounded-full bg-slate-100 text-slate-400 px-2 py-0.5 text-[10px] font-bold">pausado</span>@endunless
              </div>
              <div class="text-[11px] text-slate-400 truncate">{{ $a->tipo === 'arquivo' ? 'arquivo de áudio' : \Illuminate\Support\Str::limit((string) $a->texto, 70) }}</div>
            </div>
            <button type="button" @click="editId === {{ $a->id }} ? fecharEditar() : abrirEditar(@js(['id'=>$a->id,'nome'=>(string)$a->nome,'tipo'=>(string)$a->tipo,'texto'=>(string)$a->texto,'voz'=>(string)$a->voz,'provedor'=>(string)$a->provedor,'estilo'=>(string)$a->estilo]))" class="btn btn-ghost btn-sm" title="Editar" x-text="editId === {{ $a->id }} ? '✖ Fechar' : '✏️ Editar'"></button>
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
        {{-- expansão: editar ali mesmo --}}
        <div x-show="editId === {{ $a->id }}" x-cloak class="border-t border-amber-200 bg-amber-50/40 p-3">
          <div class="text-[11px] font-bold uppercase tracking-wide text-amber-700 mb-1.5">Editando áudio</div>
          <template x-if="editId === {{ $a->id }}">
            @include('marketing._audioform')
          </template>
        </div>
      </div>
      @empty
      <div class="text-center text-slate-400 text-sm p-4">Nenhum áudio ainda. Clique em <b>+ Novo áudio</b>.</div>
      @endforelse
    </div>

    {{-- MODAL: novo áudio --}}
    <div x-show="modalNovo" x-cloak class="fixed inset-0 z-[60] overflow-y-auto" @keydown.escape.window="fecharNovo()">
      <div class="min-h-full flex items-start justify-center p-4" style="background:rgba(15,23,42,.45)" @click.self="fecharNovo()">
        <div class="card w-full max-w-lg my-8">
          <div class="p-3 border-b bg-slate-50 flex items-center justify-between">
            <div class="font-bold text-sm text-slate-700">🎙 Novo áudio</div>
            <button type="button" @click="fecharNovo()" class="btn btn-ghost btn-sm">✖</button>
          </div>
          <div class="p-3">
            <template x-if="modalNovo">
              @include('marketing._audioform')
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- PROGRAMAÇÃO --}}
  <div class="card overflow-hidden">
    <div class="p-3 border-b bg-slate-50 font-bold text-sm text-slate-700">⏰ Programação <span class="text-[11px] font-normal text-slate-400">· toca sozinho no dia e horário</span></div>
    <div class="p-3 space-y-3">
      <form method="post" action="{{ route('marketing.prog.salvar') }}" class="rounded-xl border border-slate-200 p-3 space-y-2" x-data="{ janela:false }">
        @csrf
        <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Nova programação</div>
        <div><label class="label">Áudio</label>
          <select name="audio_id" class="input" required>
            <option value="">— escolher —</option>
            @foreach($audios as $a)<option value="{{ $a->id }}">{{ $a->nome }}</option>@endforeach
          </select>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div><label class="label" x-text="janela ? 'Hora inicial' : 'Horário'">Horário</label><input type="time" name="hora" class="input" required></div>
          <div><label class="label">Terminal</label>
            <select name="terminal_id" class="input">
              <option value="">Todos</option>
              @foreach($terminais as $t)<option value="{{ $t->id }}">{{ $t->nome }}</option>@endforeach
            </select>
          </div>
        </div>

        <label class="flex items-center gap-2 text-[13px] text-slate-600 cursor-pointer">
          <input type="checkbox" x-model="janela" class="w-4 h-4 rounded"> Repetir dentro de uma janela de horário
        </label>
        <div x-show="janela" x-cloak class="grid grid-cols-2 gap-2">
          <div><label class="label">Hora final</label><input type="time" name="hora_fim" class="input"></div>
          <div><label class="label">A cada (min)</label><input type="number" name="repetir_min" class="input" min="1" max="1440" placeholder="ex.: 30"></div>
        </div>

        <div class="grid grid-cols-2 gap-2">
          <div><label class="label">Tocar quantas vezes</label><input type="number" name="vezes" class="input" min="1" max="50" value="1"></div>
          <div><label class="label">Intervalo (seg)</label><input type="number" name="intervalo_seg" class="input" min="0" max="600" value="5"></div>
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
          <div class="text-center shrink-0 rounded-lg bg-slate-100 px-2.5 py-1">
            <div class="text-base font-extrabold text-slate-800 leading-none">{{ $p->hora }}</div>
            @if($p->hora_fim)<div class="text-[10px] text-slate-400 leading-tight">→ {{ $p->hora_fim }}</div>@endif
          </div>
          <div class="min-w-0 flex-1">
            <div class="font-semibold text-slate-800 truncate">{{ optional($p->audio)->nome ?? '—' }} @unless($p->ativo)<span class="text-[10px] text-slate-400">(pausado)</span>@endunless</div>
            <div class="text-[11px] text-slate-400 truncate">
              {{ optional($p->terminal)->nome ?? 'Todos os terminais' }} ·
              @php $ds = array_map('intval', (array) $p->dias); @endphp
              {{ empty($ds) ? 'todos os dias' : collect($ds)->map(fn ($d) => $diasLabel[$d] ?? '')->implode(', ') }}
            </div>
            <div class="text-[11px] text-slate-400 truncate">
              @if($p->hora_fim && $p->repetir_min)a cada {{ $p->repetir_min }} min · @endif{{ (int) $p->vezes }}x @if((int) $p->vezes > 1 && (int) $p->intervalo_seg > 0)(intervalo {{ $p->intervalo_seg }}s)@endif
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
  </div>{{-- fim das 3 colunas --}}

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

  {{-- INTEGRAÇÕES (VOZ) --}}
  <div class="card overflow-hidden" x-data="{ prov: @js($tts['provider']), testando:false, okmsg:'', erro:'',
    async testar(){ this.testando=true; this.okmsg=''; this.erro='';
      try{
        const r = await fetch(@js(route('marketing.tts.testar')), {method:'POST', headers:{'X-CSRF-TOKEN': @js(csrf_token()), 'Accept':'application/json'}});
        const d = await r.json();
        if(d.ok){ try{ new Audio(d.audio).play(); }catch(e){} this.okmsg = 'Tocou pela voz do '+(d.provedor==='google'?'Google':'OpenAI')+' ✓'; }
        else { this.erro = d.msg || 'Não foi possível gerar a voz.'; }
      }catch(e){ this.erro = 'Erro: '+e; }
      this.testando=false;
    } }">
    <div class="p-3 border-b bg-slate-50 font-bold text-sm text-slate-700">🔌 Integrações · voz do áudio falado</div>
    <form method="post" action="{{ route('marketing.tts.salvar') }}" class="p-3 space-y-3">
      @csrf
      <div>
        <label class="label">Provedor de voz</label>
        <div class="grid grid-cols-2 gap-2 max-w-md">
          <button type="button" @click="prov='openai'" :class="prov==='openai' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-300'" class="rounded-xl border py-2 text-sm font-bold">🤖 OpenAI</button>
          <button type="button" @click="prov='google'" :class="prov==='google' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-300'" class="rounded-xl border py-2 text-sm font-bold">🟦 Google · Chirp 3 HD</button>
        </div>
        <input type="hidden" name="tts_provider" :value="prov">
      </div>

      <div><label class="label">Estilo / como se comportar <span class="text-slate-400 font-normal">(ex.: voz de aeromoça, calma e clara)</span></label>
        <textarea name="tts_estilo" class="input" rows="2" placeholder="Narre com voz feminina, calma, clara e acolhedora, sotaque brasileiro neutro.">{{ $tts['estilo'] }}</textarea>
        <p class="text-[11px] text-slate-400 mt-1">Melhor aproveitado na OpenAI (gpt-4o-mini-tts). No Google, a voz escolhida já define o tom.</p>
      </div>

      <div x-show="prov==='openai'" x-cloak class="space-y-2 rounded-xl border border-slate-200 p-3">
        <div class="text-[11px] font-bold uppercase text-slate-400">OpenAI</div>
        <div><label class="label">Voz</label>
          <select name="tts_openai_voz" class="input">
            @foreach(['nova'=>'👩 Nova — moça, clara','shimmer'=>'👩 Shimmer — moça, suave','coral'=>'👩 Coral — moça, simpática','alloy'=>'🧑 Alloy — neutra','onyx'=>'👨 Onyx — homem','echo'=>'👨 Echo — homem'] as $vk=>$vl)
              <option value="{{ $vk }}" @selected($tts['openai_voz']===$vk)>{{ $vl }}</option>
            @endforeach
          </select>
        </div>
        <p class="text-[11px] text-slate-400">Usa a chave OpenAI já configurada em <a href="{{ route('admin.llm') }}" class="underline">Provedores de IA</a> (a mesma dos Agentes).</p>
      </div>

      <div x-show="prov==='google'" x-cloak class="space-y-3 rounded-xl border border-slate-200 p-3">
        <div class="text-[11px] font-bold uppercase text-slate-400">Google Cloud Text-to-Speech</div>

        {{-- Opção A: conta de serviço (recomendada, funciona com Chirp 3 HD) --}}
        <div class="rounded-lg border border-emerald-200 bg-emerald-50/40 p-2.5 space-y-1.5">
          <div class="text-[12px] font-bold text-emerald-800">✅ Conta de serviço (JSON) — recomendado</div>
          <p class="text-[11px] text-slate-500 leading-snug">É o único jeito que funciona com as vozes <b>Chirp 3 HD</b> (as mais naturais). A chave de API comum dá erro 401 nelas.</p>
          <label class="label">JSON da conta de serviço {!! $tts['google_tem_sa'] ? '<span class="text-emerald-600">✓ salvo</span>' : '' !!}</label>
          <textarea name="tts_google_sa" rows="3" autocomplete="off" class="input font-mono !text-[11px]" placeholder="{{ $tts['google_tem_sa'] ? '•••••• (deixe em branco pra manter o salvo)' : 'cole aqui todo o conteúdo do arquivo .json baixado do Google Cloud' }}"></textarea>
          @if($tts['google_tem_sa'])
          <label class="flex items-center gap-1.5 text-[11px] text-rose-600 cursor-pointer"><input type="checkbox" name="tts_google_sa_limpar" value="1" class="w-3.5 h-3.5"> remover a conta de serviço salva</label>
          @endif
          <details class="text-[11px] text-slate-500">
            <summary class="cursor-pointer font-semibold text-slate-600">Como pegar esse JSON (passo a passo)</summary>
            <ol class="list-decimal ml-4 mt-1 space-y-0.5">
              <li>Abra o <a href="https://console.cloud.google.com/" target="_blank" class="underline">console.cloud.google.com</a> e crie/escolha um projeto (com faturamento ativo).</li>
              <li>Ative a <b>Cloud Text-to-Speech API</b> em <i>APIs e serviços → Ativar APIs e serviços</i>.</li>
              <li>Vá em <b>IAM e administrador → Contas de serviço → Criar conta de serviço</b> (papel: <i>Cloud Text-to-Speech API User</i>, ou Editor).</li>
              <li>Abra a conta criada → aba <b>Chaves → Adicionar chave → Criar nova chave → JSON</b>. Baixa um arquivo <span class="font-mono">.json</span>.</li>
              <li>Abra o arquivo, copie <b>todo</b> o conteúdo e cole no campo acima. Pronto.</li>
            </ol>
          </details>
        </div>

        {{-- Opção B: chave de API (só Neural2/Wavenet) --}}
        <div><label class="label">… ou Chave de API {!! $tts['google_tem_chave'] ? '<span class="text-emerald-600">✓ salva</span>' : '' !!} <span class="text-slate-400 font-normal">· só vozes Neural2/Wavenet</span></label>
          <input type="password" name="tts_google_key" class="input" autocomplete="off" placeholder="{{ $tts['google_tem_chave'] ? '•••••• (deixe em branco pra manter)' : 'cole a chave do Google Cloud' }}">
        </div>

        <div><label class="label">Voz</label>
          <select name="tts_google_voz" class="input">
            <optgroup label="Chirp 3 HD — mais natural (precisa de conta de serviço)">
              @foreach(['pt-BR-Chirp3-HD-Aoede'=>'👩 Aoede — moça','pt-BR-Chirp3-HD-Kore'=>'👩 Kore — moça','pt-BR-Chirp3-HD-Leda'=>'👩 Leda — moça','pt-BR-Chirp3-HD-Zephyr'=>'👩 Zephyr — moça','pt-BR-Chirp3-HD-Sulafat'=>'👩 Sulafat — moça','pt-BR-Chirp3-HD-Charon'=>'👨 Charon — homem','pt-BR-Chirp3-HD-Puck'=>'👨 Puck — homem'] as $vk=>$vl)
                <option value="{{ $vk }}" @selected($tts['google_voz']===$vk)>{{ $vl }}</option>
              @endforeach
            </optgroup>
            <optgroup label="Neural2 / Wavenet — funcionam com chave de API">
              @foreach(['pt-BR-Neural2-A'=>'👩 Neural2 A — moça','pt-BR-Neural2-C'=>'👩 Neural2 C — moça','pt-BR-Wavenet-A'=>'👩 Wavenet A — moça','pt-BR-Wavenet-C'=>'👩 Wavenet C — moça','pt-BR-Neural2-B'=>'👨 Neural2 B — homem','pt-BR-Wavenet-B'=>'👨 Wavenet B — homem'] as $vk=>$vl)
                <option value="{{ $vk }}" @selected($tts['google_voz']===$vk)>{{ $vl }}</option>
              @endforeach
            </optgroup>
          </select>
          <p class="text-[11px] text-slate-400 mt-1">Chirp 3 HD só funciona com a conta de serviço acima. Com chave de API, escolha Neural2/Wavenet.</p>
        </div>
        <div><label class="label">Ritmo</label>
          <select name="tts_google_ritmo" class="input max-w-[200px]">
            <option value="1.0" @selected(abs($tts['google_ritmo']-1.0)<0.01)>Normal</option>
            <option value="0.9" @selected(abs($tts['google_ritmo']-0.9)<0.01)>Calmo</option>
            <option value="0.85" @selected(abs($tts['google_ritmo']-0.85)<0.01)>Bem pausado</option>
          </select>
        </div>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        <button class="btn btn-primary btn-sm">Salvar integrações</button>
        <button type="button" @click="testar()" :disabled="testando" class="btn btn-ghost btn-sm" x-text="testando ? 'Testando…' : '🔊 Testar voz'"></button>
      </div>
      <p class="text-[12px] text-emerald-700 font-semibold" x-show="okmsg" x-text="okmsg" x-cloak></p>
      <p class="text-[12px] text-rose-600 font-semibold" x-show="erro" x-cloak><span x-text="erro"></span></p>
      <p class="text-[11px] text-slate-400">Salve antes de testar. O teste usa o provedor e a voz salvos aqui.</p>
    </form>
  </div>

</div>
@endsection
