@extends('layouts.app')
@section('titulo','Vender suíte / camarote')
@push('head')
<style type="text/tailwindcss">
  .sec { @apply rounded-2xl overflow-hidden flex flex-col; background:#fff; box-shadow: 0 1px 2px rgba(15,23,42,.06), 0 8px 24px -12px rgba(15,23,42,.18); }
  .sec-h { @apply flex items-center gap-2.5 px-3.5 py-2 text-white; background: linear-gradient(90deg, var(--cor1), color-mix(in srgb, var(--cor1) 70%, var(--cor2))); }
  .sec-h .n { @apply inline-flex w-6 h-6 rounded-full items-center justify-center text-[12px] font-extrabold text-slate-900; background: var(--cor2); }
  .sec-h .t { @apply text-[13px] font-bold uppercase tracking-wide flex-1; }
  .sec-b { @apply p-3 flex-1 flex flex-col gap-2.5; }
  .field { @apply rounded-xl bg-slate-50 border border-slate-200 px-3 py-2 text-[15px] w-full transition; min-height: 44px; }
  .field:focus { @apply outline-none bg-white; border-color: var(--cor2); box-shadow: 0 0 0 3px color-mix(in srgb, var(--cor2) 25%, transparent); }
  .lbl { @apply block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1; }
  .forma { @apply flex-1 rounded-xl border-2 py-2.5 text-[13px] font-bold text-center cursor-pointer transition text-slate-500 border-slate-200 bg-white; }
  .forma.on { background: var(--cor1); color:#fff; border-color: var(--cor1); }
  .pessoa { @apply rounded-xl p-2.5 space-y-1.5 border; background: color-mix(in srgb, var(--cor2) 7%, white); border-color: color-mix(in srgb, var(--cor2) 30%, white); }
  @media (min-width:1024px){
    .field{min-height:36px;font-size:14px;padding-top:.3rem;padding-bottom:.3rem}
    .sec-b{padding:.55rem .6rem;gap:.45rem}
    .sec-h{padding-top:.3rem;padding-bottom:.3rem}
    .lbl{margin-bottom:.1rem;font-size:10px}
    .forma{padding-top:.4rem;padding-bottom:.4rem}
  }
</style>
@endpush
@section('conteudo')
@php $emp=\App\Support\Tenant::empresa(); $corMod=(bool)($emp?->config('cores_por_modulo', true) ?? true); @endphp
<form method="post" action="{{ route('passagens.camarote.store') }}" x-data="vendaCamarote()" @submit="prep()"
      class="mx-auto max-w-[1440px] space-y-2"
      style="--cor1:{{ $corMod ? '#7c3aed' : ($emp?->cor_primaria ?? '#0b1a33') }};--cor2:{{ $corMod ? '#a78bfa' : ($emp?->cor_secundaria ?? '#00a8e8') }}">
@csrf
<input type="hidden" name="camarote_id" :value="cam?cam.id:''">
<input type="hidden" name="rota_id" :value="rotaId||''">
<input type="hidden" name="viagem_id" :value="viagemId||''">

{{-- cabeçalho --}}
<div class="flex items-center justify-between gap-3 flex-wrap">
  <div class="flex items-center gap-x-4 gap-y-1 flex-wrap">
    <h1 class="text-lg font-extrabold" style="color:var(--cor1)">🛏️ Vender Suíte / Camarote</h1>
    <template x-if="cam">
      <span class="text-[11px] font-bold rounded-lg px-2 py-0.5 text-white" style="background:var(--cor1)" x-text="cam.nome+' · até '+cam.capacidade"></span>
    </template>
    <template x-if="viagemLabel">
      <div class="flex items-center gap-1.5">
        <span class="lbl !mb-0 whitespace-nowrap">Viagem</span>
        <span class="field !min-h-0 !py-1 text-sm font-semibold whitespace-nowrap" style="width:auto">📅 <span x-text="viagemLabel"></span></span>
      </div>
    </template>
  </div>
  <a href="{{ route('passagens.create') }}" class="text-sm text-slate-500 hover:underline">Passagem comum →</a>
</div>

@if(collect($camarotes)->isEmpty())
  <div class="sec"><div class="sec-b text-sm text-amber-700">
    Nenhuma suíte/camarote cadastrada. <a href="{{ route('admin.camarotes') }}" class="underline font-semibold">Cadastrar agora</a>.
  </div></div>
@else
<div class="grid gap-2 lg:grid-cols-12 items-start">

  {{-- COLUNA 1: suíte/rota + comprador --}}
  <div class="lg:col-span-3 space-y-2">
    <section class="sec shrink-0">
      <div class="sec-h"><span class="n">1</span><span class="t">Suíte / Rota</span></div>
      <div class="sec-b">
        <div><label class="lbl">Suíte / Camarote</label>
          <select class="field" x-model.number="camId" @change="trocaCam()" required>
            <option value="">—</option>
            <template x-for="c in camarotes" :key="c.id"><option :value="c.id" x-text="c.nome+' (até '+c.capacidade+')'"></option></template>
          </select></div>
        <template x-if="cam && cam.descricao">
          <div class="text-[11px] text-slate-500 bg-slate-50 rounded-lg p-2 whitespace-pre-line" x-text="cam.descricao"></div>
        </template>
        <div class="grid grid-cols-2 gap-2">
          <div><label class="lbl">Origem</label>
            <select class="field" x-model="origem" @change="onTrecho()" required>
              <option value="">—</option>
              @foreach($localidades as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach
            </select></div>
          <div><label class="lbl">Destino</label>
            <select class="field" x-model="destino" @change="onTrecho()" required>
              <option value="">—</option>
              @foreach($localidades as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach
            </select></div>
        </div>
        <div><label class="lbl">Data da viagem</label>
          <input type="hidden" name="data_viagem" :value="dataViagem">
          <div class="relative">
            <button type="button" @click="abrirCal()" class="field text-left w-full" :class="viagemDia===null?'text-slate-400':''">
              📅 <span x-text="viagemLabel || (viagemDia===null ? 'escolha origem/destino' : 'escolher')"></span>
            </button>
            <div x-show="calAberto" x-cloak @click.outside="calAberto=false" class="absolute top-full left-0 mt-1 z-40 bg-white rounded-xl border shadow-xl p-2 w-64 text-slate-700">
              <div class="flex items-center justify-between mb-1">
                <button type="button" @click="mudarMes(-1)" class="w-7 h-7 rounded hover:bg-slate-100 text-lg leading-none">‹</button>
                <div class="text-sm font-bold capitalize" x-text="nomeMes"></div>
                <button type="button" @click="mudarMes(1)" class="w-7 h-7 rounded hover:bg-slate-100 text-lg leading-none">›</button>
              </div>
              <div class="grid grid-cols-7 text-center text-[10px] font-bold text-slate-400 mb-1">
                <template x-for="(d,i) in ['D','S','T','Q','Q','S','S']" :key="i"><div x-text="d"></div></template>
              </div>
              <div class="grid grid-cols-7 gap-0.5">
                <template x-for="c in calDias" :key="c.iso">
                  <button type="button" :disabled="!c.valido" @click="c.valido && escolherDia(c.iso)"
                    class="h-8 rounded text-sm transition"
                    :class="c.sel ? 'text-white font-bold' : (c.valido ? 'font-bold hover:brightness-95' : (c.outroMes ? 'text-slate-200' : 'text-slate-300 cursor-not-allowed'))"
                    :style="c.sel ? 'background:var(--cor1)' : (c.valido ? 'color:var(--cor1);background:color-mix(in srgb, var(--cor2) 20%, white)' : '')"
                    x-text="c.dia"></button>
                </template>
              </div>
              <div class="text-[10px] text-slate-400 mt-1 text-center">Só os dias de viagem da rota ficam disponíveis.</div>
            </div>
          </div>
        </div>
        <template x-if="cam && origem && destino">
          <div>
            <template x-if="base!==null">
              <div class="text-[11px] font-semibold" style="color:var(--cor1)">💲 Camarote <span x-text="origem"></span>→<span x-text="destino"></span>: R$ <span x-text="fmt(base)"></span> <span class="text-slate-400 font-normal">(até <span x-text="cam.capacidade"></span>)</span></div>
            </template>
            <template x-if="base===null">
              <div class="text-[11px] text-red-600">Sem preço nesse trecho. <a href="{{ route('admin.camarotes') }}" class="underline">Cadastrar</a>.</div>
            </template>
          </div>
        </template>
      </div>
    </section>

    <section class="sec shrink-0">
      <div class="sec-h"><span class="n">2</span><span class="t">Comprador (opcional)</span>
        <button type="button" @click="compradorDoTitular()" class="text-[10px] bg-white/20 rounded px-1.5 py-0.5">1º ocupante</button></div>
      <div class="sec-b">
        <div><label class="lbl">Nome</label><input class="field" name="comprador_nome" x-model="comprador.nome" placeholder="Quem está comprando"></div>
        <div class="grid grid-cols-2 gap-2">
          <div><label class="lbl">WhatsApp</label><input class="field" name="comprador_telefone" x-model="comprador.telefone"></div>
          <div><label class="lbl">CPF</label><input class="field" name="comprador_documento" x-model="comprador.documento"></div>
        </div>
      </div>
    </section>
  </div>

  {{-- COLUNA 2: ocupantes --}}
  <div class="lg:col-span-6">
    <section class="sec">
      <div class="sec-h"><span class="n">3</span><span class="t">Ocupantes (um bilhete por pessoa)</span>
        <span class="text-[11px] bg-white/20 rounded px-1.5 py-0.5" x-text="ocupantes.length+' / '+(cam?cam.capacidade:'?')"></span>
        <button type="button" @click="addOcup()" class="text-[11px] bg-white/25 rounded px-2 py-0.5 font-bold">＋ pessoa</button></div>
      <div class="sec-b">
        <template x-for="(o,i) in ocupantes" :key="i">
          <div class="pessoa">
            <div class="flex items-center justify-between">
              <span class="inline-flex items-center gap-1.5">
                <span class="w-6 h-6 rounded-full inline-flex items-center justify-center text-[12px] font-extrabold text-white" style="background:var(--cor1)" x-text="i+1"></span>
                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full"
                      :class="papel(i)==='Titular'?'bg-violet-100 text-violet-700':(papel(i)==='Adicional'?'bg-amber-100 text-amber-700':'bg-slate-100 text-slate-600')"
                      x-text="papel(i)"></span>
              </span>
              <button type="button" x-show="ocupantes.length>1" @click="ocupantes.splice(i,1)" class="text-red-500 text-xl leading-none">×</button>
            </div>
            <input class="field" :name="`ocupantes[${i}][nome]`" x-model="o.nome" placeholder="Nome do passageiro" required>
            <div class="grid grid-cols-2 gap-2">
              <input class="field" :name="`ocupantes[${i}][documento]`" x-model="o.documento" placeholder="RG/CPF">
              <input class="field" :name="`ocupantes[${i}][nascimento]`" x-model="o.nascimento" type="date">
            </div>
            <div class="grid grid-cols-2 gap-2 items-end">
              <template x-if="papel(i)==='Adicional'">
                <div><label class="lbl text-amber-600">Adicional (R$)</label>
                  <input class="field text-right font-semibold" type="number" step="0.01" min="0" x-model.number="o.adicional"></div>
              </template>
              <label class="flex items-center gap-2 text-[13px] text-slate-600 pb-2"><input type="checkbox" :name="`ocupantes[${i}][checkin]`" value="1" x-model="o.checkin" class="w-5 h-5 rounded"> Check-in</label>
            </div>
          </div>
        </template>
        <input class="field" name="observacao" x-model="observacao" placeholder="Observação (opcional)">
      </div>
    </section>
  </div>

  {{-- COLUNA 3: pagamento --}}
  <div class="lg:col-span-3 space-y-2">
    <section class="sec shrink-0">
      <div class="sec-h"><span class="n">4</span><span class="t">Pagamento</span></div>
      <div class="sec-b">
        <div class="flex gap-1.5">
          @foreach(\App\Models\Passagem::FORMAS as $k=>$l)
          @if(empty($soAvista) || $k==='avista')
          <label class="forma" :class="forma==='{{ $k }}' && 'on'"><input type="radio" value="{{ $k }}" x-model="forma" class="sr-only"> {{ $l }}</label>
          @endif
          @endforeach
        </div>
        <input type="hidden" name="forma" :value="forma">
        @if(!empty($soAvista))
        <p class="text-xs text-slate-500 -mt-1">Revendedor: venda à vista.</p>
        @endif

        <div x-show="forma==='avista'" class="grid grid-cols-2 gap-2">
          <template x-for="m in meios" :key="m.k">
            <div><label class="lbl" x-text="m.l"></label><input class="field text-right font-semibold" x-money="pag[m.k]"></div>
          </template>
          <div><label class="lbl">Desconto</label><input class="field text-right" x-money="desconto"></div>
          <div class="flex items-end"><button type="button" class="w-full rounded-xl border-2 font-bold text-[13px] py-2 transition" style="border-color:var(--cor1);color:var(--cor1)" @click="pag.dinheiro=+Math.max(total()-pagoSemDinheiro(),0).toFixed(2)">Tudo em dinheiro</button></div>
        </div>
        <p x-show="forma==='faturado'" class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-xl p-3">Faturado: nada é recebido agora.</p>

        {{-- pagamentos ocultos --}}
        <template x-for="m in meios" :key="'h'+m.k">
          <span class="hidden">
            <input type="hidden" :name="`pagamentos[${m.k}][meio]`" :value="m.k">
            <input type="hidden" :name="`pagamentos[${m.k}][valor]`" :value="(forma==='avista'?(pag[m.k]||0):0)">
          </span>
        </template>
        <input type="hidden" name="desconto" :value="desconto||0">

        <div class="rounded-2xl p-2.5 text-white" style="background: linear-gradient(160deg, var(--cor1), color-mix(in srgb, var(--cor1) 75%, black))">
          <div class="flex justify-between text-xs opacity-75"><span>Camarote</span><span x-text="'R$ '+fmt(base||0)"></span></div>
          <div class="flex justify-between text-xs opacity-75" x-show="adicionalTotal()>0"><span>Adicionais</span><span x-text="'R$ '+fmt(adicionalTotal())"></span></div>
          <div class="flex justify-between text-xs opacity-75" x-show="(desconto||0)>0"><span>Desconto</span><span x-text="'- R$ '+fmt(desconto||0)"></span></div>
          <div class="flex justify-between items-baseline mt-0.5"><span class="text-sm font-semibold opacity-90">Total</span><span class="text-xl font-extrabold tracking-tight" x-text="'R$ '+fmt(total())"></span></div>
          <div class="h-px bg-white/20 my-1"></div>
          <div class="flex justify-between text-sm"><span>Pago</span><span x-text="'R$ '+fmt(pago())"></span></div>
          <div class="flex justify-between items-center text-sm font-bold mt-0.5">
            <span>Devido</span>
            <span class="rounded-lg px-2 py-0.5" :class="devido()>0.009 ? 'bg-amber-400 text-slate-900' : 'bg-emerald-400 text-slate-900'" x-text="devido()>0.009 ? ('R$ '+fmt(devido())) : 'Pago ✓'"></span>
          </div>
        </div>
      </div>
    </section>

    <section class="sec shrink-0">
      <div class="p-2.5 space-y-2">
        <div class="flex flex-wrap gap-x-3 gap-y-1 text-[13px] text-slate-600">
          <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" name="imprimir" value="navegador" x-model="imprimir" class="accent-[var(--cor1)]"> Imprimir</label>
          <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" name="imprimir" value="nao" x-model="imprimir" class="accent-[var(--cor1)]"> Não imprimir</label>
        </div>
        <button class="w-full rounded-xl py-3 text-base font-extrabold text-slate-900 shadow-lg transition hover:brightness-105 active:scale-[.98] disabled:opacity-40" style="background:var(--cor2)" :disabled="!cam || base===null || !origem || !destino">
          💾 <span x-text="(base===null&&cam&&origem&&destino)?'Sem preço p/ esse trecho':'Salvar camarote'"></span>
        </button>
      </div>
    </section>
  </div>
</div>
@endif
</form>

<script>
function vendaCamarote(){
  return {
    camarotes: @json($camarotes),
    linhas: @json($linhas),
    camId:'', cam:null,
    origem: @json($ultimaOrigem ?? ''), destino:'',
    dataViagem:'', rotaId:'', viagemId:'', viagemDia:null, viagemHora:'', viagemLabel:'',
    diasCurto:['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'], calAberto:false, calMes:null,
    base:null,
    ocupantes:[{nome:'',documento:'',nascimento:'',adicional:0,checkin:{{ $checkinAuto ? 'true':'false' }}}],
    comprador:{nome:'',telefone:'',documento:''},
    observacao:'',
    forma:'avista',
    meios:[{k:'dinheiro',l:'Dinheiro'},{k:'pix',l:'PIX'},{k:'debito',l:'Débito'},{k:'credito',l:'Crédito'}],
    pag:{dinheiro:0,pix:0,debito:0,credito:0}, desconto:0,
    imprimir:'navegador',
    fmt(v){ return (Number(v)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); },
    trocaCam(){ this.cam=this.camarotes.find(c=>c.id===this.camId)||null; this.ajustarOcupantes(); this.recalc(); },
    onTrecho(){ this.recalc(); this.resolveData(); },
    recalc(){
      if(this.cam && this.origem && this.destino){
        const k=(this.origem+'|'+this.destino).toLowerCase();
        const v=this.cam.precos[k];
        this.base=(v===undefined||v===null)?null:Number(v);
      } else { this.base=null; }
    },
    papel(i){ if(i===0) return 'Titular'; if(this.cam && i < this.cam.capacidade) return 'Incluído'; return 'Adicional'; },
    novoOcupante(){ const ad=this.cam?Number(this.cam.adicional||0):0; return {nome:'',documento:'',nascimento:'',adicional:ad,checkin:{{ $checkinAuto ? 'true':'false' }}}; },
    addOcup(){ this.ocupantes.push(this.novoOcupante()); },
    ajustarOcupantes(){ if(!this.cam) return; const cap=Math.max(Number(this.cam.capacidade)||1,1); while(this.ocupantes.length<cap) this.ocupantes.push(this.novoOcupante()); }, 
    adicionalTotal(){ let s=0; this.ocupantes.forEach((o,i)=>{ if(this.papel(i)==='Adicional') s+=Number(o.adicional||0); }); return s; },
    total(){ return Math.max((this.base||0)+this.adicionalTotal()-(Number(this.desconto)||0),0); },
    pagoSemDinheiro(){ return (Number(this.pag.pix)||0)+(Number(this.pag.debito)||0)+(Number(this.pag.credito)||0); },
    pago(){ if(this.forma!=='avista') return 0; return (Number(this.pag.dinheiro)||0)+this.pagoSemDinheiro(); },
    devido(){ return Math.max(this.total()-this.pago(),0); },
    compradorDoTitular(){ const t=this.ocupantes[0]; if(t){ this.comprador.nome=t.nome; this.comprador.documento=t.documento||''; } },
    proximaData(dia){ const d=new Date(); d.setHours(0,0,0,0); for(let i=0;i<70;i++){ if(d.getDay()===dia){ const y=d.getFullYear(),m=String(d.getMonth()+1).padStart(2,'0'),dd=String(d.getDate()).padStart(2,'0'); return `${y}-${m}-${dd}`; } d.setDate(d.getDate()+1);} return ''; },
    rotuloData(iso){ if(!iso) return ''; const [y,m,dd]=iso.split('-').map(Number); const d=new Date(y,m-1,dd); const dias=['Dom','Seg','Ter','Qua','Qui','Sex','Sáb']; return dias[d.getDay()]+' '+String(dd).padStart(2,'0')+'/'+String(m).padStart(2,'0')+(this.viagemHora?' '+this.viagemHora:''); },
    resolveData(forcar){
      const idx=(L,c)=>L.paradas.findIndex(p=>(p.n||'').toLowerCase()===(c||'').toLowerCase());
      let linha=null;
      if(this.rotaId){ linha=this.linhas.find(L=>L.id===this.rotaId)||null; }
      if(!linha && this.origem && this.destino){
        linha=this.linhas.find(L=>{const io=idx(L,this.origem),id=idx(L,this.destino);return io>=0&&id>=0&&io<id;})
            ||this.linhas.find(L=>idx(L,this.origem)>=0);
      }
      if(!linha){ this.viagemDia=null; this.viagemHora=''; this.viagemLabel=''; return; }
      this.rotaId=linha.id;
      const p=linha.paradas.find(pp=>(pp.n||'').toLowerCase()===(this.origem||'').toLowerCase());
      if(!p || p.d===null || p.d===undefined || p.d===''){ this.viagemDia=null; this.viagemHora=''; this.viagemLabel=''; return; }
      this.viagemDia=Number(p.d); this.viagemHora=p.h||'';
      if(forcar || !this.dataViagem){ this.dataViagem=this.proximaData(this.viagemDia); }
      this.viagemLabel=this.rotuloData(this.dataViagem);
    },
    isoLocal(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); },
    get hojeISO(){ return this.isoLocal(new Date()); },
    abrirCal(){ if(this.viagemDia===null) return; const base=this.dataViagem||this.hojeISO; const [y,m]=base.split('-').map(Number); this.calMes=new Date(y,m-1,1); this.calAberto=true; },
    mudarMes(n){ const d=new Date(this.calMes); d.setMonth(d.getMonth()+n); this.calMes=d; },
    get nomeMes(){ return this.calMes ? this.calMes.toLocaleDateString('pt-BR',{month:'long',year:'numeric'}) : ''; },
    get calDias(){
      if(!this.calMes) return [];
      const ano=this.calMes.getFullYear(), mes=this.calMes.getMonth();
      const ini=new Date(ano,mes,1); ini.setDate(1-ini.getDay());
      const out=[];
      for(let i=0;i<42;i++){ const d=new Date(ini); d.setDate(ini.getDate()+i); const iso=this.isoLocal(d); const outroMes=d.getMonth()!==mes;
        const valido=!outroMes && d.getDay()===this.viagemDia && iso>=this.hojeISO;
        out.push({iso,dia:d.getDate(),valido,outroMes,sel:iso===this.dataViagem}); }
      return out;
    },
    escolherDia(iso){ this.dataViagem=iso; this.viagemLabel=this.rotuloData(iso); this.calAberto=false; },
    prep(){ if(!this.comprador.nome && this.ocupantes[0]) this.comprador.nome=this.ocupantes[0].nome; },
  };
}
</script>
@endsection
