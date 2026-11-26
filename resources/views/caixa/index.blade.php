@extends('layouts.app')
@section('titulo', ($ehRelatorio ?? false) ? 'Relatório' : 'Caixa')
@section('conteudo')
@php
  $focoPrest = request('foco') === 'prestacoes';
  $ehRel = (bool) ($ehRelatorio ?? false);
  // identidade de cada página: [cor, cor escura, ícone, título, subtítulo]
  $tema = $focoPrest
    ? ['#d97706', '#92400e', '🧾', 'Prestação de Contas', 'O que falta acertar com os proprietários · fechamento, histórico e desfazer']
    : ($ehRel
      ? ['#4f46e5', '#312e81', '📋', ($verTodos ? 'Relatório geral' : 'Meu relatório'), 'Vendas e movimento por período, transporte e viagem']
      : ['#059669', '#065f46', '💰', ($verTodos ? 'Caixa geral' : 'Meu caixa'), 'Dinheiro da agência · entradas, saídas e comissão']);
  $abertoTotal = $focoPrest ? (float) $donosBalcao->sum(fn ($d) => max(0, (float) $d->liquido)) : 0;
@endphp
<div id="rt-root" class="space-y-4" style="--cor1:{{ $tema[0] }}">
<div class="rounded-2xl bg-white px-4 py-3 flex items-center justify-between gap-3 flex-wrap"
     style="border:1px solid color-mix(in srgb, {{ $tema[0] }} 22%, white); box-shadow: inset 4px 0 0 {{ $tema[0] }}, 0 1px 2px rgba(15,23,42,.04)">
  <div class="flex items-center gap-3 min-w-0">
    <div class="w-11 h-11 rounded-xl flex items-center justify-center text-xl shrink-0" style="background:color-mix(in srgb, {{ $tema[0] }} 12%, white)">{{ $tema[2] }}</div>
    <div class="min-w-0">
      <h1 class="text-lg font-extrabold leading-tight flex items-center gap-2" style="color:{{ $tema[1] }}">{{ $tema[3] }}
        <span data-rt-live class="no-print inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide transition" style="background:color-mix(in srgb, {{ $tema[0] }} 10%, white); color:{{ $tema[0] }}"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>ao vivo</span></h1>
      <div class="text-[12px] text-slate-500">{{ $tema[4] }}</div>
    </div>
  </div>
  @if(! ($focoPrest && ! $transporteId))<div class="flex items-center gap-2 no-print">@unless($focoPrest)<a href="{{ route('passagens.caixa-viagem', array_filter(['inicio'=>\Illuminate\Support\Carbon::parse($inicio)->toDateString(),'fim'=>\Illuminate\Support\Carbon::parse($fim)->toDateString(),'embarcacao_id'=>$transporteId,'user_id'=>$userId,'data_viagem'=>$dataViagem,'viagem_id'=>$viagemId,'relatorio'=>(($ehRelatorio ?? false) ? 1 : null),'modo'=>($focoPrest ? 'prestacao' : (($ehRelatorio ?? false) ? 'relatorio' : 'caixa'))])) }}" class="btn btn-ghost btn-sm">📄 {{ $focoPrest ? 'Pré-prestação' : ($ehRel ? 'Relatório' : 'Caixa') }} (PDF)</a><button onclick="window.print()" class="btn btn-ghost btn-sm">🖨 Imprimir</button>@endunless</div>@endif</div>

@php $escolher = $focoPrest && ! $transporteId; @endphp
@if($escolher)
@php
  $tSel = $transporteId ? $transportes->firstWhere('id', (int) $transporteId) : null;
  $tLista = $transportes->values(); // todos, com ou sem login de proprietário
