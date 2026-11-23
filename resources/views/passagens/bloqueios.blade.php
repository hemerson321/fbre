@extends('layouts.app')
@section('titulo','Travar poltronas')
@section('conteudo')
@php
  $travadasMapa = $bloqueadas->filter(fn($b)=> $b->data_viagem===null || ($dataViagem && \Illuminate\Support\Carbon::parse($b->data_viagem)->toDateString()===$dataViagem))
                 ->map(fn($b)=>['poltrona'=>(string)$b->poltrona,'motivo'=>(string)($b->motivo ?? ''),'todas'=>$b->data_viagem===null])->values();
@endphp
<div class="max-w-3xl mx-auto space-y-4">

  <div class="flex items-center justify-between gap-2 flex-wrap">
    <h1 class="text-xl font-bold">🔒 Travar poltronas</h1>
    <a href="{{ route('passagens.index') }}" class="btn btn-ghost btn-sm">← Voltar</a>
  </div>
  <p class="text-sm text-slate-500">Trave uma poltrona pra ela <b>não ser vendida</b>. Deixe a data em branco pra travar em <b>todas as viagens</b> (ex.: assento quebrado, reservado); escolha uma data pra travar só <b>aquela viagem</b>.</p>

  @if(session('ok'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-3 py-2 text-sm">{{ session('ok') }}</div>@endif
  @if(session('erro'))<div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-3 py-2 text-sm">{{ session('erro') }}</div>@endif

  {{-- Filtro --}}
  <form method="get" class="card p-3 grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">
    <div>
      <label class="label">Transporte</label>
      <select name="embarcacao_id" class="input w-full" onchange="this.form.submit()">
        @foreach($embarcacoes as $e)<option value="{{ $e->id }}" @selected((int)$embId===(int)$e->id)>🚤 {{ $e->nome }}</option>@endforeach
      </select>
    </div>
    <div>
      <label class="label">Data da viagem <span class="text-slate-400 font-normal">(em branco = todas)</span></label>
      @php $diasCurto = \App\Models\Rota::DIAS_CURTO; @endphp
      <select name="data_viagem" class="input w-full" onchange="this.form.submit()">
        <option value="">— todas as viagens —</option>
        @foreach(($datasDisponiveis ?? collect()) as $d)
          @php $c = \Illuminate\Support\Carbon::parse($d); @endphp
          <option value="{{ $c->toDateString() }}" @selected((string)($dataViagem ?? '')===$c->toDateString())>📅 {{ $c->format('d/m/Y') }} · {{ $diasCurto[$c->dayOfWeek] ?? '' }}</option>
        @endforeach
      </select>
    </div>
    <div class="text-right"><button class="btn btn-sm">Aplicar</button></div>
  </form>

  @if(!$emb)
    <div class="card p-6 text-center text-slate-400">Nenhum transporte com poltronas cadastrado.</div>
  @else
  <div x-data="travarTool()">
    <form method="post" action="{{ route('passagens.bloquear') }}" @submit="return prep()">
      @csrf
      <input type="hidden" name="embarcacao_id" value="{{ $emb->id }}">
      <input type="hidden" name="data_viagem" value="{{ $dataViagem }}">
      <template x-for="p in sel" :key="p"><input type="hidden" name="poltronas[]" :value="p"></template>

      <div class="card p-3 space-y-3">
        <div class="flex items-center justify-between gap-2 flex-wrap">
          <div class="text-sm font-semibold text-slate-600">Toque nas poltronas que quer travar</div>
          <div class="flex items-center gap-3 text-[11px]">
            <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-white border-2 border-slate-300"></span> livre</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-slate-200 border-2 border-slate-300"></span> vendida</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-rose-100 border-2 border-rose-400"></span> travada</span>
            <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded border-2" style="background:#fde68a;border-color:#d97706"></span> selecionada</span>
          </div>
        </div>

        <template x-if="temLayout">
          <div class="mx-auto" style="max-width:360px">
            <div class="text-center text-[11px] font-bold text-slate-400 mb-1">⬆ PROA (frente)</div>
            <div class="rounded-t-[44px] rounded-b-2xl border-2 border-slate-300 bg-slate-50 px-2 py-3">
              <template x-for="(bloco,bi) in blocos" :key="bi">
                <div :class="bi>0 ? 'mt-2.5 pt-2.5 border-t border-dashed border-slate-300 space-y-1.5' : 'space-y-1.5'">
                  <template x-for="(f,ri) in bloco" :key="ri">
                    <div class="flex items-center justify-center gap-1">
                      <div class="flex gap-1"><template x-for="n in f.esq" :key="n">
                        <button type="button" class="min-w-[38px] h-9 px-0.5 rounded-t-lg rounded-b-md border-2 text-[11px] font-bold leading-none" :class="cls(n)" :disabled="vendida(n)" :title="tituloTravada(n)" @click="toggle(n)" x-text="n"></button>
                      </template></div>
                      <div class="w-4 text-center text-[9px] text-slate-300 select-none" x-show="f.dir.length">║</div>
                      <div class="flex gap-1"><template x-for="n in f.dir" :key="n">
                        <button type="button" class="min-w-[38px] h-9 px-0.5 rounded-t-lg rounded-b-md border-2 text-[11px] font-bold leading-none" :class="cls(n)" :disabled="vendida(n)" :title="tituloTravada(n)" @click="toggle(n)" x-text="n"></button>
                      </template></div>
                    </div>
                  </template>
                </div>
              </template>
            </div>
            <div class="text-center text-[11px] font-bold text-slate-400 mt-1">POPA (fundo) ⬇</div>
          </div>
        </template>

        <template x-if="!temLayout">
          <div>
            <label class="label">Poltronas (separadas por espaço ou vírgula)</label>
            <input type="text" x-model="manual" class="input w-full" placeholder="Ex.: 12 13 20A">
            <p class="text-[11px] text-slate-400 mt-1">Esse transporte não tem mapa de poltronas desenhado — digite os números.</p>
          </div>
        </template>

        <div class="mb-2" x-data="{esc:'todos'}">
          <label class="label">Travar para</label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <select name="escopo_tipo" x-model="esc" class="input w-full">
              <option value="todos">Todos (ninguém vende)</option>
              <option value="operadores">Só operadores (balcão)</option>
              <option value="revendedores">Só revendedores / agentes</option>
              <option value="ia">Só a IA (WhatsApp)</option>
              <option value="usuario">Uma pessoa específica…</option>
            </select>
            <select name="escopo_id" class="input w-full" x-show="esc==='usuario'" x-cloak>
              <option value="">— escolha a pessoa —</option>
              @foreach($usuarios as $us)<option value="{{ $us->id }}">{{ $us->name }}{{ $us->is_revendedor ? ' (revendedor)' : '' }}</option>@endforeach
            </select>
          </div>
          <p class="text-[11px] text-slate-400 mt-1">Ex.: "Só a IA" reserva a poltrona pro balcão (o robô não vende). "Uma pessoa" trava só pra aquele operador/revendedor.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 items-end">
          <div><label class="label">Observação (opcional)</label><input type="text" name="motivo" class="input w-full" placeholder="Ex.: dono pediu pra travar, assento quebrado, reservado"></div>
          <div class="text-right">
            <span class="text-sm text-slate-500 mr-2" x-show="count()>0" x-text="count()+' selecionada(s)'"></span>
            <button class="btn btn-accent btn-sm" :disabled="count()===0">🔒 Travar</button>
          </div>
        </div>
      </div>
    </form>

    {{-- Lista das travadas --}}
    <div class="card divide-y mt-4">
      <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Poltronas travadas neste transporte ({{ $bloqueadas->count() }})</div>
      @forelse($bloqueadas as $b)
        <div class="p-3 flex items-center justify-between gap-2 flex-wrap">
          <div class="min-w-0">
            <span class="chip bg-rose-100 text-rose-700 font-bold">🔒 {{ $b->poltrona }}</span>
            <span class="text-sm text-slate-600 ml-1">
              @if($b->data_viagem)viagem {{ \Illuminate\Support\Carbon::parse($b->data_viagem)->format('d/m/Y') }}@else<b>todas as viagens</b>@endif
            </span>
            <span class="chip bg-slate-100 text-slate-600 text-[10px] ml-1">👥 {{ $b->escopoLabel() }}</span>
            @if($b->motivo)<span class="text-xs text-slate-400">· {{ $b->motivo }}</span>@endif
          </div>
          <form method="post" action="{{ route('passagens.desbloquear') }}" onsubmit="return confirm('Liberar a poltrona {{ $b->poltrona }} pra venda?')">
            @csrf
            <input type="hidden" name="id" value="{{ $b->id }}">
            <button class="btn btn-ghost btn-sm text-emerald-600 border border-emerald-200">🔓 Liberar</button>
          </form>
        </div>
      @empty
        <div class="p-6 text-center text-slate-400">Nenhuma poltrona travada{{ $dataViagem ? '' : '' }}.</div>
      @endforelse
    </div>
  </div>
  @endif
</div>

@if($emb)
<script>
  function travarTool(){
    return {
      layout: @js((string)($emb->poltronas_lista ?? '')),
      vendidas: @js(array_values($vendidas)),
      travadas: @js($travadasMapa->all()),
      sel: [],
      manual: '',
      get temLayout(){ return (this.layout||'').trim().length>0; },
      get blocos(){
        const txt=(this.layout||'').replace(/\r/g,'');
        return txt.split(/\n[ \t]*\n/).map(b=>
          b.split('\n').filter(l=>l.trim()).map(linha=>{
            const p=linha.split('|');
            return { esq:(p[0]||'').trim().split(/\s+/).filter(Boolean), dir:(p[1]||'').trim().split(/\s+/).filter(Boolean) };
          })
        ).filter(b=>b.length);
      },
      vendida(n){ return this.vendidas.map(String).includes(String(n)); },
      bloqueada(n){ return (this.travadas||[]).some(t=>String(t.poltrona)===String(n)); },
      tituloTravada(n){ const t=(this.travadas||[]).find(t=>String(t.poltrona)===String(n)); if(!t) return ''; return '🔒 '+(t.motivo||'Travada')+(t.todas?' · todas as viagens':' · esta viagem'); },
      selecionada(n){ return this.sel.map(String).includes(String(n)); },
      toggle(n){
        n=String(n);
        if(this.vendida(n)) return;
        if(this.bloqueada(n)) return; // já travada: libere pela lista abaixo
        const i=this.sel.map(String).indexOf(n);
        if(i>=0) this.sel.splice(i,1); else this.sel.push(n);
      },
      cls(n){
        if(this.selecionada(n)) return 'text-amber-900';
        if(this.bloqueada(n)) return 'bg-rose-100 border-rose-400 text-rose-700 cursor-not-allowed';
        if(this.vendida(n)) return 'bg-slate-200 border-slate-300 text-slate-400 cursor-not-allowed';
        return 'bg-white border-slate-300 text-slate-600 hover:border-amber-400';
      },
      count(){ if(!this.temLayout){ return this._manualList().length; } return this.sel.length; },
      _manualList(){ return (this.manual||'').split(/[\s,;]+/).map(s=>s.trim()).filter(Boolean); },
      prep(){
        if(!this.temLayout){ this.sel = this._manualList(); }
        if(this.sel.length===0){ alert('Escolha pelo menos uma poltrona.'); return false; }
        return true;
      },
    };
  }
</script>
<style>[x-cloak]{display:none!important}
  button[class*="text-amber-900"]{background:#fde68a;border-color:#d97706}
</style>
@endif
@endsection
