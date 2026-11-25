@extends('layouts.app')
@section('titulo','Orçamentos')
@section('conteudo')
@php $navy = '#13295e'; @endphp
<div class="max-w-3xl mx-auto space-y-4" x-data="orc()" x-init="init()">

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
      {{-- 1) origem / destino --}}
      <select x-model="origem" class="input">
        <option value="">Origem…</option>
        @foreach($localidades as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach
      </select>
      <select x-model="destino" class="input">
        <option value="">Destino…</option>
        @foreach($localidades as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach
      </select>

      {{-- 2) transporte (automático se só 1; escolhe se tiver vários) --}}
      <div>
        <select x-model="embId" class="input w-full" :disabled="!origem || !destino">
          <option value="" x-text="(!origem||!destino) ? 'Selecione origem e destino…' : 'Transporte…'"></option>
          <template x-for="t in opcoesTransporte()" :key="t.id"><option :value="String(t.id)" x-text="t.nome"></option></template>
        </select>
        <p class="text-[10px] text-slate-400 mt-1" x-show="origem && destino && autoUm()">✓ transporte único desse trecho — já selecionado</p>
        <p class="text-[10px] text-amber-500 mt-1" x-show="origem && destino && !transportes().length">Nenhuma linha cadastrada p/ esse trecho — escolha o transporte na lista.</p>
      </div>

      {{-- 3) data da viagem (lista do sistema p/ o transporte; ou digitar) --}}
      <div>
        <select x-show="embId && datas().length" x-model="dataSel" class="input w-full">
          <option value="">Viagem programada…</option>
          <template x-for="v in datas()" :key="v.d"><option :value="v.d" x-text="fmtData(v.d) + (v.rot ? ' · '+v.rot : '')"></option></template>
          <option value="__outra">Outra data (digitar)…</option>
        </select>
        <input x-show="!embId || !datas().length || dataSel==='__outra'" type="date" x-model="dataManual" class="input w-full" title="Data da viagem">
      </div>
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
        <div class="flex items-center gap-2 flex-wrap">
          <select x-model="l.tipo" @change="preencherUnit(l)" class="input !min-h-[40px]" style="flex:1 1 0;min-width:130px">
            @foreach($tipos as $t)<option value="{{ $t->id }}">{{ $t->nome }}</option>@endforeach
          </select>
          <input type="number" min="1" x-model.number="l.qtd" class="input !min-h-[40px] text-center" style="width:62px;flex:none" title="Quantidade">
          <div class="flex items-center gap-1" style="flex:none"><span class="text-xs text-slate-400">R$</span>
            <input type="number" min="0" step="0.01" x-model.number="l.unit" class="input !min-h-[40px] text-right" style="width:92px;flex:none" title="Valor unitário"></div>
          <div class="text-right font-bold text-sm tabular-nums" style="color:{{ $navy }};width:86px;flex:none" x-text="brl(l.qtd*l.unit)"></div>
          <button type="button" @click="delLinha(idx)" class="text-rose-400 hover:text-rose-600 px-1" style="flex:none" title="Remover">✕</button>
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

  <button type="button" @click="gerar()" class="btn w-full text-white font-bold" style="background:{{ $navy }}">📄 Gerar orçamento</button>
  <p class="text-[11px] text-slate-400 text-center">No orçamento aberto você poderá imprimir, baixar o PDF ou enviar no WhatsApp.</p>
</div>