@endphp
<div class="card overflow-hidden">
  <div class="p-4 border-b flex items-center gap-3 flex-wrap">
    <div class="flex items-center gap-2 text-sm font-bold">
      <span class="inline-flex w-7 h-7 rounded-full items-center justify-center text-white text-[13px]" style="background:{{ $tSel ? '#16a34a' : $tema[0] }}">{{ $tSel ? '✓' : '1' }}</span>
      <span class="{{ $tSel ? 'text-slate-400' : 'text-slate-800' }}">Transporte</span>
    </div>
    <span class="text-slate-300">›</span>
    <div class="flex items-center gap-2 text-sm font-bold text-slate-400"><span class="inline-flex w-7 h-7 rounded-full items-center justify-center text-[13px] bg-slate-100">2</span>Prestação (todas as viagens ou uma só)</div>
  </div>

  @if(! $tSel)
  <div class="p-4">
    <div class="flex items-center justify-between gap-2">
      <div class="text-lg font-extrabold text-slate-800">De qual transporte é a prestação?</div>
      <button type="button" id="vrecToggle" data-on="1" class="rounded-full px-3 py-1.5 text-[12px] font-bold border transition shrink-0" title="Mostra no card quando alguem acabou de vender, ao vivo">👁 Vendas ao vivo</button>
    </div>
    <div class="text-[13px] text-slate-500 mb-3">Escolha a lancha. A prestação abre com todas as viagens em aberto; se quiser, depois você filtra uma viagem só.</div>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2.5">
      @forelse($tLista as $t)
        @php $pp = $pendPorTransporte[$t->id] ?? null; @endphp
        <a href="{{ route('caixa.index', ['foco' => 'prestacoes', 'transporte_id' => $t->id]) }}"
           data-emb="{{ $t->id }}" class="vrec-card relative overflow-hidden group rounded-2xl border bg-white p-3.5 flex items-center gap-3 transition hover:shadow-md"
           style="border-color:{{ $pp ? 'color-mix(in srgb, '.$tema[0].' 45%, white)' : '#e2e8f0' }}">
          <div class="w-11 h-11 rounded-xl flex items-center justify-center text-xl shrink-0" style="background:color-mix(in srgb, {{ $tema[0] }} 12%, white)">🚤</div>
          <div class="min-w-0 flex-1">
            <div class="font-bold text-slate-800 truncate">{{ $t->nome }}</div>
            <div class="text-[11px] text-slate-400 truncate">{{ ($t->dono?->name ?: $t->dono_nome) ? 'proprietário '.($t->dono?->name ?: $t->dono_nome).(empty($t->dono_id) ? ' · sem login' : '') : 'proprietário sem login ainda' }}</div>
          </div>
          @if($pp)
            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold whitespace-nowrap" style="background:color-mix(in srgb, {{ $tema[0] }} 14%, white); color:{{ $tema[1] }}">{{ $pp->qtd }} em aberto</span>
          @else
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-400 whitespace-nowrap">em dia</span>
          @endif
          <span class="vrec-toast" data-emb-toast="{{ $t->id }}"></span>
        </a>
      @empty
        <div class="col-span-full p-6 text-center text-slate-400">Nenhum transporte cadastrado.</div>
      @endforelse
    </div>
    <style>
      .vrec-toast{position:absolute;left:0;right:0;bottom:0;transform:translateY(115%);opacity:0;transition:transform .55s cubic-bezier(.2,.8,.3,1),opacity .6s ease;background:linear-gradient(90deg,#059669,#10b981);color:#fff;font-size:11px;font-weight:800;padding:5px 11px;line-height:1.2;box-shadow:0 -2px 10px rgba(5,150,105,.35);z-index:3;pointer-events:none}
      .vrec-toast.show{transform:translateY(0);opacity:1}
      .vrec-card.vrec-flash{box-shadow:0 0 0 3px rgba(16,185,129,.55)!important;transition:box-shadow .2s}
      #vrecToggle[data-on="1"]{background:#059669;color:#fff;border-color:#059669}
      #vrecToggle[data-on="0"]{background:#fff;color:#64748b;border-color:#e2e8f0}
    </style>
    <script>
    (function(){
      var any = document.querySelector('.vrec-card'); if(!any) return;
      var toggle = document.getElementById('vrecToggle');
      var on = (localStorage.getItem('vrecOn') !== '0');
      function setOn(v){ on=v; try{localStorage.setItem('vrecOn', v?'1':'0');}catch(e){} if(toggle) toggle.setAttribute('data-on', v?'1':'0'); }
      setOn(on);
      if(toggle) toggle.addEventListener('click', function(){ setOn(!on); });
      var cursor = 0;
      function mostra(v){
        var card = document.querySelector('.vrec-card[data-emb="'+v.emb+'"]'); if(!card) return;
        var toast = card.querySelector('[data-emb-toast="'+v.emb+'"]'); if(!toast) return;
        var quem = v.agente ? ('🤝 '+v.vendedor+' (agente)') : ('💰 '+v.vendedor);
        toast.textContent = quem + ' vendeu uma passagem';
        toast.classList.add('show'); card.classList.add('vrec-flash');
        clearTimeout(toast._t);
        toast._t = setTimeout(function(){ toast.classList.remove('show'); card.classList.remove('vrec-flash'); }, 5000);
      }
      function processa(vendas){
        var porCard = {}; vendas.forEach(function(v){ porCard[v.emb] = v; });
        var i=0; Object.keys(porCard).forEach(function(k){ setTimeout(function(){ mostra(porCard[k]); }, i*250); i++; });
      }
      async function poll(){
        if(!on || document.visibilityState!=='visible') return;
        try{
          var r = await fetch('{{ route('sync.vendas-recentes') }}?desde='+cursor, {headers:{'X-Requested-With':'fetch'}});
          if(!r.ok) return; var d = await r.json();
          if(typeof d.ultimo==='number') cursor = d.ultimo;
          if(d.vendas && d.vendas.length) processa(d.vendas);
        }catch(e){}
      }
      poll();
      setInterval(poll, 4500);
      document.addEventListener('visibilitychange', function(){ if(document.visibilityState==='visible') poll(); });
    })();
    </script>
  </div>
  @endif
</div>
@include('caixa._prest_operadores')
@else
@if($focoPrest)
@php
  $tSel = $transportes->firstWhere('id', (int) $transporteId);
  $pendD = ($pendPorData ?? collect());
  $chipsD = $pendD->keys()->map(fn ($d) => (string) $d);
  if ($dataViagem && ! $chipsD->contains((string) $dataViagem)) $chipsD->push((string) $dataViagem);
  $chipsD = $chipsD->sort()->values();
  $pendTot = (int) $pendD->sum('qtd');
@endphp
<form method="get" action="{{ route('caixa.index') }}" class="card p-3 flex flex-wrap gap-3 items-end no-print">
  <input type="hidden" name="foco" value="prestacoes">
  <input type="hidden" name="transporte_id" value="{{ (int) $transporteId }}">
  <div class="flex items-center gap-2 font-extrabold text-slate-800 text-base pr-2">🚤 {{ optional($tSel)->nome }}</div>
  <div class="min-w-[240px] flex-1 sm:flex-none">
    <label class="label">Data da viagem</label>
    <select name="data_viagem" class="input !min-h-[42px]" onchange="this.form.submit()">
      <option value="">Todas as viagens · {{ $pendTot }} em aberto</option>
      @foreach($datasViagem as $dv)
        @php $dvs = \Illuminate\Support\Carbon::parse($dv)->toDateString(); $pq = $pendD[$dvs] ?? null; @endphp
        <option value="{{ $dvs }}" @selected((string) ($dataViagem ?? '') === $dvs)>{{ \Illuminate\Support\Carbon::parse($dv)->format('d/m/Y') }}{{ $pq ? ' · '.$pq->qtd.' em aberto' : '' }}</option>
      @endforeach
    </select>
  </div>
  <a href="{{ route('caixa.index', ['foco' => 'prestacoes']) }}" class="btn btn-ghost btn-sm ml-auto">← Voltar aos transportes</a>
</form>
@endif
@if($podeAvulso && ! ($ehRelatorio ?? false))
<script>window.VIAGENS = @json($viagensTransporte ?? []);</script>
<div x-data="{open:false, editId:0, tipo:'entrada', emb:'{{ $focoPrest ? (int) $transporteId : '' }}', viagemSel:'', meio:'dinheiro', categoria:'', descricao:'', baseAvulso: @js(url('/caixa/avulso')),
  setVal(v){ try{ this.$refs.valorInp.value = v||''; this.$refs.valorInp.dispatchEvent(new Event('input',{bubbles:true})); }catch(e){} },
  novoAv(t){ this.editId=0; this.tipo=t; this.emb='{{ $focoPrest ? (int) $transporteId : '' }}'; this.viagemSel=''; this.meio='dinheiro'; this.categoria=''; this.descricao=''; this.open=true; this.$nextTick(()=>this.setVal('')); },
  editarAv(a){ this.editId=a.id; this.tipo=a.tipo_mov; this.emb=a.emb||''; this.viagemSel=a.data_viagem||''; this.meio=a.meio||'dinheiro'; this.categoria=a.categoria||''; this.descricao=a.descricao||''; this.open=true; this.$nextTick(()=>this.setVal(a.valor)); } }"
  class="no-print" @abrir-avulso.window="novoAv($event.detail.tipo)" @editar-avulso.window="editarAv($event.detail)">
  <div x-show="open" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-3" @click="open=false">
    <form method="post" :action="editId ? (baseAvulso + '/' + editId + '/editar') : baseAvulso" class="bg-white rounded-2xl p-4 w-full max-w-sm space-y-3" @click.stop>
      @csrf
      <input type="hidden" name="tipo_mov" :value="tipo">
      <div class="font-bold text-lg" x-text="(editId ? '✏️ Editar ' : '') + (tipo==='entrada' ? '➕ Entrada avulsa' : '➖ Saída avulsa')"></div>
      @php $avNaPrest = $focoPrest && $transporteId; @endphp
      @if($avNaPrest)
      <div class="text-[12px] text-slate-500 -mt-1">🚤 {{ optional($transportes->firstWhere('id', (int) $transporteId))->nome }} · entra na prestação deste transporte</div>
      <input type="hidden" name="embarcacao_id" value="{{ (int) $transporteId }}">
      @endif
      <div @if($avNaPrest) style="display:none" @endif><label class="label">Categoria</label>
        <select name="categoria" x-model="categoria" class="input" :required="!emb" @disabled($avNaPrest)>
          <option value="">— escolher —</option>
          @foreach($categorias as $c)<option value="{{ $c->nome }}" x-show="'{{ $c->tipo }}'==='ambos' || '{{ $c->tipo }}'===tipo">{{ $c->nome }}</option>@endforeach
        </select>
        @if(auth()->user()->isAdmin() || auth()->user()->isSuper())<p class="text-[11px] text-slate-400 mt-1">Criar/editar categorias em <a href="{{ route('admin.cadastros') }}" class="underline">Cadastros gerais</a>.</p>@endif
      </div>
      <div><label class="label">Valor</label><input name="valor" class="input" x-money x-ref="valorInp"></div>
      <div><label class="label">Forma</label>
        <select name="meio" x-model="meio" class="input"><option value="dinheiro">Dinheiro</option><option value="pix">PIX</option><option value="debito">Débito</option><option value="credito">Crédito</option></select></div>
      <div @if($avNaPrest) style="display:none" @endif><label class="label">Transporte (p/ prestação) — vazio = caixa da agência</label>
        <select name="embarcacao_id" class="input" x-model="emb" @change="viagemSel = (window.VIAGENS[$event.target.value] && window.VIAGENS[$event.target.value][0]) ? window.VIAGENS[$event.target.value][0].d : ''" @disabled($avNaPrest)>
          <option value="">— caixa da agência —</option>
          @foreach($transportes as $t)<option value="{{ $t->id }}">{{ $t->nome }}</option>@endforeach
        </select></div>
      <div x-show="emb" x-cloak><label class="label">Viagem (pra cobrar na prestação)</label>
        <select name="data_viagem" class="input" x-model="viagemSel">
          <option value="">Todas as viagens</option>
          <template x-for="v in (window.VIAGENS[emb] || [])" :key="v.d">
            <option :value="v.d" x-text="v.l"></option>
          </template>
        </select>
        <p class="text-[11px] text-slate-400 mt-1" x-show="!(window.VIAGENS[emb] && window.VIAGENS[emb].length)">Sem próximas viagens cadastradas pra esse transporte — vai valer pra todas.</p>
      </div>
      <div><label class="label">{{ $avNaPrest ? 'Motivo / descrição' : 'Descrição (opcional)' }}</label><input name="descricao" x-model="descricao" class="input" maxlength="200"></div>
      <div class="flex gap-2 pt-1">
        <button type="button" class="btn btn-ghost flex-1" @click="open=false">Cancelar</button>
        <button class="btn flex-1 text-white" :style="tipo==='entrada'?'background:#059669':'background:#e11d48'" x-text="editId ? 'Salvar' : 'Registrar'"></button>
      </div>
    </form>
  </div>
</div>
@endif

@unless($focoPrest)
@if(($seguraCaixa ?? false) && ($emAbertoOperadores ?? 0) > 0)
<a href="{{ route('caixa.index', ['foco' => 'prestacoes']) }}" class="no-print block rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800 hover:bg-amber-100">
  ⏳ <b>R$ {{ number_format($emAbertoOperadores,2,',','.') }}</b> em aberto com operadores — só entra no Caixa depois da prestação. <span class="underline">Ver prestação com operadores</span>
</a>
@endif
@if($ehRel)
<x-periodo :periodo="$periodo" :inicio="$inicio" :fim="$fim">
  @if($verTodos)
  <div><label class="label">Operador</label>
    <select name="user_id" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todos</option>
      @foreach($usuarios as $us)<option value="{{ $us->id }}" @selected($userId==$us->id)>{{ $us->name }}</option>@endforeach</select></div>
  @endif
  <div><label class="label">Módulo</label>
    <select name="modulo" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todos os módulos</option>
      @php
        $_ma = \App\Support\Tenant::empresa()?->config('modulos_ativos');
        $_mods = collect(\App\Support\Modulos::LISTA)->filter(fn ($mv, $mk) => (empty($_ma) || in_array($mk, (array) $_ma, true)) && auth()->user()->temModulo($mk));
      @endphp
      @foreach($_mods as $mk=>$mv)<option value="{{ $mk }}" @selected(($modulo ?? '')===$mk)>{{ $mv[1] }} {{ $mv[0] }}</option>@endforeach</select></div>
  <div><label class="label">Forma</label>
    <select name="canal" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todas as formas</option>
      @foreach(\App\Models\Pagamento::CANAIS as $ck=>$cv)<option value="{{ $ck }}" @selected(($canal ?? '')===$ck)>{{ $cv }}</option>@endforeach</select></div>
  @if($viagens->count())
  <div><label class="label">Viagem</label>
    <select name="viagem_id" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">— período —</option>
      @foreach($viagens as $v)<option value="{{ $v->id }}" @selected($viagemId==$v->id)>{{ $v->numeroFormatado() }} · {{ $v->nome }}</option>@endforeach</select></div>
  @endif
  @if($podeTransporte)
  <div><label class="label">Transporte</label>
    <select name="transporte_id" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todos</option>
      @foreach($transportes as $t)<option value="{{ $t->id }}" @selected((string)$transporteId===(string)$t->id)>{{ $t->nome }}</option>@endforeach</select></div>
  @endif
  @if($podeTransporte && $transporteId && $datasViagem->count())
  <div><label class="label">Data da viagem</label>
    <select name="data_viagem" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todas as datas</option>
      @foreach($datasViagem as $dv)@php $dvs=\Illuminate\Support\Carbon::parse($dv)->toDateString(); @endphp<option value="{{ $dvs }}" @selected((string)($dataViagem ?? '')===$dvs)>{{ \Illuminate\Support\Carbon::parse($dv)->format('d/m/Y') }}</option>@endforeach</select></div>
  @endif
</x-periodo>
@endif
@endunless
@if($viagemId)<div class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Mostrando o caixa da viagem selecionada (o período acima é ignorado).</div>@endif

@unless($focoPrest)
<style>
  details.vendas-det > summary{list-style:none}
  details.vendas-det > summary::-webkit-details-marker{display:none}
  details.vendas-det[open] .vd-ver{display:none}
  details.vendas-det:not([open]) .vd-fechar{display:none}
  @media print{ details.vendas-det > div{display:block !important} details.vendas-det .vd-ver,details.vendas-det .vd-fechar{display:none} }
</style>
@if(! $ehRel)
<form method="GET" action="{{ route('caixa.index') }}" class="flex items-center gap-2 flex-wrap text-[13px] text-slate-500 px-1">
  <span class="font-semibold text-slate-400">📅 Período</span>
  <input type="date" name="inicio" value="{{ \Illuminate\Support\Carbon::parse($inicio)->format('Y-m-d') }}" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[13px]">
  <span class="text-slate-400">até</span>
  <input type="date" name="fim" value="{{ \Illuminate\Support\Carbon::parse($fim)->format('Y-m-d') }}" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[13px]">
  <input type="hidden" name="periodo" value="personalizado">
  <button type="submit" class="rounded-lg text-white px-3 py-1 text-[13px] font-semibold" style="background:var(--cor2)">Ver</button>
  @if(($periodo ?? 'hoje') !== 'hoje')<a href="{{ route('caixa.index') }}" class="underline text-slate-400 ml-1">voltar p/ hoje</a>@endif
</form>
@endif
@unless($ehRel)
@php
  $navy = \App\Support\Tenant::empresa()?->cor_primaria ?: '#0b1a33';
  $pmV = fn ($k) => (float) ($porMeio[$k]->valor ?? 0);
  $cxDinheiro = $pmV('dinheiro');
  $cxPix      = $pmV('pix_manual') + $pmV('pix_auto');
  $cxDebito   = $pmV('debito');
  $cxCredito  = $pmV('credito');
  $cxCartao   = $cxDebito + $cxCredito;
  $cxVendas   = (int) collect($porCategoria ?? [])->sum('qtd');
  $cxAvL      = $avulsosLista ?? collect();
  $cxAvE      = (float) $cxAvL->where('tipo_mov', 'entrada')->sum('valor');
  $cxAvS      = (float) $cxAvL->where('tipo_mov', 'saida')->sum('valor');
@endphp
<style>
  .cx-painel{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,1fr) minmax(0,1fr);gap:12px}
  .cx-tiles{display:grid;grid-template-columns:1fr 1fr;gap:12px;align-content:start}
  .cx-avrows{overflow-y:auto;max-height:300px}
  @media (max-width:1100px){ .cx-painel{grid-template-columns:1fr 1fr} .cx-avlist{grid-column:1 / -1} }
  @media (max-width:720px){ .cx-painel{grid-template-columns:1fr} .cx-avlist{grid-column:auto} }
