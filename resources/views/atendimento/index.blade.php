@extends('layouts.app')
@section('titulo','Atendimento')
@section('conteudo')
@php
  $sel = $conversa;
  $respostas = $respostas ?? collect();
  $grupos = $respostas->groupBy(fn($r) => trim((string) $r->categoria) ?: 'Geral');
  $cats = $grupos->keys()->sort()->values();
@endphp

<style>
  .atd{--atd-h:calc(100vh - 8.5rem)}
  @media(max-width:1023px){.atd{--atd-h:calc(100vh - 7rem)}}
  .atd-wall{background-color:#eae6df;background-image:radial-gradient(circle at 20% 15%,rgba(255,255,255,.55) 0,transparent 40%),radial-gradient(circle at 80% 70%,rgba(255,255,255,.4) 0,transparent 45%),url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='52' height='52' viewBox='0 0 52 52'%3E%3Cpath fill='%23d6cfc4' fill-opacity='.35' d='M26 6c2 0 3 1 3 3s-1 3-3 3-3-1-3-3 1-3 3-3zM10 24c2 0 3 1 3 3s-1 3-3 3-3-1-3-3 1-3 3-3zm32 0c2 0 3 1 3 3s-1 3-3 3-3-1-3-3 1-3 3-3zM26 42c2 0 3 1 3 3s-1 3-3 3-3-1-3-3 1-3 3-3z'/%3E%3C/svg%3E")}
  .atd-scroll::-webkit-scrollbar{width:7px;height:7px}
  .atd-scroll::-webkit-scrollbar-thumb{background:rgba(100,116,139,.35);border-radius:9px}
  .atd-scroll::-webkit-scrollbar-track{background:transparent}
  .atd-bub{box-shadow:0 1px 1px rgba(0,0,0,.08)}
  .atd-tab{transition:all .15s}
  .atd-tab[data-on="1"]{color:#fff}
  .atd-cat{transition:all .12s;cursor:pointer}
  .atd-card{transition:transform .1s, box-shadow .1s}
  .atd-card:hover{transform:translateY(-1px);box-shadow:0 6px 18px -8px rgba(2,6,23,.25)}
  .atd-drop.drag{border-color:var(--cor1);background:color-mix(in srgb,var(--cor1) 8%,white)}
  [x-cloak]{display:none!important}
</style>

<div class="atd h-[var(--atd-h)] flex gap-3">

  {{-- ================= INBOX (esquerda) ================= --}}
  <aside class="w-full lg:w-[320px] shrink-0 flex flex-col min-h-0 {{ $sel ? 'hidden lg:flex' : 'flex' }}">
    <div class="flex items-center justify-between mb-2">
      <h1 class="text-lg font-extrabold flex items-center gap-2">💬 Atendimento</h1>
      <div class="flex items-center gap-1.5">
        @if($aguardando)<span class="chip bg-amber-100 text-amber-700 text-xs font-bold">{{ $aguardando }} aguard.</span>@endif
        <span id="badgePrecisa" class="chip bg-rose-100 text-rose-700 text-xs font-bold" style="{{ ($precisaHumano ?? 0) ? '' : 'display:none' }}">🔔 {{ $precisaHumano ?? 0 }}</span>
      </div>
    </div>
    <div class="flex gap-1.5 mb-2 text-xs">
      <a href="{{ route('atendimento.kanban') }}" class="flex-1 text-center rounded-lg border border-slate-200 bg-white px-2 py-1.5 font-bold text-slate-700 hover:bg-slate-50">📋 CRM</a>
      <a href="{{ route('atendimento.ferramentas') }}" class="flex-1 text-center rounded-lg border border-slate-200 bg-white px-2 py-1.5 font-bold text-slate-700 hover:bg-slate-50">🧰 Respostas · Etiquetas</a>
    </div>
    <div class="flex gap-1 mb-2 text-xs">
      @foreach(['ativas'=>'Ativas','aguardando'=>'Aguardando','humano'=>'Comigo','fechadas'=>'Fechadas'] as $k=>$l)
        <a href="{{ route('atendimento.index',['status'=>$k]) }}" class="px-2.5 py-1 rounded-full font-semibold {{ $filtro===$k ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600' }}">{{ $l }}</a>
      @endforeach
    </div>
    <div id="alertaAtend" class="mb-2 rounded-xl border border-rose-300 bg-rose-50 p-2" style="{{ ($precisaHumano ?? 0) ? '' : 'display:none' }}">
      <div class="text-xs font-bold text-rose-700 mb-1">🔔 <span data-n>{{ $precisaHumano ?? 0 }}</span> cliente(s) esperando atendente</div>
      <div data-lista class="space-y-0.5"></div>
    </div>
    <div id="listaConversas" class="card flex-1 overflow-y-auto atd-scroll divide-y">
      @include('atendimento._lista', ['conversas' => $conversas, 'sel' => $sel])
    </div>
  </aside>

  {{-- ================= CONVERSA (centro) + PAINEL (direita) ================= --}}
  @if($sel)
  <div x-data="atd({{ $sel->id }}, {{ optional($sel->mensagens->last())->id ?? 0 }}, @js($vendaResumo))"
       class="flex-1 min-w-0 flex gap-3 {{ $sel ? 'flex' : 'hidden lg:flex' }}">

    {{-- ---------- CENTRO ---------- --}}
    <section class="flex-1 min-w-0 flex flex-col card overflow-hidden">
      {{-- header --}}
      @php $etqSel = array_map('intval', (array) $sel->etiquetas); @endphp
      <header class="flex items-center gap-3 px-3 py-2.5 border-b" style="background:linear-gradient(90deg,color-mix(in srgb,var(--cor1) 10%,white),#fff)">
        <a href="{{ route('atendimento.index') }}" class="lg:hidden text-slate-500 text-xl leading-none">←</a>
        <div class="w-10 h-10 rounded-full flex items-center justify-center font-extrabold text-white shrink-0" style="background:linear-gradient(135deg,var(--cor1),var(--cor2))">{{ mb_strtoupper(mb_substr($sel->nomeExibicao(),0,1)) }}</div>
        <div class="min-w-0 flex-1">
          <div class="font-extrabold truncate leading-tight">{{ $sel->nomeExibicao() }}</div>
          <div class="text-xs text-slate-500 truncate">{{ $sel->cliente_telefone }} · <span class="font-semibold text-{{ $sel->cor() }}-600">{{ $sel->statusLabel() }}</span>{{ $sel->atendente ? ' · '.$sel->atendente->name : '' }}</div>
        </div>
        <button type="button" @click="painel=!painel" class="lg:hidden btn btn-ghost btn-sm shrink-0" title="Ferramentas">🧰</button>
        @if($sel->status!=='humano')
        <form method="post" action="{{ route('atendimento.assumir',$sel) }}" class="shrink-0">@csrf<button class="btn btn-primary btn-sm" style="background:var(--cor1)">Assumir</button></form>
        @endif
        <form method="post" action="{{ route('atendimento.reiniciar',$sel) }}" onsubmit="return confirm('Reiniciar a sessão? O agente atende como novo.')" class="shrink-0">@csrf<button class="btn btn-ghost btn-sm" title="Agente esquece o histórico">🔄</button></form>
        <form method="post" action="{{ route('atendimento.fechar',$sel) }}" onsubmit="return confirm('Finalizar o pedido? A conversa é encerrada.')" class="shrink-0">@csrf<button class="btn btn-primary btn-sm" style="background:var(--cor2)">✅ Finalizar</button></form>
      </header>

      {{-- venda ao vivo (faixa fina) --}}
      <div x-show="venda && (venda.origem || venda.destino || (venda.poltronas&&venda.poltronas.length))" x-cloak class="px-3 py-1.5 border-b bg-cyan-50/80 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-slate-700">
        <span class="text-[11px] font-bold text-cyan-800">🧾 Venda:</span>
        <span x-show="venda&&(venda.origem||venda.destino)" class="font-semibold" x-text="(venda.origem||'?')+' → '+(venda.destino||'?')"></span>
        <span x-show="venda&&venda.data">📅 <span x-text="venda.data"></span></span>
        <span x-show="venda&&venda.passageiros">👤 <span x-text="venda.passageiros+'x'"></span></span>
        <span x-show="venda&&venda.pref">🪑 <span x-text="venda.pref"></span></span>
        <span x-show="venda&&venda.poltronas&&venda.poltronas.length" class="font-bold text-cyan-700">💺 <span x-text="(venda.poltronas||[]).join(', ')"></span></span>
        <button type="button" @click="tab='vender'; painel=true" class="ml-auto text-cyan-700 font-bold underline">abrir venda →</button>
      </div>

      {{-- mensagens --}}
      <div id="msgs" x-ref="msgs" class="flex-1 overflow-y-auto atd-scroll px-3 py-4 space-y-1.5 atd-wall">
        @foreach($sel->mensagens as $m)
          @php $mt = (array) ($m->meta ?? []); $arq = ! empty($mt['arquivo']) ? asset($mt['arquivo']) : null; $mm = (string) ($mt['mime'] ?? ''); @endphp
          <div class="flex {{ $m->direcao==='entrada' ? 'justify-start' : 'justify-end' }}">
            <div class="atd-bub max-w-[78%] rounded-2xl px-3 py-2 text-sm {{ $m->direcao==='entrada' ? 'bg-white' : ($m->origem==='ia' ? 'bg-cyan-600 text-white' : 'text-white') }}" @if($m->direcao!=='entrada' && $m->origem!=='ia') style="background:#128c4a" @endif>
              @if($m->direcao!=='entrada')<div class="text-[10px] opacity-80 font-semibold mb-0.5">{{ $m->origem==='ia' ? '🤖 Agente' : ($m->autor?->name ?? 'Atendente') }}</div>@endif
              @if($arq)
                @if(str_starts_with($mm, 'image/'))
                  <a href="{{ $arq }}" target="_blank"><img src="{{ $arq }}" class="rounded-lg max-h-56 mb-1" loading="lazy" alt=""></a>
                @elseif(str_starts_with($mm, 'video/'))
                  <video src="{{ $arq }}" controls preload="metadata" class="rounded-lg max-h-56 mb-1"></video>
                @elseif(str_starts_with($mm, 'audio/'))
                  <audio src="{{ $arq }}" controls class="mb-1 max-w-full"></audio>
                @else
                  <a href="{{ $arq }}" target="_blank" class="underline font-semibold block mb-1">📎 {{ $mt['nome'] ?? 'arquivo' }}</a>
                @endif
              @endif
              @if(! empty($mt['erro']))<div class="text-[10px] font-bold bg-white/90 text-rose-700 rounded px-1 mb-1">⚠ não enviou: {{ $mt['erro'] }}</div>@endif
              <div class="whitespace-pre-wrap break-words">{{ $m->texto }}</div>
              <div class="text-[10px] opacity-60 text-right mt-0.5">{{ $m->created_at?->format('H:i') }}</div>
            </div>
          </div>
        @endforeach
      </div>

      {{-- composer --}}
      <div class="border-t bg-white p-2.5">
        <div x-show="arqNome" x-cloak class="flex items-center gap-2 mb-1.5 text-xs bg-slate-100 rounded-lg px-2 py-1">
          <span class="truncate flex-1">📎 <span x-text="arqNome"></span> <span class="text-slate-400">— o texto vai como legenda</span></span>
          <button type="button" @click="limparArq()" class="text-rose-600 font-bold">✕</button>
        </div>
        <form @submit.prevent="enviar()" class="flex items-end gap-1.5">
          <div class="flex items-center">
            <label class="btn btn-ghost btn-sm cursor-pointer !px-2" title="Foto"><span>📷</span><input type="file" x-ref="img" accept="image/*" class="hidden" @change="pegarArq($event)"></label>
            <label class="btn btn-ghost btn-sm cursor-pointer !px-2" title="Vídeo"><span>🎥</span><input type="file" x-ref="vid" accept="video/*" class="hidden" @change="pegarArq($event)"></label>
            <label class="btn btn-ghost btn-sm cursor-pointer !px-2" title="Arquivo"><span>📎</span><input type="file" x-ref="doc" class="hidden" @change="pegarArq($event)"></label>
            <button type="button" class="btn btn-ghost btn-sm !px-2" title="Respostas rápidas" @click="tab='rapidas'; painel=true">⚡</button>
          </div>
          <textarea x-ref="txt" x-model="texto" rows="1" @keydown.enter.exact.prevent="enviar()" @input="autoGrow()" class="input flex-1 !min-h-[42px] max-h-32 resize-none py-2.5" placeholder="Escreva uma mensagem…  (Enter envia)"></textarea>
          <button type="submit" :disabled="sending" class="btn btn-primary shrink-0 !px-4" style="background:var(--cor1)"><span x-show="!sending">Enviar</span><span x-show="sending" x-cloak>…</span></button>
        </form>
      </div>
    </section>

    {{-- ---------- PAINEL DIREITO (abas) ---------- --}}
    <aside class="w-[370px] shrink-0 flex-col card overflow-hidden hidden lg:flex"
           :class="painel ? '!flex fixed lg:static inset-0 z-40 lg:z-auto w-full lg:w-[370px] rounded-none lg:rounded-2xl' : ''">
      {{-- abas --}}
      <div class="flex items-center gap-1 p-1.5 border-b bg-slate-50">
        <button type="button" class="atd-tab flex-1 rounded-lg px-2 py-2 text-sm font-bold text-slate-600" :data-on="tab==='rapidas'?1:0" :style="tab==='rapidas' ? 'background:var(--cor1)' : ''" @click="tab='rapidas'">⚡ Rápidas</button>
        <button type="button" class="atd-tab flex-1 rounded-lg px-2 py-2 text-sm font-bold text-slate-600" :data-on="tab==='vender'?1:0" :style="tab==='vender' ? 'background:var(--cor2)' : ''" @click="tab='vender'; abrirVenda()">🎫 Vender</button>
        <button type="button" class="atd-tab flex-1 rounded-lg px-2 py-2 text-sm font-bold text-slate-600" :data-on="tab==='cliente'?1:0" :style="tab==='cliente' ? 'background:#334155' : ''" @click="tab='cliente'">👤 Cliente</button>
        <button type="button" @click="painel=false" class="lg:hidden btn btn-ghost btn-sm">✕</button>
      </div>

      {{-- ===== ABA RÁPIDAS ===== --}}
      <div x-show="tab==='rapidas'" class="flex-1 min-h-0 flex flex-col">
        {{-- envio rápido de mídia --}}
        <label class="atd-drop m-2.5 mb-1.5 flex items-center gap-2 rounded-xl border-2 border-dashed border-slate-300 px-3 py-2.5 cursor-pointer text-sm text-slate-500 hover:bg-slate-50"
               @dragover.prevent="$el.classList.add('drag')" @dragleave="$el.classList.remove('drag')" @drop.prevent="soltarArq($event)">
          <span class="text-xl">📷</span>
          <span class="flex-1"><b class="text-slate-700">Enviar imagem ou vídeo</b><br>arraste aqui ou toque para escolher</span>
          <input type="file" accept="image/*,video/*" class="hidden" @change="pegarArqEnviar($event)">
        </label>
        {{-- busca --}}
        <div class="px-2.5 pb-1.5">
          <input x-model="busca" class="input !min-h-[38px] py-1.5 text-sm" placeholder="🔎 Buscar resposta…">
        </div>
        {{-- categorias --}}
        <div class="px-2.5 pb-2 flex flex-wrap gap-1">
          <span class="atd-cat rounded-full px-2.5 py-1 text-xs font-bold" :class="catSel==='Todas' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600'" @click="catSel='Todas'">Todas</span>
          @foreach($cats as $c)
            <span class="atd-cat rounded-full px-2.5 py-1 text-xs font-bold" :class="catSel===@js($c) ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600'" @click="catSel=@js($c)">{{ $c }}</span>
          @endforeach
        </div>
        {{-- lista de respostas --}}
        <div class="flex-1 overflow-y-auto atd-scroll px-2.5 pb-3 space-y-1.5">
          @forelse($respostas as $rz)
            @php
              $rp = (array) $rz->passos; $um = count($rp) === 1 && empty($rp[0]['arquivo']);
              $cat = trim((string) $rz->categoria) ?: 'Geral';
              $prev = \Illuminate\Support\Str::limit((string) ($rp[0]['texto'] ?? ''), 80) ?: ('📎 '.($rp[0]['nome'] ?? 'arquivo'));
              $temMidia = !empty($rp[0]['arquivo']);
              $busStr = \Illuminate\Support\Str::lower($rz->titulo.' '.$cat.' '.($rp[0]['texto'] ?? ''));
            @endphp
            <div class="atd-card rounded-xl border border-slate-200 bg-white p-2.5"
                 x-show="(catSel==='Todas' || catSel===@js($cat)) && (busca==='' || @js($busStr).includes(busca.toLowerCase()))">
              <div class="flex items-start gap-2">
                <div class="min-w-0 flex-1">
                  <div class="font-bold text-sm truncate">{{ count($rp) > 1 ? '🔀' : '⚡' }} {{ $rz->titulo }}
                    @if(count($rp) > 1)<span class="chip text-[9px] bg-violet-100 text-violet-700">funil {{ count($rp) }}</span>@endif
                    @if($temMidia)<span class="chip text-[9px] bg-sky-100 text-sky-700">📎</span>@endif
                  </div>
                  <div class="text-xs text-slate-500 line-clamp-2">{{ $prev }}</div>
                </div>
              </div>
              <div class="flex gap-1.5 mt-2">
                <button type="button" class="btn btn-primary btn-sm flex-1" style="background:var(--cor1)" @click="enviarResposta({{ $rz->id }}, $el)">Enviar</button>
                @if($um)
                <button type="button" class="btn btn-sm" title="Editar antes de enviar" @click="texto=@js((string) ($rp[0]['texto'] ?? '')); tab='rapidas'; painel=false; $nextTick(()=>$refs.txt&&$refs.txt.focus())">✎</button>
                @endif
              </div>
            </div>
          @empty
            <div class="p-5 text-center text-sm text-slate-500">Nenhuma resposta ainda.<br><a href="{{ route('atendimento.ferramentas') }}" class="underline font-semibold">Criar respostas e funis</a></div>
          @endforelse
          <a href="{{ route('atendimento.ferramentas') }}" class="block text-center text-xs text-slate-400 underline pt-1">＋ gerenciar respostas e categorias</a>
        </div>
      </div>

      {{-- ===== ABA VENDER ===== --}}
      <div x-show="tab==='vender'" x-cloak class="flex-1 min-h-0 flex flex-col">
        <div class="flex items-center justify-between px-3 py-2 border-b bg-slate-50">
          <div class="text-sm font-bold">🎫 Vender para {{ $sel->nomeExibicao() }}</div>
          <a :href="vendaUrl()" target="_blank" class="text-xs text-slate-500 underline">tela cheia ↗</a>
        </div>
        <div class="flex-1 min-h-0 relative">
          <template x-if="vendaSrc">
            <iframe :src="vendaSrc" class="absolute inset-0 w-full h-full border-0"></iframe>
          </template>
          <div x-show="!vendaSrc" class="absolute inset-0 flex items-center justify-center text-slate-400 text-sm">Carregando venda…</div>
        </div>
      </div>

      {{-- ===== ABA CLIENTE ===== --}}
      <div x-show="tab==='cliente'" x-cloak class="flex-1 min-h-0 overflow-y-auto atd-scroll p-3 space-y-3">
        {{-- contexto da venda --}}
        @if(!empty($sel->contexto['pref_poltrona']) || !empty($sel->contexto['poltronas_agente']))
        <div class="rounded-xl bg-sky-50 border border-sky-200 p-2.5 text-xs text-sky-800">
          🪑 Prefere <b>{{ ucfirst($sel->contexto['pref_poltrona'] ?? '—') }}</b>@if(!empty($sel->contexto['poltronas_agente'])) · poltrona(s) <b>{{ implode(', ', (array) $sel->contexto['poltronas_agente']) }}</b>@endif
        </div>
        @endif
        {{-- funil --}}
        <div>
          <div class="label mb-1">Etapa do funil</div>
          <form method="post" action="{{ route('atendimento.funil', $sel) }}">@csrf
            <select name="funil" onchange="this.form.submit()" class="input !min-h-[40px] py-2 font-bold text-indigo-700">
              @foreach(\App\Models\Conversa::FUNIL as $fk => $fn)
                <option value="{{ $fk }}" @selected(($sel->funil ?: 'novo') === $fk)>{{ $fn }}</option>
              @endforeach
            </select>
          </form>
        </div>
        {{-- etiquetas --}}
        <div>
          <div class="label mb-1">Etiquetas</div>
          <div class="flex flex-wrap gap-1.5">
            @foreach(($etiquetasTodas ?? collect()) as $e)
              @php $on = in_array((int) $e->id, $etqSel, true); @endphp
              <form method="post" action="{{ route('atendimento.etiqueta.toggle', $sel) }}">@csrf
                <input type="hidden" name="etiqueta_id" value="{{ $e->id }}">
                <button class="rounded-full px-2.5 py-1 text-xs font-bold border" title="{{ $on ? 'Tirar' : 'Marcar' }}"
                  style="{{ $on ? 'background:'.$e->cor.';color:#fff;border-color:'.$e->cor : 'background:#fff;color:'.$e->cor.';border-color:color-mix(in srgb, '.$e->cor.' 35%, white)' }}">{{ $on ? '✓ ' : '' }}{{ $e->nome }}</button>
              </form>
            @endforeach
            @if(($etiquetasTodas ?? collect())->isEmpty())
              <a href="{{ route('atendimento.ferramentas') }}#etiquetas" class="text-slate-400 underline text-xs">criar etiquetas</a>
            @endif
          </div>
        </div>
        {{-- agendadas --}}
        @if(($agendadasConv ?? collect())->isNotEmpty())
        <div>
          <div class="label mb-1">Agendadas</div>
          <div class="space-y-1">
            @foreach($agendadasConv as $ag)
              <div class="flex items-center gap-2 text-xs bg-amber-50 border border-amber-200 rounded-lg px-2 py-1.5">
                <span class="font-bold text-amber-800 shrink-0">🕒 {{ $ag->enviar_em->format('d/m H:i') }}</span>
                <span class="truncate flex-1 text-slate-600">{{ \Illuminate\Support\Str::limit($ag->texto, 40) ?: '📎 arquivo' }}</span>
                <form method="post" action="{{ route('atendimento.agendada.cancelar', $ag) }}">@csrf<button class="text-rose-600 font-bold">✕</button></form>
              </div>
            @endforeach
          </div>
        </div>
        @endif
        {{-- agendar nova --}}
        <details class="rounded-xl border border-slate-200 bg-white">
          <summary class="cursor-pointer px-3 py-2 text-sm font-bold text-slate-700">🕒 Agendar mensagem</summary>
          <form method="post" action="{{ route('atendimento.agendar', $sel) }}" enctype="multipart/form-data" class="p-3 pt-1 space-y-2">@csrf
            <textarea name="texto" rows="2" maxlength="4000" class="input !min-h-[60px] py-2 text-sm" placeholder="Ex.: Bom dia! Seu embarque é amanhã às 05:00."></textarea>
            <div><label class="label">Enviar em</label><input type="datetime-local" name="enviar_em" required class="input !min-h-[40px]" value="{{ now()->addHour()->format('Y-m-d\TH:i') }}"></div>
            <div><label class="label">Arquivo (opcional)</label><input type="file" name="arquivo" class="input py-1.5 text-sm"></div>
            <button class="btn btn-sm btn-primary w-full" style="background:var(--cor1)">Agendar</button>
          </form>
        </details>
      </div>
    </aside>
  </div>
  @else
  <div class="flex-1 hidden lg:flex items-center justify-center text-slate-400 text-sm card">
    <div class="text-center"><div class="text-5xl mb-3">💬</div>Selecione uma conversa para começar</div>
  </div>
  @endif
</div>

<script>
function atd(id, ultimoId, vendaIni){
  return {
    ultimo: ultimoId,
    venda: vendaIni || null,
    texto: '',
    arqFile: null,
    arqNome: '',
    sending: false,
    tab: 'rapidas',
    painel: false,
    catSel: 'Todas',
    busca: '',
    vendaSrc: '',

    init(){ this.rolar(); this.timer = setInterval(()=>this.puxar(), 4000); },

    autoGrow(){ var t=this.$refs.txt; if(!t) return; t.style.height='auto'; t.style.height=Math.min(128, t.scrollHeight)+'px'; },
    rolar(){ this.$nextTick(()=>{ var m=this.$refs.msgs; if(m) m.scrollTop=m.scrollHeight; }); },

    pegarArq(ev){ var f=(ev.target.files||[])[0]; if(f){ this.arqFile=f; this.arqNome=f.name; } ev.target.value=''; },
    pegarArqEnviar(ev){ var f=(ev.target.files||[])[0]; if(f){ this.arqFile=f; this.arqNome=f.name; this.enviar(); } ev.target.value=''; },
    soltarArq(ev){ ev.currentTarget.classList.remove('drag'); var f=(ev.dataTransfer.files||[])[0]; if(f){ this.arqFile=f; this.arqNome=f.name; this.enviar(); } },
    limparArq(){ this.arqFile=null; this.arqNome=''; },

    async enviar(){
      if(this.sending) return;
      var txt=(this.texto||'').trim();
      if(!txt && !this.arqFile) return;
      this.sending=true;
      var fd=new FormData();
      fd.append('_token','{{ csrf_token() }}');
      fd.append('texto', txt);
      if(this.arqFile) fd.append('arquivo', this.arqFile);
      try{
        var r=await fetch('{{ route('atendimento.responder',$sel) }}', {method:'POST', headers:{'X-Requested-With':'fetch','Accept':'application/json'}, body:fd});
        var d=await r.json().catch(()=>({ok:r.ok}));
        if(d && d.ok===false){ alert(d.erro||'Não enviou.'); }
        else { this.texto=''; this.limparArq(); this.autoGrow(); await this.puxar(); }
      }catch(e){ alert('Falha de conexão ao enviar.'); }
      this.sending=false;
    },

    async enviarResposta(rid, el){
      if(el){ el.disabled=true; el.textContent='…'; }
      try{
        var r=await fetch('{{ url('/passagens/atendimento') }}/'+id+'/usar/'+rid, {method:'POST', headers:{'X-Requested-With':'fetch','Accept':'application/json','Content-Type':'application/x-www-form-urlencoded'}, body:'_token={{ csrf_token() }}'});
        var d=await r.json().catch(()=>({ok:r.ok}));
        if(d && d.ok===false) alert(d.erro||'Não enviou.');
        else { await this.puxar(); this.painel=false; }
      }catch(e){ alert('Falha de conexão.'); }
      if(el){ el.disabled=false; el.textContent='Enviar'; }
    },

    vendaUrl(){ var v=this.venda||{}; var q=new URLSearchParams(); if(v.origem)q.set('origem',v.origem); if(v.destino)q.set('destino',v.destino); if(v.data)q.set('data',v.data); if(v.poltronas&&v.poltronas.length)q.set('poltrona',v.poltronas[0]); if(v.cliente)q.set('nome',v.cliente); q.set('tel','{{ $sel->cliente_telefone }}'); q.set('embed','1'); return '{{ route('passagens.create') }}?'+q.toString(); },
    abrirVenda(){ if(!this.vendaSrc) this.vendaSrc=this.vendaUrl(); },

    async puxar(){
      try{
        var r = await fetch('{{ url('/passagens/atendimento') }}/'+id+'/mensagens?desde='+this.ultimo, {headers:{'X-Requested-With':'fetch'}});
        if(!r.ok) return;
        var d = await r.json();
        if(d.venda) this.venda = d.venda;
        if(d.mensagens && d.mensagens.length){
          var box = this.$refs.msgs;
          d.mensagens.forEach(function(m){
            this.ultimo = m.id;
            var ent = m.direcao==='entrada';
            var cls = ent ? 'bg-white' : (m.origem==='ia' ? 'bg-cyan-600 text-white' : 'text-white');
            var sty = (!ent && m.origem!=='ia') ? ' style="background:#128c4a"' : '';
            var autor = !ent ? ('<div class="text-[10px] opacity-80 font-semibold mb-0.5">'+(m.origem==='ia'?'🤖 Agente':(m.autor||'Atendente'))+'</div>') : '';
            var html = '<div class="flex '+(ent?'justify-start':'justify-end')+'"><div class="atd-bub max-w-[78%] rounded-2xl px-3 py-2 text-sm '+cls+'"'+sty+'>'+autor+'<div class="whitespace-pre-wrap break-words"></div><div class="text-[10px] opacity-60 text-right mt-0.5">'+(m.hora||'')+'</div></div></div>';
            var tmp=document.createElement('div'); tmp.innerHTML=html;
            tmp.querySelector('.whitespace-pre-wrap').textContent = m.texto || '';
            if (m.arquivo) {
              var mime = m.mime || '', el;
              if (mime.indexOf('image/') === 0) { el = document.createElement('img'); el.src = m.arquivo; el.className = 'rounded-lg max-h-56 mb-1'; }
              else if (mime.indexOf('video/') === 0) { el = document.createElement('video'); el.src = m.arquivo; el.controls = true; el.className = 'rounded-lg max-h-56 mb-1'; }
              else if (mime.indexOf('audio/') === 0) { el = document.createElement('audio'); el.src = m.arquivo; el.controls = true; el.className = 'mb-1 max-w-full'; }
              else { el = document.createElement('a'); el.href = m.arquivo; el.target = '_blank'; el.className = 'underline font-semibold block mb-1'; el.textContent = '📎 ' + (m.nome || 'arquivo'); }
              var alvo = tmp.querySelector('.whitespace-pre-wrap'); alvo.parentNode.insertBefore(el, alvo);
            }
            box.appendChild(tmp.firstChild);
          }.bind(this));
          this.rolar();
        }
      }catch(e){}
    }
  }
}
</script>
<script>
(function(){
  var last = null, lastHash = null;
  var banner = document.getElementById('alertaAtend');
  var listaUrl = '{{ route('atendimento.lista') }}';
  var curFiltro = @json($filtro);
  var curSel = {{ $sel->id ?? 'null' }};
  async function refreshLista(){
    try{
      var r = await fetch(listaUrl+'?status='+encodeURIComponent(curFiltro)+'&sel='+(curSel||''), {headers:{'X-Requested-With':'fetch'}});
      if(!r.ok) return; var d = await r.json();
      var box = document.getElementById('listaConversas'); if(box && typeof d.html==='string') box.innerHTML = d.html;
      if(d.hash) lastHash = d.hash;
    }catch(e){}
  }
  function beep(){ try{ var a=new (window.AudioContext||window.webkitAudioContext)(); var o=a.createOscillator(); var g=a.createGain(); o.connect(g); g.connect(a.destination); o.type='sine'; o.frequency.value=880; g.gain.value=0.12; o.start(); setTimeout(function(){o.frequency.value=660;},150); setTimeout(function(){o.stop();a.close();},340);}catch(e){} }
  function notif(n){ try{ if(window.Notification && Notification.permission==='granted'){ new Notification('WhatsApp — novo atendimento', {body:n+' cliente(s) esperando atendente'}); } }catch(e){} }
  async function poll(){
    try{
      var r = await fetch('{{ route('atendimento.alertas') }}', {headers:{'X-Requested-With':'fetch'}});
      if(!r.ok) return;
      var d = await r.json(); var n = d.precisa||0;
      var b=document.getElementById('badgePrecisa'); if(b){ b.textContent='🔔 '+n; b.style.display = n>0?'':'none'; }
      if(banner){
        if(n>0){ banner.style.display=''; var cn=banner.querySelector('[data-n]'); if(cn) cn.textContent=n;
          var ul=banner.querySelector('[data-lista]'); ul.innerHTML='';
          (d.itens||[]).forEach(function(it){ var a=document.createElement('a'); a.href='/passagens/atendimento/'+it.id; a.className='block text-sm font-semibold text-rose-700 hover:underline'; a.textContent='🔔 '+it.nome+(it.motivo?(' — '+it.motivo):''); ul.appendChild(a); });
        } else banner.style.display='none';
      }
      if(last!==null && n>last){ beep(); notif(n); }
      last=n;
      if(d.hash){ if(lastHash===null){ lastHash=d.hash; } else if(d.hash!==lastHash){ refreshLista(); } }
    }catch(e){}
  }
  try{ if(window.Notification && Notification.permission==='default'){ Notification.requestPermission(); } }catch(e){}
  poll(); setInterval(poll, 5000);
})();
</script>
@endsection