<script>
function orc(){
  const LINHAS = @json($linhas);
  const DATAS  = @json($datasPorEmb);
  const EMBS   = @json($embarcacoes->map(fn($e)=>['id'=>$e->id,'nome'=>$e->nome])->values());
  const TIPOS  = @json($tipos->map(fn($t)=>['id'=>$t->id,'nome'=>$t->nome,'percentual'=>$t->percentual,'pct_sobre'=>$t->pct_sobre ?? null,'paga_taxa'=>(bool)$t->paga_taxa,'modo'=>$t->modo ?? 'valor','normal'=>(bool)$t->eh_normal,'tarifa_padrao'=>$t->tarifa_padrao ?? 0,'alimentacao_padrao'=>$t->alimentacao_padrao ?? 0])->values());
  const TRECHOS= @json($trechoPrecos);
  const TAXAS  = @json($taxas);
  const USAALIM = @json($usaAlim);
  const USAEMB  = @json($usaEmbarque);
  const normalId = (TIPOS.find(t=>t.normal)||TIPOS[0]||{id:null}).id;
  const low = s => String(s==null?'':s).trim().toLowerCase();
  const trechoMap={}, taxaMap={};
  TRECHOS.forEach(r=>{ trechoMap[low(r.origem)+'|'+low(r.destino)+'|'+r.tipo_passageiro_id]={tarifa:Number(r.tarifa)||0,alim:Number(r.alimentacao)||0,integral:Number(r.integral)||0}; });
  TAXAS.forEach(r=>{ taxaMap[low(r.origem)+'|'+low(r.destino)]=Number(r.taxa)||0; });
  const tipoById = id => TIPOS.find(t=>String(t.id)===String(id));
  return {
    cliente:'', doc:'', origem:'', destino:'', embId:'', dataSel:'', dataManual:'',
    linhas:[ {tipo:String(normalId||''), qtd:1, unit:0} ],
    init(){ this.$watch('origem',()=>this.autoTransporte()); this.$watch('destino',()=>this.autoTransporte()); this.$watch('embId',()=>{ this.dataSel=''; this.reprecificar(); }); this.reprecificar(); },
    brl(v){ return (Number(v)||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'}); },
    fmtData(d){ if(!d) return ''; const p=String(d).split('-'); return p.length===3?(p[2]+'/'+p[1]+'/'+p[0]):d; },
    tipoNome(id){ const t=tipoById(id); return t?t.nome:'Passagem'; },
    transportes(){ if(!this.origem||!this.destino) return []; const set={}; LINHAS.forEach(l=>{ const io=l.paradas.indexOf(this.origem), id=l.paradas.indexOf(this.destino); if(io>-1&&id>io) set[l.emb]=l.nome||('Transporte #'+l.emb); }); return Object.keys(set).map(id=>({id:Number(id),nome:set[id]})); },
    opcoesTransporte(){ if(!this.origem||!this.destino) return EMBS; const t=this.transportes(); return t.length?t:EMBS; },
    autoUm(){ return this.transportes().length===1; },
    autoTransporte(){ const t=this.transportes(); if(t.length===1){ this.embId=String(t[0].id); } else if(!this.opcoesTransporte().find(x=>String(x.id)===String(this.embId))){ this.embId=''; } this.reprecificar(); },
    datas(){ return DATAS[String(this.embId)]||DATAS[this.embId]||[]; },
    trechoGet(o,d,tipoId){ return trechoMap[low(o)+'|'+low(d)+'|'+tipoId]||null; },
    taxaDoTrecho(o,d){ const a=taxaMap[low(o)+'|'+low(d)], b=taxaMap[low(d)+'|'+low(o)]; return Number(a!=null?a:(b!=null?b:0))||0; },
    precoDoTipo(o,d,t){
      if(!t) return {tarifa:0,alim:0,integral:0};
      const c=this.trechoGet(o,d,t.id);
      if(c){ const tt=Number(c.tarifa)||0, aa=USAALIM?(Number(c.alim)||0):0; return {tarifa:tt,alim:aa,integral:Math.max(Number(c.integral)||0,tt+aa)}; }
      if(t.modo==='porcentagem'){
        const nc=this.trechoGet(o,d,normalId), n=tipoById(normalId);
        const baseT=nc?(Number(nc.tarifa)||0):(n?(Number(n.tarifa_padrao)||0):0);
        const baseA=nc?(Number(nc.alim)||0):(n?(Number(n.alimentacao_padrao)||0):0);
        const pct=(Number(t.percentual)||0)/100;
        const tarifa=Math.round(baseT*pct*100)/100;
        const alim=USAALIM?((t.pct_sobre==='tudo')?Math.round(baseA*pct*100)/100:Math.round(baseA*100)/100):0;
        return {tarifa,alim,integral:tarifa+alim};
      }
      const tt=Number(t.tarifa_padrao)||0, aa=USAALIM?(Number(t.alimentacao_padrao)||0):0;
      return {tarifa:tt,alim:aa,integral:tt+aa};
    },
    precoUnit(tipoId){
      if(!this.origem||!this.destino) return null;
      const t=tipoById(tipoId); if(!t) return null;
      const pr=this.precoDoTipo(this.origem,this.destino,t);
      let passagem,alim;
      if(t.modo==='manual'){ passagem=0; alim=0; }
      else if(pr.tarifa>0){ passagem=pr.tarifa; alim=pr.alim; }
      else { passagem=pr.integral; alim=0; }
      const taxa=(USAEMB && t.paga_taxa)?this.taxaDoTrecho(this.origem,this.destino):0;
      return Math.round((passagem+alim+taxa)*100)/100;
    },
    preencherUnit(l){ const v=this.precoUnit(l.tipo); if(v!==null) l.unit=v; },
    reprecificar(){ this.linhas.forEach(l=>{ const v=this.precoUnit(l.tipo); if(v!==null) l.unit=v; }); },
    addLinha(){ const l={tipo:String(normalId||''),qtd:1,unit:0}; const v=this.precoUnit(l.tipo); if(v!==null) l.unit=v; this.linhas.push(l); },
    delLinha(i){ this.linhas.splice(i,1); },
    total(){ return this.linhas.reduce((s,l)=>s+(Number(l.qtd)||0)*(Number(l.unit)||0),0); },
    totalPax(){ return this.linhas.reduce((s,l)=>s+(Number(l.qtd)||0),0); },
    embNome(){ const e=[...this.opcoesTransporte(),...EMBS].find(x=>String(x.id)===String(this.embId)); return e?e.nome:''; },
    gerar(){
      const itens=this.linhas.filter(l=>(Number(l.qtd)||0)>0).map(l=>({d:'Passagem '+this.tipoNome(l.tipo).toLowerCase(), q:Number(l.qtd)||0, u:Number(l.unit)||0}));
      if(!itens.length){ alert('Adicione ao menos um passageiro.'); return; }
      const data=(this.dataSel&&this.dataSel!=='__outra')?this.dataSel:this.dataManual;
      const qs=new URLSearchParams();
      qs.set('cli',this.cliente||''); qs.set('doc',this.doc||'');
      qs.set('org',this.origem||''); qs.set('dst',this.destino||'');
      qs.set('data',data||''); qs.set('emb',this.embNome()||'');
      qs.set('itens',JSON.stringify(itens));
      window.FBRE.abrirDoc('{{ route('orcamentos.gerar') }}?'+qs.toString());
    }
  };
}
</script>
@endsection