</style>
<div class="cx-painel">
  <div class="rounded-2xl p-5 text-white flex flex-col" style="background:linear-gradient(135deg, {{ $navy }}, color-mix(in srgb, {{ $navy }} 60%, #000)); box-shadow:0 10px 26px -14px color-mix(in srgb, {{ $navy }} 70%, #000)">
    <div class="text-[11px] font-bold uppercase tracking-wider" style="color:rgba(255,255,255,.62)">No caixa agora</div>
    <div class="font-extrabold leading-none mt-2 mb-4" style="font-size:clamp(34px,6vw,56px);color:#34d399">R$ {{ number_format($liquidoAgencia ?? $total,2,',','.') }}</div>
    <div class="flex gap-8 flex-wrap gap-y-3">
      <div><div class="text-[10px] font-bold uppercase tracking-wide" style="color:rgba(255,255,255,.58)">Recebeu ↑</div><div class="text-lg font-extrabold" style="color:#34d399">R$ {{ number_format($entradas ?? 0,2,',','.') }}</div></div>
      <div><div class="text-[10px] font-bold uppercase tracking-wide" style="color:rgba(255,255,255,.58)">Saídas ↓</div><div class="text-lg font-extrabold" style="color:#fb7185">R$ {{ number_format($saidasCaixa ?? $saidas,2,',','.') }}</div></div>
      @if(($ownerShareG ?? 0) > 0)<div title="Parte dos donos (comissão deles) descontada no dia da venda. O pagamento ao dono na baixa não conta como saída."><div class="text-[10px] font-bold uppercase tracking-wide" style="color:rgba(255,255,255,.58)">Parte dos donos ↓</div><div class="text-lg font-extrabold" style="color:#fbbf24">R$ {{ number_format($ownerShareG,2,',','.') }}</div></div>@endif
    </div>
    @if($podeAvulso)
    <div class="flex gap-2 mt-auto pt-4" style="border-top:1px solid rgba(255,255,255,.14);margin-top:18px">
      <button type="button" onclick="window.dispatchEvent(new CustomEvent('abrir-avulso',{detail:{tipo:'entrada'}}))" class="btn btn-sm text-white flex-1" style="background:#059669">➕ Entrada avulsa</button>
      <button type="button" onclick="window.dispatchEvent(new CustomEvent('abrir-avulso',{detail:{tipo:'saida'}}))" class="btn btn-sm text-white flex-1" style="background:#e11d48">➖ Saída avulsa</button>
    </div>
    @endif
  </div>
  <div class="card p-4 flex flex-col gap-2">
    <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Formas de pagamento</div>
    <div class="flex items-center justify-between"><span class="text-[13px] text-slate-600">💵 Dinheiro</span><b class="font-extrabold">R$ {{ number_format($cxDinheiro,2,',','.') }}</b></div>
    <div class="flex items-center justify-between"><span class="text-[13px] text-slate-600">⚡ Pix</span><b class="font-extrabold">R$ {{ number_format($cxPix,2,',','.') }}</b></div>
    <div class="flex items-center justify-between"><span class="text-[13px] text-slate-600">💳 Débito</span><b class="font-extrabold">R$ {{ number_format($cxDebito,2,',','.') }}</b></div>
    <div class="flex items-center justify-between"><span class="text-[13px] text-slate-600">💳 Crédito</span><b class="font-extrabold">R$ {{ number_format($cxCredito,2,',','.') }}</b></div>
    <div class="flex items-center justify-between border-t pt-2 mt-0.5"><span class="text-[13px] font-bold text-slate-700">Total recebido</span><b class="font-extrabold text-emerald-600">R$ {{ number_format($entradas ?? 0,2,',','.') }}</b></div>
    <div class="flex items-center justify-between"><span class="text-[12px] text-slate-500">🏧 Maquineta dos proprietários</span><b class="font-bold text-slate-600">R$ {{ number_format($maqProprietarios ?? 0,2,',','.') }}</b></div>
    <div class="flex items-center justify-between"><span class="text-[12px] text-slate-500">🎫 Vendas</span><b class="font-bold text-slate-600">{{ $cxVendas }}</b></div>
  </div>
  <div class="card cx-avlist overflow-hidden flex flex-col">
    <div class="p-3 border-b flex items-center justify-between gap-2">
      <div class="font-bold text-[13px] text-slate-700">↕️ Avulsas</div>
      <div class="flex items-center gap-1.5 text-[11px]">
        <span class="rounded bg-emerald-50 text-emerald-700 px-1.5 py-0.5 font-bold">+{{ number_format($cxAvE,2,',','.') }}</span>
        <span class="rounded bg-rose-50 text-rose-700 px-1.5 py-0.5 font-bold">−{{ number_format($cxAvS,2,',','.') }}</span>
      </div>
    </div>
    <div class="cx-avrows divide-y">
      @forelse($cxAvL as $av)
      <div class="flex items-center gap-2 px-3 py-2">
        <span class="rounded px-1.5 py-0.5 text-[10px] font-bold shrink-0 {{ $av->tipo_mov==='saida' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $av->tipo_mov==='saida' ? 'Saída' : 'Entrada' }}</span>
        <div class="min-w-0 flex-1">
          <div class="text-[12px] font-medium truncate">{{ trim(($av->categoria ? $av->categoria.' · ' : '').($av->descricao ?? ''), ' ·') ?: '—' }}</div>
          <div class="text-[10px] text-slate-400 truncate">{{ optional($av->created_at)->format('d/m H:i') }}@if($av->embarcacao) · 🚤 {{ $av->embarcacao->nome }}@endif</div>
        </div>
        <div class="text-[12px] font-bold whitespace-nowrap shrink-0 {{ $av->tipo_mov==='saida' ? 'text-rose-600' : 'text-emerald-700' }}">{{ $av->tipo_mov==='saida' ? '−' : '+' }} {{ number_format($av->valor,2,',','.') }}</div>
        @if($podeAvulso && ! $av->prestacao_id)
        <div class="flex items-center shrink-0 -mr-1">
          <button type="button" title="Editar" onclick="window.dispatchEvent(new CustomEvent('editar-avulso',{detail: @js(['id'=>$av->id,'tipo_mov'=>$av->tipo_mov,'valor'=>number_format($av->valor,2,',','.'),'meio'=>(string)$av->meio,'categoria'=>(string)$av->categoria,'descricao'=>(string)$av->descricao,'emb'=>(string)($av->embarcacao_id ?? ''),'data_viagem'=>$av->data_viagem ? \Illuminate\Support\Carbon::parse($av->data_viagem)->toDateString() : ''])}))" class="text-slate-300 hover:text-indigo-600 px-1 text-[13px]">✏️</button>
          <form method="post" action="{{ route('caixa.avulso.excluir', $av->id) }}" onsubmit="return confirm('Excluir este avulso?')">@csrf
            <button type="submit" title="Excluir" class="text-slate-300 hover:text-rose-600 px-1 text-[13px]">🗑</button>
          </form>
        </div>
        @endif
      </div>
      @empty
      <div class="p-5 text-center text-slate-400 text-[12px]">Nenhuma entrada/saída avulsa no período.</div>
      @endforelse
    </div>
  </div>
