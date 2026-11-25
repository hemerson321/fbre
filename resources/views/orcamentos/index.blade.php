@extends('layouts.app')
@section('titulo','Orçamentos')
@section('conteudo')
@php
  $navy = '#13295e';
@endphp
<div class="max-w-3xl mx-auto space-y-4" x-data="orc()">

  <div class="flex items-center gap-2">
    <span class="text-2xl">📝</span>
    <div><div class="font-bold text-lg" style="color:{{ $navy }}">Orçamento de passagens</div>
    <div class="text-xs text-slate-400">Monte o orçamento e gere o PDF ou compartilhe no WhatsApp.</div></div>
  </div>

  {{-- Cliente --}}
  <div class="card p-4 space-y-3">
    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Cliente</div>
    <div class="grid sm:grid-cols-2 gap-3">
      <input x-model="cliente" placeholder="Nome do cliente / empresa" class="input">
      <input x-model="doc" placeholder="CNPJ/CPF ou A/C (opcional)" class="input">
    </div>
  </div>

  {{-- Viagem --}}
  <div class="card p-4 space-y-3">
    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Viagem</div>
    <div class="grid sm:grid-cols-2 gap-3">
      <select x-model="origem" @change="reprecificar()" class="input">
        <option value="">Origem…</option>
        @foreach($localidades as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach
      </select>
      <select x-model="destino" @change="reprecificar()" class="input">
        <option value="">Destino…</option>
        @foreach($localidades as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach
      </select>
      <input type="date" x-model="data" class="input">
      <select x-model="embId" class="input">
        <option value="">Embarcação (opcional)…</option>
        @foreach($embarcacoes as $e)<option value="{{ $e->id }}">{{ $e->nome }}</option>@endforeach
      </select>
    </div>
  </div>

  {{-- Itens / passageiros --}}
  <div class="card p-4 space-y-3">
    <div class="flex items-center justify-between">
      <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Passageiros</div>
      <button type="button" @click="addLinha()" class="text-sm font-bold" style="color:{{ $navy }}">+ Adicionar</button>
    </div>
    <div class="space-y-2">
      <template x-for="(l,idx) in linhas" :key="idx">
        <div class="flex items-center gap-2">
          <select x-model="l.tipo" @change="preencherUnit(l)" class="input flex-1 !min-h-[40px]">
            @foreach($tipos as $t)<option value="{{ $t->id }}">{{ $t->nome }}</option>@endforeach
          </select>
          <input type="number" min="1" x-model.number="l.qtd" class="input w-16 text-center !min-h-[40px]" title="Quantidade">
          <div class="flex items-center gap-1">
            <span class="text-xs text-slate-400">R$</span>
            <input type="number" min="0" step="0.01" x-model.number="l.unit" class="input w-24 text-right !min-h-[40px]" title="Valor unitário">
          </div>
          <div class="w-24 text-right font-bold text-sm tabular-nums" style="color:{{ $navy }}" x-text="brl(l.qtd*l.unit)"></div>
          <button type="button" @click="delLinha(idx)" class="text-rose-400 hover:text-rose-600 px-1" title="Remover">✕</button>
        </div>
      </template>
      <p x-show="!linhas.length" class="text-sm text-slate-400 py-2">Nenhuma linha. Clique em “+ Adicionar”.</p>
    </div>
    <div class="flex justify-between items-center pt-2 border-t">
      <span class="text-xs text-slate-400"><span x-text="totalPax()"></span> passageiro(s)</span>
      <div class="text-right"><div class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">Total</div>
        <div class="text-2xl font-extrabold tabular-nums" style="color:{{ $navy }}" x-text="brl(total())"></div></div>
    </div>
  </div>

  <div class="flex gap-2">
    <button type="button" @click="gerar()" class="btn flex-1 text-white font-bold" style="background:{{ $navy }}">📄 Gerar orçamento</button>
  </div>
  <p class="text-[11px] text-slate-400 text-center">No orçamento aberto você poderá imprimir, baixar o PDF ou enviar no WhatsApp.</p>
</div>

<script>
function orc(){
  const PRECOS = @json($precos);
  const TIPOS  = @json($tipos->map(fn($t)=>['id'=>$t->id,'nome'=>$t->nome,'normal'=>(bool)$t->eh_normal])->values());
  const EMBS   = @json($embarcacoes->map(fn($e)=>['id'=>$e->id,'nome'=>$e->nome])->values());
  const normalId = (TIPOS.find(t=>t.normal)||TIPOS[0]||{id:null}).id;
  const mapa = {}; PRECOS.forEach(p=>{ mapa[p.o+'||'+p.d+'||'+p.t] = p.v; });
  return {
    cliente:'', doc:'', origem:'', destino:'', data:'', embId:'',
    linhas:[ {tipo: String(normalId||''), qtd:1, unit:0} ],
    brl(v){ return (Number(v)||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'}); },
    tipoNome(id){ const t=TIPOS.find(x=>String(x.id)===String(id)); return t?t.nome:'Passagem'; },
    precoDe(tipoId){
      if(!this.origem||!this.destino) return null;
      const k=this.origem+'||'+this.destino+'||'+tipoId;
      if(mapa[k]!=null) return mapa[k];
      const kn=this.origem+'||'+this.destino+'||'+normalId;
      return mapa[kn]!=null ? mapa[kn] : null;
    },
    preencherUnit(l){ const v=this.precoDe(l.tipo); if(v!=null) l.unit=v; },
    reprecificar(){ this.linhas.forEach(l=>{ const v=this.precoDe(l.tipo); if(v!=null) l.unit=v; }); },
    addLinha(){ const l={tipo:String(normalId||''), qtd:1, unit:0}; const v=this.precoDe(l.tipo); if(v!=null) l.unit=v; this.linhas.push(l); },
    delLinha(i){ this.linhas.splice(i,1); },
    total(){ return this.linhas.reduce((s,l)=> s + (Number(l.qtd)||0)*(Number(l.unit)||0), 0); },
    totalPax(){ return this.linhas.reduce((s,l)=> s + (Number(l.qtd)||0), 0); },
    gerar(){
      const itens = this.linhas.filter(l=>(Number(l.qtd)||0)>0).map(l=>({ d:'Passagem '+this.tipoNome(l.tipo).toLowerCase(), q:Number(l.qtd)||0, u:Number(l.unit)||0 }));
      if(!itens.length){ alert('Adicione ao menos um passageiro.'); return; }
      const emb = (EMBS.find(e=>String(e.id)===String(this.embId))||{}).nome || '';
      const qs = new URLSearchParams();
      qs.set('cli', this.cliente||''); qs.set('doc', this.doc||'');
      qs.set('org', this.origem||''); qs.set('dst', this.destino||'');
      qs.set('data', this.data||''); qs.set('emb', emb);
      qs.set('itens', JSON.stringify(itens));
      window.FBRE.abrirDoc('{{ route('orcamentos.gerar') }}?'+qs.toString());
    }
  };
}
</script>
@endsection