</div>
@endunless

<details class="vendas-det">
  <summary class="cursor-pointer text-[12px] font-semibold text-slate-400 px-1 py-1 select-none">
    <span class="vd-ver">ver vendas e formas de pagamento ▾</span>
    <span class="vd-fechar">ocultar vendas ▴</span>
  </summary>
  <div class="card p-4 mt-1 space-y-2.5">
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-2">
      <div class="card p-3" style="border:2px solid var(--cor1)"><div class="text-[11px] uppercase text-slate-500">Vendas (bruto)</div><div class="text-xl font-extrabold" style="color:var(--cor1)">R$ {{ number_format($vendasBruto ?? 0,2,',','.') }}</div><div class="text-[10px] text-slate-400">só balcão (operadores)</div></div>
      @foreach(['dinheiro','pix_manual','pix_auto','debito','credito'] as $m)
        <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">{{ $meios[$m] }}</div><div class="text-lg font-extrabold">R$ {{ number_format($porMeio[$m]->valor ?? 0,2,',','.') }}</div><div class="text-xs text-slate-400">{{ $porMeio[$m]->qtd ?? 0 }} lançamentos</div></div>
      @endforeach
    </div>
    <div class="flex flex-wrap items-center gap-x-7 gap-y-1.5 text-[13px]">
      <span class="text-slate-500">Saídas avulsas <b class="text-red-600 ml-1">R$ {{ number_format($saidas,2,',','.') }}</b></span>
      <span class="text-slate-300">·</span>
      <span class="text-slate-500">Descontos <b class="text-slate-800 ml-1">R$ {{ number_format($descontos,2,',','.') }}</b></span>
      <span class="text-slate-300">·</span>
      <span class="text-slate-500">Cadastros <b class="text-slate-800 ml-1">{{ $cadastros }}</b></span>
      <span class="text-slate-300">·</span>
      <span class="text-slate-500">Entregas <b class="text-slate-800 ml-1">{{ $entregas }}</b></span>
      <span class="text-slate-300">·</span>
      <span class="text-slate-500">❌ Cancelados <b class="text-slate-800 ml-1">{{ $cancelados->qtd ?? 0 }} · R$ {{ number_format($cancelados->valor ?? 0,2,',','.') }}</b></span>
    </div>
  </div>
</details>

@endunless

@if(($prestacaoTransporte ?? false) && $focoPrest)
<div class="card overflow-hidden border-2 border-indigo-300">
  <div class="p-3 border-b bg-indigo-50 flex items-center justify-between flex-wrap gap-2">
    <div class="font-bold text-sm text-indigo-800">📋 Prestação de contas — 🚤 {{ $prestacaoTransporte->nome }}</div>
    <div class="text-[11px] text-indigo-600">{{ $prestacaoTransporte->qtd }} passagem(ns) no filtro</div>
  </div>
  <div class="p-3">
    <div class="grid grid-cols-2 gap-2 max-w-md mx-auto mb-3">
      <div class="rounded-xl bg-slate-900 text-white p-3 text-center"><div class="text-[10px] uppercase tracking-wide opacity-70">Total de Vendas</div><div class="text-xl font-extrabold">R$ {{ number_format($prestacaoTransporte->bruto,2,',','.') }}</div></div>
      <div class="rounded-xl bg-indigo-600 text-white p-3 text-center"><div class="text-[10px] uppercase tracking-wide opacity-80">Comissão da Agência{{ !is_null($prestacaoTransporte->pct) ? ' ('.$prestacaoTransporte->pct.'%)' : '' }}</div><div class="text-xl font-extrabold">R$ {{ number_format($prestacaoTransporte->comissao,2,',','.') }}</div></div>
    </div>
    <div class="max-w-md mx-auto text-sm">
      <div class="flex justify-between py-1.5 border-b border-slate-100"><span>Total vendas de Passagens</span><b>R$ {{ number_format($prestacaoTransporte->bruto,2,',','.') }}</b></div>
      <div class="flex justify-between py-1.5 border-b border-slate-100"><span>Comissão de Agência{{ !is_null($prestacaoTransporte->pct) ? ' ('.$prestacaoTransporte->pct.'%)' : '' }}</span><b class="text-rose-600">− R$ {{ number_format($prestacaoTransporte->comissao,2,',','.') }}</b></div>
      <div class="flex justify-between py-1.5 border-b border-slate-100"><span>{{ $transporteId ? \App\Support\DebitoDono::rotulo(\App\Support\DebitoDono::itens((int) $transporteId)) : 'Cancelamento / devolução' }} a descontar</span><b class="text-rose-600">− R$ {{ number_format($prestacaoTransporte->debito,2,',','.') }}</b></div>
      <div class="flex justify-between py-1.5 border-b border-slate-100"><span>Cartão de crédito</span><b class="text-rose-600">− R$ {{ number_format($prestacaoTransporte->cartao,2,',','.') }}</b></div>
      <div class="flex justify-between py-1.5 border-b border-slate-100"><span>Recebido direto pelo proprietário</span><b class="text-rose-600">− R$ {{ number_format($prestacaoTransporte->credProp,2,',','.') }}</b></div>
      @if(($prestacaoTransporte->avEntrada ?? 0) > 0)<div class="flex justify-between py-1.5 border-b border-slate-100"><span>Entradas avulsas (transporte)</span><b class="text-emerald-600">+ R$ {{ number_format($prestacaoTransporte->avEntrada,2,',','.') }}</b></div>@endif
      @if(($prestacaoTransporte->avSaida ?? 0) > 0)<div class="flex justify-between py-1.5 border-b border-slate-100"><span>Saídas avulsas (transporte)</span><b class="text-rose-600">− R$ {{ number_format($prestacaoTransporte->avSaida,2,',','.') }}</b></div>@endif
      @if(($prestacaoTransporte->aberto ?? 0) > 0.009)<div class="flex justify-between py-1.5 border-b border-slate-100 text-blue-700"><span>🧾 Faturado a receber <span class="text-[10px] text-slate-400">(não entra — entra quando for pago no Financeiro)</span></span><b>R$ {{ number_format($prestacaoTransporte->aberto,2,',','.') }}</b></div>@endif
      <div class="flex justify-between py-2 border-t-2 border-slate-300 font-bold text-amber-700"><span>Total de descontos</span><b>R$ {{ number_format($prestacaoTransporte->despesas,2,',','.') }}</b></div>
      <div class="flex justify-between items-center mt-2 rounded-xl bg-emerald-50 border border-emerald-200 p-3"><span class="font-bold text-emerald-800">TOTAL A PAGAR AO PROPRIETÁRIO</span><b class="text-2xl text-emerald-700">R$ {{ number_format($prestacaoTransporte->liquido,2,',','.') }}</b></div>
<div class="mt-3 flex justify-end no-print"><a href="{{ route('passagens.caixa-viagem', array_filter(['inicio'=>\Illuminate\Support\Carbon::parse($inicio)->toDateString(),'fim'=>\Illuminate\Support\Carbon::parse($fim)->toDateString(),'embarcacao_id'=>$transporteId,'data_viagem'=>$dataViagem,'viagem_id'=>$viagemId,'modo'=>'prestacao'])) }}" class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">📄 Pré-prestação (PDF)</a></div>
    </div>
  </div>
</div>
@endif

@if($focoPrest)
@include('caixa._avulsos')
@endif

@if($focoPrest)
<div x-data="{ open:false, carregando:false, nome:'', linhas:[], total:0, dono:0, comissao:0, erro:'',
      async ver(d){ this.open=true; this.carregando=true; this.erro=''; this.nome=d.nome; this.linhas=[];
        try{ const u=new URL(@js(url('/prestacoes/pendentes'))+'/'+d.id, location.origin);
          const vg=@js((string) ($viagemId ?? '')), dv=@js((string) ($dataViagem ?? ''));
          if(vg)u.searchParams.set('viagem_id',vg); if(dv)u.searchParams.set('data_viagem',dv);
          const r=await fetch(u,{headers:{Accept:'application/json'},credentials:'same-origin'}); const j=await r.json();
          this.linhas=j.linhas||[]; this.total=j.total||0; this.dono=j.dono||0; this.comissao=j.comissao||0;
        }catch(e){ this.erro='Não foi possível carregar as vendas.'; }
        this.carregando=false; },
      m(n){ return 'R$ '+Number(n||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); } }"
     @ver-vendas.window="ver($event.detail)" @keydown.escape.window="open=false">
  <div x-show="open" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-3" @click="open=false">
    <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[88vh] flex flex-col overflow-hidden shadow-2xl" @click.stop>
      <div class="px-4 py-3 text-white flex items-center justify-between" style="background:linear-gradient(110deg,#d97706,#92400e)">
        <div><div class="text-[11px] uppercase tracking-wide opacity-80">Vendas em aberto desta prestação</div><div class="font-extrabold text-lg leading-tight">🚤 <span x-text="nome"></span></div></div>
        <button type="button" class="rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 text-sm font-bold" @click="open=false">✕ Fechar</button>
      </div>
      <div class="flex-1 overflow-auto">
        <div x-show="carregando" class="p-8 text-center text-slate-400">Carregando…</div>
        <div x-show="erro" class="p-8 text-center text-rose-600" x-text="erro"></div>
        <table x-show="!carregando && !erro" class="w-full text-sm">
          <thead class="sticky top-0 bg-slate-50 text-[10px] uppercase tracking-wide text-slate-400"><tr>
            <th class="text-left p-2">Bilhete</th><th class="text-left p-2">Comprador / passageiros</th><th class="text-left p-2">Trecho</th><th class="text-left p-2">Viagem</th><th class="text-left p-2">Vendido em</th><th class="text-left p-2">Operador</th><th class="text-right p-2">Valor</th>
          </tr></thead>
          <tbody class="divide-y">
            <template x-for="l in linhas" :key="l.id">
              <tr>
                <td class="p-2 font-semibold whitespace-nowrap"><a :href="l.url" target="_blank" class="text-indigo-600 hover:underline" x-text="l.numero"></a></td>
                <td class="p-2"><div class="font-medium"><span x-text="l.comprador || '—'"></span> <span x-show="l.agente" class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-bold bg-blue-100 text-blue-700" x-text="'🤝 '+l.agente"></span></div><div class="text-[11px] text-slate-400" x-text="l.pax"></div></td>
                <td class="p-2 whitespace-nowrap text-slate-500" x-text="l.trecho"></td>
                <td class="p-2 whitespace-nowrap text-slate-500" x-text="l.viagem"></td>
                <td class="p-2 whitespace-nowrap text-slate-500" x-text="l.vendido"></td>
                <td class="p-2 whitespace-nowrap text-slate-500" x-text="l.operador"></td>
                <td class="p-2 text-right font-bold whitespace-nowrap" x-text="m(l.total)"></td>
              </tr>
            </template>
            <tr x-show="!linhas.length"><td colspan="7" class="p-6 text-center text-slate-400">Nenhuma venda em aberto.</td></tr>
          </tbody>
        </table>
      </div>
      <div class="border-t bg-slate-50 px-4 py-3 flex flex-wrap items-center justify-end gap-x-6 gap-y-1 text-sm">
        <span class="text-slate-500">Vendido <b class="text-slate-800" x-text="m(total)"></b></span>
        <span class="text-slate-500">Comissões (agência + agentes) <b class="text-indigo-700" x-text="m(comissao)"></b></span>
        <span class="text-slate-500">Parte do proprietário <b class="text-amber-700 text-base" x-text="m(dono)"></b></span>
      </div>
    </div>
  </div>
</div>
@if($donosBalcao->isNotEmpty())
<div class="card overflow-hidden border border-amber-200">
  <div class="p-3 border-b bg-amber-50 flex items-center justify-between flex-wrap gap-2">
    <div class="font-bold text-sm text-amber-700">🚤 Acerto com proprietários (balcão)</div>
    <div class="text-[11px] text-amber-600">pague o proprietário do balcão vendido pra lancha dele; fica só a comissão da empresa</div>
  </div>
  <div class="divide-y text-sm">
    @foreach($donosBalcao as $d)
    <div class="flex items-center justify-between gap-2 p-3 flex-wrap">
      <div class="min-w-0">
        <div class="font-semibold truncate">🚤 {{ $d->emb->nome }}@if($d->dono) <span class="text-[11px] text-slate-400 font-normal">· proprietário {{ $d->dono }}</span>@endif</div>
        <div class="text-xs text-slate-500">{{ $d->qtd }} passagem(ns) de balcão · recebido <b>R$ {{ number_format($d->bruto ?? 0,2,',','.') }}</b> · comissão {{ (int)$d->emb->comissao_empresa_pct }}% = <b class="text-indigo-700">R$ {{ number_format($d->comissao ?? 0,2,',','.') }}</b></div>
        @if(($d->aberto ?? 0) > 0.009)
        <div class="text-xs text-slate-500 mt-0.5">🧾 {{ $d->qtdAberto }} faturado(s) <b class="text-blue-700">a receber</b> · <b>R$ {{ number_format($d->aberto,2,',','.') }}</b> <span class="text-[10px] text-slate-400">(neutro — não entra no total; quando pago no Financeiro entra como positivo na próxima prestação)</span></div>
        @endif
        @if(($d->avE ?? 0) > 0 || ($d->avS ?? 0) > 0)
        <div class="text-xs text-slate-500 mt-0.5">↕️ Avulsos desta prestação: @if($d->avE > 0)<b class="text-emerald-700">+ R$ {{ number_format($d->avE,2,',','.') }}</b> @endif @if($d->avS > 0)<b class="text-rose-600">− R$ {{ number_format($d->avS,2,',','.') }}</b> @endif <span class="text-[10px] text-slate-400">(entram no fechamento)</span></div>
        @endif
        @if(($d->qtdAg ?? 0) > 0)
        <div class="text-xs text-slate-500 mt-0.5">🤝 {{ $d->qtdAg }} passagem(ns) de <b class="text-blue-700">agente externo</b> · vendido <b>R$ {{ number_format($d->brutoAg,2,',','.') }}</b> <span class="text-[10px] text-slate-400">(neutro — o proprietário acerta direto com o agente, não entra no total)</span></div>
        @endif
        <button type="button" class="mt-1 inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                @click="$dispatch('ver-vendas', { id: {{ $d->emb->id }}, nome: @js($d->emb->nome) })">👁 Ver vendas ({{ $d->qtd + ($d->qtdAg ?? 0) }})</button>
        @if(($d->credProp ?? 0) > 0)
        <div class="text-xs text-sky-700 mt-0.5">💳 Proprietário já recebeu direto <b>R$ {{ number_format($d->credProp,2,',','.') }}</b> <span class="text-[10px] text-slate-400">(não passou pela agência — sai do valor a pagar; a agência fica só com a comissão)</span></div>
        @endif
        @if($d->debito > 0)<div class="text-[11px] text-rose-600 font-semibold mt-0.5">↩ proprietário deve R$ {{ number_format($d->debito,2,',','.') }} ({{ mb_strtolower(\App\Support\DebitoDono::rotulo(\App\Support\DebitoDono::itens((int) $d->emb->id))) }} de passagem já acertada) — abate no acerto</div>@endif
        @foreach(\App\Support\DebitoDono::itens((int) $d->emb->id) as $di)
        <div class="text-[11px] text-rose-600 mt-0.5 pl-3">• <b>{{ $di->tipo }}</b> · {{ $di->texto }}{{ $di->data ? ' · '.$di->data : '' }} · <b>R$ {{ number_format($di->valor,2,',','.') }}</b></div>
        @endforeach
      </div>
      <div class="flex items-center gap-2">
        <div class="text-right">
          @if($d->liquido < -0.009)
          <div class="text-lg font-bold text-rose-600">− R$ {{ number_format(abs($d->liquido),2,',','.') }}</div>
          <div class="text-[10px] text-rose-500 font-semibold">proprietário deve · desconta na próxima prestação</div>
          @else
          <div class="text-lg font-bold text-amber-700">R$ {{ number_format($d->liquido > 0 ? $d->liquido : 0,2,',','.') }}</div>
          @endif
          @if($d->debito > 0)<div class="text-[10px] text-slate-400">bruto {{ number_format($d->valor,2,',','.') }} − dívida {{ number_format($d->debito,2,',','.') }}</div>@endif
        </div>
        @if($d->valor > 0 || ($d->avE ?? 0) > 0 || ($d->avS ?? 0) > 0)
        <form method="post" action="{{ route('prestacoes.fechar', $d->emb) }}" class="flex flex-col items-stretch gap-1.5 min-w-[230px]"
              x-data="{ max: {{ $d->liquido > 0 ? $d->liquido : 0 }}, forma:'dinheiro', dividir:false, valorUnico:'{{ number_format($d->liquido > 0 ? $d->liquido : 0,2,',','.') }}', linhas:[{meio:'dinheiro',valor:''},{meio:'pix',valor:''}], parse(x){return parseFloat((x||'').toString().replace('.','').replace(',','.'))||0}, get soma(){return this.linhas.reduce((s,l)=>s+this.parse(l.valor),0)}, fmt(n){return (Math.round(n*100)/100).toFixed(2).replace('.',',')}, get splitJson(){return this.dividir?JSON.stringify(this.linhas.filter(l=>this.parse(l.valor)>0)):''} }"
              onsubmit="return confirm('Fechar a prestação de {{ $d->emb->nome }}? As passagens deste ciclo saem das vendas atuais e vão para o histórico (podem ser desfeitas lá). Devido R$ {{ number_format($d->liquido > 0 ? $d->liquido : 0,2,',','.') }}.')">
          @csrf
          <input type="hidden" name="viagem_id" value="{{ $viagemId }}">
          <input type="hidden" name="data_viagem" value="{{ $dataViagem }}">
          <input type="hidden" name="forma" :value="forma">
          <input type="hidden" name="formas_json" :value="splitJson">
          <input type="hidden" name="valor" :value="dividir ? '' : valorUnico">
          <div class="flex flex-wrap items-center gap-1 justify-end">
            <template x-if="!dividir">
              <div class="flex gap-1">
                <button type="button" @click="forma='dinheiro'" :class="forma==='dinheiro' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-300'" class="rounded-lg border px-2.5 py-1 text-xs font-semibold">Dinheiro</button>
                <button type="button" @click="forma='pix'" :class="forma==='pix' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-300'" class="rounded-lg border px-2.5 py-1 text-xs font-semibold">PIX</button>
                <button type="button" @click="forma='deposito'" :class="forma==='deposito' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-300'" class="rounded-lg border px-2.5 py-1 text-xs font-semibold">Depósito</button>
              </div>
            </template>
            <button type="button" @click="dividir=!dividir" :class="dividir ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-slate-600 border-slate-300'" class="rounded-lg border px-2.5 py-1 text-xs font-semibold" title="Pagar em mais de uma forma">⇄ Dividir</button>
          </div>
          <template x-if="!dividir">
            <div class="flex items-end gap-1.5 justify-end">
              <div>
                <label class="block text-[9px] uppercase tracking-wide text-slate-400 text-right">Pagar agora</label>
                <input x-model="valorUnico" inputmode="decimal" class="w-28 rounded-lg border border-slate-300 px-2 py-1 text-right text-sm">
              </div>
              <button class="btn btn-accent btn-sm whitespace-nowrap">Fechar prestação</button>
            </div>
          </template>
          <template x-if="dividir">
            <div data-rt-hold class="rounded-xl border border-slate-200 p-2 space-y-1 bg-slate-50">
              <template x-for="(l,i) in linhas" :key="i">
                <div class="flex items-center gap-1">
                  <select x-model="l.meio" class="rounded-lg border border-slate-300 bg-white px-1.5 py-1 text-xs">
                    <option value="dinheiro">Dinheiro</option>
                    <option value="pix">PIX</option>
                    <option value="deposito">Depósito</option>
                  </select>
                  <input x-model="l.valor" inputmode="decimal" placeholder="0,00" class="w-24 rounded-lg border border-slate-300 px-2 py-1 text-right text-xs">
                  <button type="button" @click="linhas.splice(i,1)" x-show="linhas.length>1" class="text-rose-500 px-1 text-sm">×</button>
                </div>
              </template>
              <div class="flex items-center justify-between pt-1">
                <button type="button" @click="linhas.push({meio:'dinheiro',valor:''})" class="text-xs text-indigo-600 font-semibold">+ forma</button>
                <span class="text-xs text-slate-500">Soma: R$ <b x-text="fmt(soma)"></b> / {{ number_format($d->liquido > 0 ? $d->liquido : 0,2,',','.') }}</span>
              </div>
              <button class="btn btn-accent btn-sm w-full whitespace-nowrap mt-1">Fechar prestação (dividido)</button>
            </div>
          </template>
        </form>
        @endif
      </div>
    </div>
    @endforeach
  </div>
</div>
@endif

<div x-data="{ open:false, id:0, numero:0, nome:'', tipo:'saida', lista:[], editId:0, delId:0, valor:'', meio:'dinheiro', desc:'',
      base: @js(url('/prestacoes')),
      abrir(d){ this.id=d.id; this.numero=d.numero; this.nome=d.nome; this.lista=d.avulsos||[]; this.novo(); this.open=true; },
      novo(){ this.editId=0; this.tipo='saida'; this.valor=''; this.meio='dinheiro'; this.desc=''; },
      editar(a){ this.editId=a.id; this.tipo=a.tipo; this.valor=Number(a.valor).toFixed(2).replace('.',','); this.meio=['dinheiro','pix','deposito'].includes(a.meio)?a.meio:'dinheiro'; this.desc=a.descricao||''; this.$nextTick(()=>this.$refs.valor && this.$refs.valor.focus()); },
      excluir(a){ if(!confirm('Excluir este avulso da prestação #'+this.numero+'? O saldo será recalculado.')) return; this.delId=a.id; this.$nextTick(()=>this.$refs.del.submit()); },
      m(n){ return 'R$ '+Number(n||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); } }"
     @avulso-prest.window="abrir($event.detail)" @keydown.escape.window="open=false">
  <div x-show="open" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-3" @click="open=false">
    <div class="bg-white rounded-2xl w-full max-w-md max-h-[92vh] overflow-auto" @click.stop>
      <div class="p-4 pb-2">
        <div class="font-bold text-lg">↕️ Entradas e saídas da prestação #<span x-text="numero"></span></div>
        <div class="text-[12px] text-slate-500">🚤 <span x-text="nome"></span> · qualquer alteração recalcula o saldo da prestação</div>
      </div>

      <div class="px-4" x-show="lista.length">
        <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">Já lançados nesta prestação</div>
        <div class="rounded-xl border border-slate-200 divide-y">
          <template x-for="a in lista" :key="a.id">
            <div class="flex items-center gap-2 px-2.5 py-2" :class="editId===a.id ? 'bg-amber-50' : ''">
              <span class="rounded px-1.5 py-0.5 text-[10px] font-bold" :class="a.tipo==='saida' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'" x-text="a.tipo==='saida' ? 'Saída' : 'Entrada'"></span>
              <div class="min-w-0 flex-1">
                <div class="text-sm font-medium truncate" x-text="a.motivo || '—'"></div>
                <div class="text-[11px] text-slate-400"><span x-text="a.data"></span> · <span class="capitalize" x-text="a.meio"></span></div>
              </div>
              <div class="text-sm font-bold whitespace-nowrap" :class="a.tipo==='saida' ? 'text-rose-600' : 'text-emerald-700'" x-text="(a.tipo==='saida' ? '− ' : '+ ') + m(a.valor)"></div>
              <button type="button" class="rounded-lg border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50" title="Editar" @click="editar(a)">✏️</button>
              <button type="button" class="rounded-lg border border-rose-200 px-2 py-1 text-xs text-rose-600 hover:bg-rose-50" title="Excluir" @click="excluir(a)">🗑</button>
            </div>
          </template>
        </div>
      </div>
      <form x-ref="del" method="post" :action="base + '/avulso/' + delId + '/excluir'" class="hidden">@csrf</form>

      <form method="post" :action="editId ? (base + '/avulso/' + editId + '/editar') : (base + '/' + id + '/avulso')" class="p-4 space-y-3">
        @csrf
        <input type="hidden" name="tipo_mov" :value="tipo">
        <div class="flex items-center justify-between">
          <div class="text-[10px] font-bold uppercase tracking-wide" :class="editId ? 'text-amber-700' : 'text-slate-400'" x-text="editId ? 'Editando o avulso selecionado' : 'Novo lançamento'"></div>
          <button type="button" x-show="editId" class="text-xs font-semibold text-indigo-600" @click="novo()">+ lançar outro</button>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <button type="button" @click="tipo='entrada'" :class="tipo==='entrada' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-300'" class="rounded-xl border py-2 text-sm font-bold">➕ Entrada</button>
          <button type="button" @click="tipo='saida'" :class="tipo==='saida' ? 'bg-rose-600 text-white border-rose-600' : 'bg-white text-slate-600 border-slate-300'" class="rounded-xl border py-2 text-sm font-bold">➖ Saída</button>
        </div>
        <div><label class="label">Valor</label><input name="valor" x-ref="valor" x-model="valor" class="input" inputmode="decimal" placeholder="0,00" required></div>
        <div><label class="label">Forma</label>
          <select name="meio" x-model="meio" class="input"><option value="dinheiro">Dinheiro</option><option value="pix">PIX</option><option value="deposito">Depósito</option></select></div>
        <div><label class="label">Motivo</label><input name="descricao" x-model="desc" class="input" maxlength="200" required placeholder="Ex.: combustível, vale, ajuste…"></div>
        <div class="flex gap-2 pt-1">
          <button type="button" class="btn btn-ghost flex-1" @click="open=false">Fechar</button>
          <button class="btn flex-1 text-white" :style="tipo==='entrada'?'background:#059669':'background:#e11d48'" x-text="editId ? 'Salvar correção' : 'Lançar'"></button>
        </div>
      </form>
    </div>
  </div>
</div>
@if(isset($prestacoesFechadas) && $prestacoesFechadas->isNotEmpty())
<div class="card overflow-hidden border border-slate-200">
  <div class="p-3 border-b bg-slate-50 flex items-center justify-between flex-wrap gap-2">
    <div class="font-bold text-sm text-slate-700">📚 Histórico de prestações</div>
    <div class="text-[11px] text-slate-400">prestações fechadas — mais recentes primeiro</div>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-[10px] uppercase tracking-wide text-slate-400 bg-slate-50">
        <tr>
          <th class="text-left p-2">Nº</th>
          <th class="text-left p-2">Viagem</th>
          <th class="text-left p-2">Prestação feita em</th>
          <th class="text-left p-2">Proprietário / Transporte</th>
          <th class="text-right p-2">Total</th>
          <th class="text-right p-2">Pago</th>
          <th class="text-right p-2">Saldo</th>
          <th class="text-left p-2">Forma</th>
          <th class="text-center p-2">Status</th>
          <th class="text-right p-2">Ações</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        @foreach($prestacoesFechadas as $pr)
        <tr>
          <td class="p-2 font-semibold whitespace-nowrap">#{{ $pr->numero }}</td>
          <td class="p-2 font-medium whitespace-nowrap">🗓 {{ $pr->viagens_txt ?? '—' }}</td>
          <td class="p-2 text-slate-500 whitespace-nowrap">{{ optional($pr->fechada_em)->format('d/m/Y H:i') }}</td>
          <td class="p-2">
            <div class="font-medium truncate">🚤 {{ optional($pr->embarcacao)->nome ?? 'Transporte' }}</div>
            <div class="text-[11px] text-slate-400">{{ $pr->qtd }} passagem(ns)</div>
          </td>
          <td class="p-2 text-right whitespace-nowrap">R$ {{ number_format($pr->total_devido,2,',','.') }}</td>
          <td class="p-2 text-right whitespace-nowrap text-emerald-700">R$ {{ number_format($pr->valor_pago,2,',','.') }}</td>
          <td class="p-2 text-right whitespace-nowrap {{ $pr->saldo > 0 ? 'text-rose-600 font-semibold' : 'text-slate-400' }}">R$ {{ number_format($pr->saldo,2,',','.') }}</td>
          <td class="p-2 capitalize text-slate-500 whitespace-nowrap">{{ $pr->forma }}</td>
          <td class="p-2 text-center"><span class="chip {{ $pr->status==='acertada' ? 'bg-emerald-100 text-emerald-700' : ($pr->status==='parcial' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">{{ $pr->statusLabel() }}</span></td>
          <td class="p-2">
            <div class="flex items-center gap-1 justify-end flex-wrap">
              <a href="{{ route('passagens.caixa-viagem', ['prestacao_id' => $pr->id]) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50" title="Abrir/imprimir a prestação de novo (o botão ← Voltar retorna pra cá)">🖨 Prestação</a>
              <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50" title="Lançar entrada/saída avulsa nesta prestação"
                      @click="$dispatch('avulso-prest', { id: {{ $pr->id }}, numero: {{ (int) $pr->numero }}, nome: @js(optional($pr->embarcacao)->nome ?? 'Transporte'), avulsos: @js($pr->avulsos_js ?? []) })">↕️ Entrada/Saída{{ count($pr->avulsos_js ?? []) ? ' ('.count($pr->avulsos_js).')' : '' }}</button>
              @if($pr->saldo > 0)
              <form method="post" action="{{ route('prestacoes.pagar', $pr) }}" class="flex items-center gap-1" onsubmit="return confirm('Baixar saldo da prestação #{{ $pr->numero }}?')">
                @csrf
                <input name="valor" value="{{ number_format($pr->saldo,2,',','.') }}" inputmode="decimal" class="w-20 rounded border border-slate-300 px-1.5 py-0.5 text-right text-xs">
                <button class="rounded-lg bg-amber-500 px-2 py-1 text-xs font-semibold text-white whitespace-nowrap">Baixar</button>
              </form>
              @endif
              <form method="post" action="{{ route('prestacoes.desfazer', $pr) }}" onsubmit="return confirm('Desfazer a prestação #{{ $pr->numero }}? As passagens voltam para EM ABERTO e os lançamentos são estornados.')">
                @csrf
                <button class="rounded-lg border border-rose-200 px-2 py-1 text-xs text-rose-600 hover:bg-rose-50" title="Desfazer">↩️</button>
              </form>
              @if(auth()->user()->podeExcluirPrestacao())
              <form method="post" action="{{ route('prestacoes.excluir', $pr) }}" onsubmit="return confirm('EXCLUIR a prestação #{{ $pr->numero }} do sistema?\n\nAs passagens voltam para EM ABERTO, os pagamentos são estornados e o registro é apagado de vez. Não dá para recuperar.')">
                @csrf
                <button class="rounded-lg bg-rose-600 px-2 py-1 text-xs text-white hover:bg-rose-700" title="Excluir do sistema">🗑</button>
              </form>
              @endif
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif

@if(($permiteDesfazer ?? false) && isset($acertosRecentes) && $acertosRecentes->isNotEmpty())
<div class="card overflow-hidden border border-slate-200">
  <div class="p-3 border-b bg-slate-50 flex items-center justify-between flex-wrap gap-2">
    <div class="font-bold text-sm text-slate-600">↩️ Desfazer acerto</div>
    <div class="text-[11px] text-slate-400">acertos dados no período — desfazer volta as passagens pra "em aberto" e estorna o pagamento</div>
  </div>
  <div class="divide-y text-sm">
    @foreach($acertosRecentes as $a)
    <div class="flex items-center justify-between gap-2 p-3 flex-wrap">
      <div class="min-w-0">
        <div class="font-semibold truncate">
          <span class="chip {{ $a->tipo==='agente' ? 'bg-indigo-100 text-indigo-700' : 'bg-amber-100 text-amber-700' }}">{{ $a->tipo==='agente' ? '🤝 agente' : '🚤 balcão' }}</span>
          {{ $a->emb->nome ?? 'Transporte' }}
        </div>
        <div class="text-xs text-slate-400">
          {{ \Illuminate\Support\Carbon::parse($a->quando)->format('d/m/Y H:i') }}@if($a->quem) · baixa por {{ $a->quem }}@endif
        </div>
      </div>
      <div class="flex items-center gap-2">
        <div class="text-right"><div class="text-lg font-bold text-slate-700">R$ {{ number_format($a->valor,2,',','.') }}</div></div>
        <form method="post" action="{{ route('meu-transporte.desfazer-acerto') }}" onsubmit="return confirm('Desfazer este acerto ({{ $a->tipo }} · {{ $a->emb->nome ?? '' }} · R$ {{ number_format($a->valor,2,',','.') }})?\n\nAs passagens voltam pra EM ABERTO e o pagamento é estornado.')">
          @csrf
          <input type="hidden" name="lote" value="{{ $a->lote }}">
          <button class="btn btn-ghost btn-sm text-rose-600 border border-rose-200 whitespace-nowrap">↩️ Desfazer</button>
        </form>
      </div>
    </div>
    @endforeach
  </div>
</div>
@endif
@if($donosBalcao->isEmpty())
<div class="card p-6 text-center text-slate-500"><div class="text-3xl mb-1">✅</div><div class="font-semibold">Nenhuma prestação em aberto{{ $transporteId ? ' para este transporte' : '' }}.</div><div class="text-[12px] text-slate-400">As vendas novas de balcão aparecem aqui para você fechar com o proprietário.</div></div>
@endif
@endif

@unless($focoPrest)
<div class="card overflow-hidden">
  <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">📊 Vendas por categoria <span class="text-[10px] font-normal text-slate-400">· só pra prestação (tudo entra normal no caixa)</span></div>
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-[11px] uppercase text-slate-500"><tr>
      <th class="p-2 text-left">Categoria</th><th class="p-2 text-right">Dinheiro</th><th class="p-2 text-right">PIX</th><th class="p-2 text-right">Débito</th><th class="p-2 text-right">Crédito</th><th class="p-2 text-right">A Receber</th><th class="p-2 text-right">Total</th>
    </tr></thead>
    <tbody class="divide-y">
      @foreach(['balcao'=>'🏬 Balcão','proprietario'=>'🚤 Proprietário','agente'=>'🤝 Agente interior'] as $ck=>$cl)
      @continue($ck==='agente' && !($ehRelatorio ?? false)) {{-- caixa: só balcão e proprietário; agente só no relatório --}}
      @php $c = $porCategoria[$ck] ?? []; @endphp
      <tr @class(['bg-emerald-50/50'=>$ck==='proprietario','bg-blue-50/50'=>$ck==='agente'])>
        <td class="p-2 font-semibold whitespace-nowrap">{{ $cl }} <span class="text-[11px] text-slate-400 font-normal">· {{ $c['qtd'] ?? 0 }}</span></td>
        <td class="p-2 text-right">{{ number_format($c['dinheiro'] ?? 0,2,',','.') }}</td>
        <td class="p-2 text-right">{{ number_format($c['pix'] ?? 0,2,',','.') }}</td>
        <td class="p-2 text-right">{{ number_format($c['debito'] ?? 0,2,',','.') }}</td>
        <td class="p-2 text-right">{{ number_format($c['credito'] ?? 0,2,',','.') }}</td>
        <td class="p-2 text-right text-amber-600">{{ number_format($c['areceber'] ?? 0,2,',','.') }}</td>
        <td class="p-2 text-right font-bold">{{ number_format($c['total'] ?? 0,2,',','.') }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
<div class="card overflow-hidden">
  <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">🚤 Por lancha / transporte</div>
  @php $tmap = $transportes->keyBy('id'); @endphp
  <table class="w-full text-sm"><thead class="bg-slate-50 text-[11px] uppercase text-slate-500"><tr><th class="p-3 text-left">Transporte</th>@foreach(['dinheiro','pix_manual','pix_auto','debito','credito'] as $m)<th class="p-3 text-right">{{ $meios[$m] }}</th>@endforeach<th class="p-3 text-right">Total</th></tr></thead>
  <tbody class="divide-y">
  @forelse($porLancha as $eid=>$d)
    <tr><td class="p-3 font-semibold">{{ $eid ? '🚤 '.(($tmap[$eid] ?? null)?->nome ?? ('Transporte '.$eid)) : '— sem transporte —' }}</td>@foreach(['dinheiro','pix_manual','pix_auto','debito','credito'] as $m)<td class="p-3 text-right">{{ number_format($d['meios'][$m] ?? 0,2,',','.') }}</td>@endforeach<td class="p-3 text-right font-bold">{{ number_format($d['total'],2,',','.') }}</td></tr>
  @empty <tr><td colspan="7" class="p-6 text-center text-slate-400">Sem movimento no período.</td></tr> @endforelse
  </tbody></table>
</div>
<div class="card overflow-hidden">
  <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Por operador</div>
  <table class="w-full text-sm"><thead class="bg-slate-50 text-[11px] uppercase text-slate-500"><tr><th class="p-3 text-left">Operador</th>@foreach(['dinheiro','pix_manual','pix_auto','debito','credito'] as $m)<th class="p-3 text-right">{{ $meios[$m] }}</th>@endforeach<th class="p-3 text-right">Total</th></tr></thead>
  <tbody class="divide-y">
  @forelse($porOperador as $uid=>$d)
    <tr><td class="p-3 font-semibold">{{ ($usuarios[$uid] ?? null)?->name ?? '—' }}</td>@foreach(['dinheiro','pix_manual','pix_auto','debito','credito'] as $m)<td class="p-3 text-right">{{ number_format($d['meios'][$m] ?? 0,2,',','.') }}</td>@endforeach<td class="p-3 text-right font-bold">{{ number_format($d['total'],2,',','.') }}</td></tr>
  @empty <tr><td colspan="7" class="p-6 text-center text-slate-400">Sem movimento no período.</td></tr> @endforelse
  </tbody></table>
</div>
</div>

<div class="card overflow-hidden">
  <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Lançamentos</div>
  <div class="divide-y">
  @if(isset($porModulo) && $porModulo->count())
  <div class="px-3 pt-3 flex flex-wrap gap-2">
    @foreach($porModulo as $mod=>$row)
      <span class="chip" style="background:{{ \App\Support\Modulos::hex($mod) }}20;color:{{ \App\Support\Modulos::hex($mod) }}">{{ \App\Support\Modulos::icone($mod) }} {{ \App\Support\Modulos::rotulo($mod) }}: R$ {{ number_format($row->valor,2,',','.') }} ({{ $row->qtd }})</span>
    @endforeach
  </div>
  @endif

  {{-- Passagens do periodo no modelo da prestacao (sem agente externo) --}}
  <div class="px-3 pt-3 pb-1 font-bold text-[11px] text-slate-400 uppercase">🧾 Passagens do período {{ ($ehRelatorio ?? false) ? '(com agente externo)' : '(sem agente externo)' }}</div>
  @include('passagens._manifesto_tabela', ['linhas' => $linhasCaixa])
  <div class="px-3 py-2 text-[10px] text-slate-500 flex flex-wrap gap-2 border-b">Legenda:
    <span style="background:#dbeafe;padding:1px 7px;border-radius:4px">Agente/Município</span>
    <span style="background:#dcfce7;padding:1px 7px;border-radius:4px">Idoso</span>
    <span style="background:#fef9c3;padding:1px 7px;border-radius:4px">PCD</span>
    <span style="background:#fee2e2;color:#b91c1c;padding:1px 7px;border-radius:4px">Cancelado/Devolvido</span>
  </div>
  @include('caixa._desfazer_transporte')

  @php $temOutros = collect($movimentos->items())->contains(fn($m)=>($m->modulo ?? '')!=='passagem'); @endphp
  @if($temOutros)<div class="px-3 pt-3 font-bold text-[11px] text-slate-400 uppercase">Encomendas / avulsos / outros</div>@endif
  @foreach($movimentos as $m)
    @if(($m->modulo ?? '')==='passagem') @continue @endif
    @php
      $rf = $m->resumoFonte();
      $pz = ($m->modulo === 'passagem') ? (($passMov ?? collect())[$m->origem_id] ?? null) : null;
      $corMov = null; $trechoMov = null;
      if ($pz) {
        $trechoMov = $pz->origem.' → '.$pz->destino;
        if ($pz->cancelada) $corMov = '#fee2e2';
        elseif ($pz->forma === 'faturado') $corMov = '#dbeafe';
        elseif ($pz->passageiros->contains(fn($x)=> mb_stripos((string)$x->tipo,'pcd')!==false || mb_stripos((string)$x->tipo,'defic')!==false)) $corMov = '#fef9c3';
        elseif ($pz->passageiros->contains(fn($x)=> mb_stripos((string)$x->tipo,'idoso')!==false)) $corMov = '#dcfce7';
      }
    @endphp
    <a href="{{ $rf['url'] ?? '#' }}" class="flex items-center gap-3 p-3 hover:brightness-95 text-sm"@if($corMov) style="background:{{ $corMov }}"@endif>
      <div class="w-14 text-xs text-slate-400">{{ $m->created_at->format('d/m') }}<br>{{ $m->created_at->format('H:i') }}</div>
      <div class="flex-1 min-w-0"><span class="chip" style="background:{{ \App\Support\Modulos::hex($m->modulo ?: 'encomenda') }}20;color:{{ \App\Support\Modulos::hex($m->modulo ?: 'encomenda') }}">{{ \App\Support\Modulos::icone($m->modulo ?: 'encomenda') }}</span> <b>#{{ $rf['numero'] }}</b> {{ $rf['nome'] }}@if($trechoMov) <span class="text-slate-500">· {{ $trechoMov }}</span>@endif<div class="text-xs text-slate-400">{{ $m->user?->name }} · {{ $m->momento==='cadastro'?'no cadastro':'na baixa' }}</div></div>
      <span class="chip bg-slate-100 text-slate-600">{{ $meios[$m->canal] ?? \App\Models\Pagamento::MEIOS[$m->meio] ?? $m->meio }}</span>@if($m->pago_proprietario ?? false)<span class="chip bg-emerald-100 text-emerald-700 font-semibold">🚤 proprietário</span>@endif
      <div class="font-bold whitespace-nowrap">R$ {{ number_format($m->valor,2,',','.') }}</div>
    </a>
  @endforeach
  </div>
  <div class="p-3">{{ $movimentos->links() }}</div>
</div>
@endunless
@endif
</div>
@include('partials.pulse', ['soft' => '#rt-root', 'intervalo' => 5000])
@endsection
