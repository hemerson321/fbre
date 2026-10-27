@extends('layouts.app')
@section('titulo','Novo frete')
@push('head')
<style type="text/tailwindcss">
  .sec { @apply rounded-2xl overflow-hidden flex flex-col; background:#fff; box-shadow: 0 1px 2px rgba(15,23,42,.06), 0 8px 24px -12px rgba(15,23,42,.18); }
  .sec-h { @apply flex items-center gap-2.5 px-3.5 py-2 text-white; background: linear-gradient(90deg, var(--cor1), color-mix(in srgb, var(--cor1) 70%, var(--cor2))); }
  .sec-h .n { @apply inline-flex w-6 h-6 rounded-full items-center justify-center text-[12px] font-extrabold text-slate-900; background: var(--cor2); }
  .sec-h .t { @apply text-[13px] font-bold uppercase tracking-wide; }
  .sec-b { @apply p-3 flex-1 flex flex-col gap-2.5; }
  .field { @apply rounded-xl bg-slate-50 border border-slate-200 px-3 py-2 text-[15px] w-full transition; min-height: 44px; }
  .field:focus { @apply outline-none bg-white; border-color: var(--cor2); box-shadow: 0 0 0 3px color-mix(in srgb, var(--cor2) 25%, transparent); }
  .lbl { @apply block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1; }
  .forma { @apply flex-1 rounded-xl border-2 py-2.5 text-[13px] font-bold text-center cursor-pointer transition text-slate-500 border-slate-200 bg-white; }
  .forma.on { background: var(--cor1); color:#fff; border-color: var(--cor1); }
  .pessoa { @apply rounded-xl p-2.5 space-y-1.5 border; background: color-mix(in srgb, var(--cor2) 7%, white); border-color: color-mix(in srgb, var(--cor2) 30%, white); }
  @media (min-width:1024px){ .field{min-height:36px;font-size:14px;padding-top:.3rem;padding-bottom:.3rem} .sec-b{padding:.55rem .6rem;gap:.45rem} .sec-h{padding-top:.3rem;padding-bottom:.3rem} .lbl{margin-bottom:.1rem;font-size:10px} .forma{padding-top:.4rem;padding-bottom:.4rem} }
</style>
@endpush
@section('conteudo')
<form method="post" action="{{ route('fretes.store') }}" x-data="novoFrete()" @submit.prevent="salvar($event)"
      class="mx-auto max-w-[1440px] space-y-2" @php $emp=\App\Support\Tenant::empresa(); $corMod=(bool)($emp?->config('cores_por_modulo', true) ?? true); @endphp style="--cor1:{{ $corMod ? '#d97706' : ($emp?->cor_primaria ?? '#0b1a33') }};--cor2:{{ $corMod ? '#fbbf24' : ($emp?->cor_secundaria ?? '#00a8e8') }}">
@csrf
<div class="flex items-center justify-between gap-3 flex-wrap">
  <div class="flex items-center gap-x-4 gap-y-1 flex-wrap">
    <h1 class="text-lg font-extrabold" style="color:var(--cor1)">🚢 Novo frete</h1>
    <span class="text-[11px] font-bold rounded-lg px-2 py-0.5 text-white" style="background:var(--cor1)">Nº <span x-text="numeroPreview"></span></span>
    <div x-show="viagemData" x-cloak class="flex items-center gap-1.5 relative">
      <span class="lbl !mb-0 whitespace-nowrap">Viagem<span x-show="linhaNome" class="normal-case font-normal" x-text="' · '+linhaNome"></span></span>
      <button type="button" @click="abrirCal()" class="field !min-h-0 !py-1 text-sm font-semibold whitespace-nowrap" style="width:auto">📅 <span x-text="viagemLabel || 'escolher'"></span></button>
      <div x-show="calAberto" x-cloak @click.outside="calAberto=false" class="absolute top-full left-0 mt-1 z-40 bg-white rounded-xl border shadow-xl p-2 w-64 text-slate-700">
        <div class="flex items-center justify-between mb-1">
          <button type="button" @click="mudarMes(-1)" class="w-7 h-7 rounded hover:bg-slate-100 text-lg leading-none">‹</button>
          <div class="text-sm font-bold capitalize" x-text="nomeMes"></div>
          <button type="button" @click="mudarMes(1)" class="w-7 h-7 rounded hover:bg-slate-100 text-lg leading-none">›</button>
        </div>
        <div class="grid grid-cols-7 text-center text-[10px] font-bold text-slate-400 mb-1"><template x-for="(d,i) in ['D','S','T','Q','Q','S','S']" :key="i"><div x-text="d"></div></template></div>
        <div class="grid grid-cols-7 gap-0.5"><template x-for="c in calDias" :key="c.iso">
          <button type="button" :disabled="!c.valido" @click="c.valido && escolherDia(c.iso)" class="h-8 rounded text-sm transition"
            :class="c.sel ? 'text-white font-bold' : (c.valido ? 'font-bold hover:brightness-95' : (c.outroMes ? 'text-slate-200' : 'text-slate-300 cursor-not-allowed'))"
            :style="c.sel ? 'background:var(--cor1)' : (c.valido ? 'color:var(--cor1);background:color-mix(in srgb, var(--cor2) 20%, white)' : '')" x-text="c.dia"></button>
        </template></div>
      </div>
    </div>
    @if($podeRetroativo)<div class="flex items-center gap-1.5"><span class="lbl !mb-0 whitespace-nowrap">Lançamento</span><input type="date" class="field !min-h-0 !py-1 w-32 text-sm" x-model="lancamentoEm" :max="hojeISO"></div>@endif
  </div>
  <a href="{{ route('fretes.index') }}" class="text-sm text-slate-500 hover:underline">← voltar</a>
</div>

<div class="grid gap-2 lg:grid-cols-12 items-start">
  {{-- COLUNA 1 --}}
  <div class="lg:col-span-3 space-y-2">
    <section class="sec shrink-0">
      <div class="sec-h"><span class="n">1</span><span class="t">Rota</span></div>
      <div class="sec-b">
        <div class="grid grid-cols-2 gap-2">
          <div><label class="lbl">Origem</label><select class="field" x-model="origemSel" required @change="resolverLinha()"><option value="">—</option>@foreach($cidades as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></div>
          <div><label class="lbl">Destino</label><select class="field" x-model="destinoSel" required @change="resolverLinha()"><option value="">—</option>@foreach($cidades as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></div>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div><label class="lbl">Transporte</label><select class="field" x-model="embarcacaoSel"><option value="">—</option>@foreach($embarcacoes as $b)<option value="{{ $b->id }}">{{ $b->nome }}</option>@endforeach</select></div>
          <div><label class="lbl">Data</label><input type="date" class="field" x-model="viagemData" @change="viagemLabel=rotulo(viagemData)"></div>
        </div>
      </div>
    </section>
    <section class="sec shrink-0">
      <div class="sec-h"><span class="n">2</span><span class="t">Remetente e destinatário</span></div>
      <div class="sec-b">
        <div class="pessoa" x-data="pessoaBusca('rem')">
          <div class="relative"><label class="lbl">Remetente</label>
            <input class="field" autocomplete="off" x-model="nome" @input.debounce.300ms="buscar" @focus="aberto=true" @click.outside="aberto=false" placeholder="Quem envia" required>
            <div x-show="aberto && lista.length" x-cloak class="absolute z-20 mt-1 w-full bg-white rounded-xl border shadow-xl max-h-40 overflow-auto"><template x-for="p in lista" :key="p.nome+p.telefone"><button type="button" @click="escolher(p)" class="w-full text-left px-3 py-2 hover:bg-slate-50 text-sm border-b last:border-0"><span class="font-semibold" x-text="p.nome"></span> <span class="text-slate-500 text-xs" x-text="p.telefone"></span></button></template></div>
          </div>
          <div class="grid grid-cols-2 gap-2"><input class="field" placeholder="WhatsApp" x-model="telefone"><input class="field" placeholder="CPF/CNPJ" x-model="documento"></div>
        </div>
        <div class="pessoa" x-data="pessoaBusca('dest')">
          <div class="relative"><label class="lbl">Destinatário</label>
            <input class="field" autocomplete="off" x-model="nome" @input.debounce.300ms="buscar" @focus="aberto=true" @click.outside="aberto=false" placeholder="Quem recebe" required>
            <div x-show="aberto && lista.length" x-cloak class="absolute z-20 mt-1 w-full bg-white rounded-xl border shadow-xl max-h-40 overflow-auto"><template x-for="p in lista" :key="p.nome+p.telefone"><button type="button" @click="escolher(p)" class="w-full text-left px-3 py-2 hover:bg-slate-50 text-sm border-b last:border-0"><span class="font-semibold" x-text="p.nome"></span> <span class="text-slate-500 text-xs" x-text="p.telefone"></span></button></template></div>
          </div>
          <div class="grid grid-cols-2 gap-2"><input class="field" placeholder="WhatsApp" x-model="telefone"><input class="field" placeholder="CPF/CNPJ" x-model="documento"></div>
        </div>
      </div>
    </section>
  </div>

  {{-- COLUNA 2: carga --}}
  <section class="sec lg:col-span-6">
    <div class="sec-h"><span class="n">3</span><span class="t">Carga</span></div>
    <div class="sec-b">
      <div><label class="lbl">Descrição da carga</label><textarea class="field" rows="2" x-model="descricao" placeholder="Ex.: 2 geladeiras, 1 moto..." required></textarea></div>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
        <div><label class="lbl">Peso (kg)</label><input type="number" step="0.01" min="0" class="field text-right" x-model.number="peso" @input="calcValor()" placeholder="0"></div>
        <div><label class="lbl">Volumes</label><input type="number" min="1" class="field text-center" x-model.number="volumes"></div>
        <div><label class="lbl">Preço / kg</label><input type="text" inputmode="numeric" class="field text-right" :value="FBRE.fmtMoney(precoKilo)" @input="precoKilo=FBRE.parseMoney($event.target.value); calcValor(); $event.target.value=FBRE.fmtMoney(precoKilo)" @focus="$event.target.value=''" @blur="$event.target.value=FBRE.fmtMoney(precoKilo)"></div>
        <div><label class="lbl">Valor do frete</label><input type="text" inputmode="numeric" class="field text-right font-bold" style="color:var(--cor1)" :value="FBRE.fmtMoney(valor)" @input="valor=FBRE.parseMoney($event.target.value); valorManual=true; $event.target.value=FBRE.fmtMoney(valor)" @focus="$event.target.value=''" @blur="$event.target.value=FBRE.fmtMoney(valor)"></div>
      </div>
      <p class="text-[11px] text-slate-400" x-show="peso>0 && precoKilo>0 && !valorManual" x-cloak>Calculado: <span x-text="peso"></span> kg × <span x-text="FBRE.moeda(precoKilo)"></span> = <b x-text="FBRE.moeda(peso*precoKilo)"></b></p>
      <input class="field" placeholder="Observação (opcional)" x-model="observacao">
    </div>
  </section>

  {{-- COLUNA 3: pagamento --}}
  <div class="lg:col-span-3 space-y-2">
    <section class="sec shrink-0">
      <div class="sec-h"><span class="n">4</span><span class="t">Pagamento</span></div>
      <div class="sec-b">
        <div class="flex gap-1.5">@foreach(\App\Models\Frete::FORMAS as $k=>$l)<label class="forma" :class="forma==='{{ $k }}' && 'on'"><input type="radio" value="{{ $k }}" x-model="forma" class="sr-only"> {{ $l }}</label>@endforeach</div>
        <div x-show="forma!=='faturado'" class="grid grid-cols-2 gap-2">
          <template x-for="m in meios" :key="m.k"><div x-show="m.k!=='pix' || pixManual"><label class="lbl" x-text="m.l"></label><input class="field text-right font-semibold w-full" x-money="pag[m.k]"></div></template>
          <div x-show="pixDinamico">
            <label class="lbl">PIX Automático</label>
            <button type="button" @click="gerarPixDinamico()" :disabled="pixPagando" title="Gerar QR de PIX e aguardar o pagamento" class="w-full rounded-xl border-2 font-bold text-[13px] py-2 disabled:opacity-50 hover:text-white transition flex items-center justify-center gap-1" style="border-color:var(--cor1);color:var(--cor1)" onmouseover="this.style.background='var(--cor1)'" onmouseout="this.style.background=''">💠 <span x-text="pixPagando ? 'Gerando…' : 'Gerar QR'"></span></button>
          </div>
          <div><label class="lbl">Desconto</label><input class="field text-right" x-money="desconto"></div>
          <div class="flex items-end"><button type="button" class="w-full rounded-xl border-2 font-bold text-[13px] py-2 transition hover:text-white" style="border-color:var(--cor1);color:var(--cor1)" onmouseover="this.style.background='var(--cor1)'" onmouseout="this.style.background=''" @click="pag.dinheiro = +Math.max(total - pagoSemDinheiro, 0).toFixed(2)">Tudo em dinheiro</button></div>
        </div>
        <p x-show="forma==='faturado'" class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-xl p-3">Faturado: nada recebido agora.</p>
        <div class="rounded-2xl p-2.5 text-white" style="background: linear-gradient(160deg, var(--cor1), color-mix(in srgb, var(--cor1) 75%, black))">
          <div class="flex justify-between items-baseline mt-0.5"><span class="text-sm font-semibold opacity-90">Total</span><span class="text-xl font-extrabold tracking-tight" x-text="FBRE.moeda(total)"></span></div>
          <div class="h-px bg-white/20 my-1"></div>
          <div class="flex justify-between text-sm"><span>Pago</span><span x-text="FBRE.moeda(pago)"></span></div>
          <div class="flex justify-between items-center text-sm font-bold mt-0.5"><span>Devido</span><span class="rounded-lg px-2 py-0.5" :class="devido>0.009 ? 'bg-amber-400 text-slate-900' : 'bg-emerald-400 text-slate-900'" x-text="devido>0.009 ? FBRE.moeda(devido) : 'Pago ✓'"></span></div>
        </div>
      </div>
    </section>
    <section class="sec shrink-0"><div class="p-2.5 space-y-2">
      <div class="flex flex-wrap gap-x-3 gap-y-1 text-[13px] text-slate-600">
        <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" value="navegador" x-model="imprimir" class="accent-[var(--cor1)]"> Imprimir</label>
        <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" value="nao" x-model="imprimir" class="accent-[var(--cor1)]"> Não imprimir</label>
      </div>
      <button x-show="total>0 || forma!=='avista'" class="w-full rounded-xl py-3 text-base font-extrabold text-slate-900 shadow-lg transition hover:brightness-105 active:scale-[.98] disabled:opacity-40" style="background:var(--cor2)" :disabled="salvando || !descricao.trim()">
        <span x-show="!salvando">💾 Salvar frete</span><span x-show="salvando">Salvando…</span>
      </button>
    </div></section>
  </div>
</div>

  {{-- modal PIX dinâmico: aguarda o pagamento; só cadastra depois de confirmar --}}
  <div x-show="pixModal" x-cloak class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl p-5 max-w-sm w-full text-center">
      <div class="font-bold text-lg">💠 PIX dinâmico</div>
      <div class="text-xs text-slate-500 mb-3">Mostre o QR pro cliente pagar. A tela confirma sozinha e só então cadastra.</div>
      <template x-if="pixQr"><img :src="pixQr.startsWith('data:')?pixQr:('data:image/png;base64,'+pixQr)" class="mx-auto rounded" style="width:220px;height:220px"></template>
      <template x-if="!pixQr"><div class="mx-auto flex items-center justify-center text-slate-300" style="width:220px;height:220px"><svg class="animate-spin w-10 h-10" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg></div></template>
      <div class="font-extrabold text-xl my-2">R$ <span x-text="fmtPix(pixValor)"></span></div>
      <div class="flex items-center justify-center gap-2 text-amber-600 text-sm font-semibold mb-3">
        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
        <span x-text="pixQr ? 'Aguardando pagamento…' : 'Gerando PIX…'"></span>
      </div>
      <button type="button" x-show="pixCobId" @click="imprimirPix(pixCobId)" class="btn btn-ghost btn-sm w-full mb-2">🖨 Imprimir QR</button>
      <button type="button" x-show="pixQr" @click="copiarPix()" class="btn btn-ghost btn-sm w-full mb-2">📋 Copiar código PIX</button>
      <button type="button" @click="cancelarPix()" class="btn btn-ghost btn-sm w-full text-slate-500">Cancelar</button>
    </div>
  </div>
</form>
@endsection

@push('scripts')
<script>
  window.FBRE_OP = @js(['prefixo'=>auth()->user()->prefixo ?: 'OP'.auth()->id(), 'proximoSeq'=>(int)(auth()->user()->proximo_seq ?: 1)]);
  function novoFrete(){
    return {
      salvando:false, forma:'avista', imprimir:'navegador',
      cidades:@js($cidades), linhas:@js($linhas),
      origemSel:@js($ultimaOrigem ?? ''), destinoSel:'', embarcacaoSel:@js((string) old('embarcacao_id', $embarcacaoPrincipal)),
      descricao:'', peso:null, volumes:1, precoKilo:@js((float)($precoKiloPadrao ?? 0)), valor:0, valorManual:false,
      observacao:'', desconto:null, pag:{dinheiro:null,pix:null,debito:null,credito:null},
      meios:[{k:'dinheiro',l:'Dinheiro'},{k:'pix',l:'PIX Manual'},{k:'debito',l:'Débito'},{k:'credito',l:'Crédito'}],
      pixManual:@js(auth()->user()->podePixManual()), pixDinamico:@js(auth()->user()->podePixDinamico() && \App\Support\Tenant::empresa()->usaPix('frete')),
      pixModal:false, pixQr:'', pixCopia:'', pixCobId:null, pixValor:0, pixPoll:null, pixPagando:false, pixChecando:false,
      rem:{nome:'',telefone:'',documento:''}, dest:{nome:'',telefone:'',documento:''},
      rotaId:'', linhaNome:'', paradasInfo:[], viagemData:'', viagemLabel:'', viagemDia:null, viagemHora:'', calAberto:false, calMes:null,
      diasCurto:['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'], lancamentoEm:@js(now()->toDateString()), podeRetroativo:@js($podeRetroativo), numeroPreview:'—',
      init(){ const op=window.FBRE_OP||{}; this.numeroPreview=(op.prefixo||'OP')+'-'+String(op.proximoSeq||1).padStart(4,'0');
        try{ const o=localStorage.getItem('fbre_origem'); if(!this.origemSel&&o)this.origemSel=o; }catch(e){}
        if(!this.origemSel && this.cidades.length) this.origemSel=this.cidades[0];
        try{ const t=localStorage.getItem('fbre_transporte'); if(t)this.embarcacaoSel=t; }catch(e){}
        this.$watch('origemSel', v=>{try{if(v)localStorage.setItem('fbre_origem',v)}catch(e){}});
        this.$watch('embarcacaoSel', v=>{try{if(v)localStorage.setItem('fbre_transporte',v)}catch(e){}});
        window._fretePessoas={rem:this.rem,dest:this.dest}; this.resolverLinha(); },
      calcValor(){ if(!this.valorManual){ this.valor = Math.round((Number(this.peso)||0)*(Number(this.precoKilo)||0)*100)/100; } },
      // viagem (igual passagem/encomenda)
      isoLocal(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); },
      fmtBr(d){ return String(d.getDate()).padStart(2,'0')+'/'+String(d.getMonth()+1).padStart(2,'0')+'/'+d.getFullYear(); },
      get hojeISO(){ return this.isoLocal(new Date()); },
      proximaViagem(dia){ const d=new Date(); d.setHours(0,0,0,0); while(d.getDay()!==dia) d.setDate(d.getDate()+1); return this.isoLocal(d); },
      rotulo(iso){ if(!iso) return ''; const [y,m,day]=iso.split('-').map(Number); const d=new Date(y,m-1,day); const tag=iso<this.hojeISO?' · anterior':(iso===this.hojeISO?' · hoje':''); return this.diasCurto[d.getDay()]+' '+this.fmtBr(d)+(this.viagemHora?' '+this.viagemHora:'')+tag; },
      recalcularDatas(){ let p=this.paradasInfo.find(x=>x.n===this.origemSel)||this.paradasInfo[0]; if(!p||p.d===null||p.d===undefined||p.d===''){this.viagemDia=null;this.viagemData='';this.viagemLabel='';return;} this.viagemDia=Number(p.d); this.viagemHora=p.h||''; this.viagemData=this.proximaViagem(this.viagemDia); this.$nextTick(()=>{this.viagemLabel=this.rotulo(this.viagemData)}); },
      abrirCal(){ if(this.viagemDia===null)return; const base=this.viagemData||this.hojeISO; const [y,m]=base.split('-').map(Number); this.calMes=new Date(y,m-1,1); this.calAberto=true; },
      mudarMes(n){ const d=new Date(this.calMes); d.setMonth(d.getMonth()+n); this.calMes=d; },
      get nomeMes(){ return this.calMes?this.calMes.toLocaleDateString('pt-BR',{month:'long',year:'numeric'}):''; },
      get calDias(){ if(!this.calMes)return[]; const ano=this.calMes.getFullYear(),mes=this.calMes.getMonth(); const ini=new Date(ano,mes,1); ini.setDate(1-ini.getDay()); const out=[]; for(let i=0;i<42;i++){const d=new Date(ini);d.setDate(ini.getDate()+i);const iso=this.isoLocal(d);const outroMes=d.getMonth()!==mes;const valido=!outroMes&&d.getDay()===this.viagemDia&&(this.podeRetroativo||iso>=this.hojeISO);out.push({iso,dia:d.getDate(),valido,outroMes,sel:iso===this.viagemData});} return out; },
      escolherDia(iso){ this.viagemData=iso; this.viagemLabel=this.rotulo(iso); this.calAberto=false; },
      resolverLinha(){ this.rotaId='';this.linhaNome='';this.paradasInfo=[]; if(!this.origemSel){this.viagemDia=null;this.viagemData='';this.viagemLabel='';return;} const idx=(L,c)=>L.paradas.findIndex(p=>p.n===c); const linha=this.linhas.find(L=>{const io=idx(L,this.origemSel),id=idx(L,this.destinoSel);return io>=0&&this.destinoSel&&id>=0&&io<id;})||this.linhas.find(L=>idx(L,this.origemSel)>=0&&(!this.destinoSel||idx(L,this.destinoSel)>=0))||this.linhas.find(L=>idx(L,this.origemSel)>=0); if(!linha){this.viagemDia=null;this.viagemData='';this.viagemLabel='';return;} this.rotaId=String(linha.id);this.linhaNome=linha.nome;this.paradasInfo=linha.paradas; this.recalcularDatas(); },
      get total(){ return Math.max((Number(this.valor)||0)-(Number(this.desconto)||0),0); },
      get pago(){ return this.forma==='faturado'?0:Object.values(this.pag).reduce((s,v)=>s+(Number(v)||0),0); },
      get pagoSemDinheiro(){ return (Number(this.pag.pix)||0)+(Number(this.pag.debito)||0)+(Number(this.pag.credito)||0); },
      get devido(){ return Math.max(this.total-this.pago,0); },
      fmtPix(v){ return (Number(v)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); },
      montarDados(){
        return { origem:this.origemSel, destino:this.destinoSel, embarcacao_id:this.embarcacaoSel||null, rota_id:this.rotaId||null, viagem_data:this.viagemData||null,
          remetente_nome:this.rem.nome, remetente_telefone:this.rem.telefone||null, remetente_documento:this.rem.documento||null,
          destinatario_nome:this.dest.nome, destinatario_telefone:this.dest.telefone||null, destinatario_documento:this.dest.documento||null,
          descricao:this.descricao, peso:Number(this.peso)||null, volumes:Number(this.volumes)||1, preco_kilo:Number(this.precoKilo)||0, subtotal:Number(this.valor)||0,
          forma:this.forma, desconto:Number(this.desconto)||0, observacao:this.observacao||null,
          pagamentos:this.forma==='faturado'?[]:this.meios.filter(m=>Number(this.pag[m.k])>0).map(m=>({meio:m.k,valor:Number(this.pag[m.k])})) };
      },
      gerarPixDinamico(){
        if(this.pixPagando) return;
        if(!this.rem.nome.trim()||!this.dest.nome.trim()){ alert('Informe remetente e destinatário antes de gerar o PIX.'); return; }
        if(!this.descricao.trim()){ alert('Descreva a carga antes de gerar o PIX.'); return; }
        const outros=(Number(this.pag.dinheiro)||0)+(Number(this.pag.debito)||0)+(Number(this.pag.credito)||0);
        const valor=Math.max((Number(this.total)||0)-outros,0);
        if(valor<=0){ alert('Sem valor a cobrar.'); return; }
        const pedido=this.montarDados();
        this.pixCobId=null; this.pixCopia=''; this.pixQr=''; this.pixValor=valor; this.pixModal=true; this.pixPagando=true;
        fetch('/cobranca/pix/gerar',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json'},body:JSON.stringify({modulo:'frete',valor:valor,descricao:'Frete',imprimir:this.imprimir,payload:pedido})})
          .then(r=>r.json()).then(d=>{
            if(!d.ok){ this.pixModal=false; this.pixPagando=false; alert(d.erro||'Falha ao gerar PIX'); return; }
            this.pixCobId=d.id; this.pixQr=d.qr_base64||''; this.pixCopia=d.copia_cola||''; this.pixValor=d.valor; this.imprimirPix(d.id);
            this.pixPoll=setInterval(()=>this.checarPix(),4000);
          }).catch(()=>{ this.pixModal=false; this.pixPagando=false; alert('Falha de conexão ao gerar PIX'); });
      },
      checarPix(){
        if(!this.pixCobId || this.pixChecando) return;
        this.pixChecando=true;
        fetch('/cobranca/'+this.pixCobId+'/status',{headers:{'Accept':'application/json'}}).then(r=>r.json()).then(d=>{
          if(d && d.pago){ clearInterval(this.pixPoll); this.pixPagando=false; this.finalizarPix(d); }
        }).catch(()=>{}).finally(()=>{ this.pixChecando=false; });
      },
      finalizarPix(d){
        if(d.erro || !d.url){ this.pixModal=false; alert('Pagamento recebido! '+(d.erro?('Não consegui cadastrar automaticamente: '+d.erro+'. '):'')+'Confira os dados e clique em Salvar.'); return; }
        this.salvando=true;
        window.location.href = d.url + (this.imprimir==='navegador' ? '?print=1' : '');
      },
      cancelarPix(){
        if(!confirm('Cancelar esta cobrança? Se o cliente já pagou, a venda será registrada normalmente.')) return;
        if(this.pixPoll) clearInterval(this.pixPoll); this.pixPagando=false;
        const id=this.pixCobId;
        if(!id){ this.pixModal=false; return; }
        fetch('/cobranca/'+id+'/cancelar',{method:'POST',headers:{'X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json'}}).then(r=>r.json()).then(d=>{
          if(d && d.pago){ this.finalizarPix(d); return; }
          this.pixModal=false; this.pixCobId=null;
        }).catch(()=>{ this.pixModal=false; this.pixCobId=null; });
      },
      copiarPix(){ try{ navigator.clipboard.writeText(this.pixCopia); }catch(e){} },
      imprimirPix(id){ fetch('/cobranca/'+id+'/imprimir-direto',{method:'POST',headers:{'X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json'}}).then(r=>r.json()).then(d=>{ if(!d||!d.ok){ alert((d&&d.erro)||'Nenhuma estação de impressão ativa. Mostre o QR na tela.'); } }).catch(()=>{}); },
      async salvar(ev){
        if(this.salvando) return;
        if(!this.rem.nome.trim()||!this.dest.nome.trim()){ alert('Informe remetente e destinatário.'); return; }
        if(!this.descricao.trim()){ alert('Descreva a carga.'); return; }
        this.salvando=true;
        const dados=this.montarDados();
        try{ const r=await FBREOffline.salvarModulo('frete', dados);
          if(r.servidor && r.servidor.ok){ window.location.href=r.servidor.url+(this.imprimir==='navegador'?'?print=1':''); return; }
          if(this.imprimir!=='nao') await FBREOffline.imprimirModuloLocal(r.local);
          alert('Sem conexão: frete '+r.local.dados.numero_completo+' guardado neste aparelho. Sobe sozinho quando a internet voltar.');
          window.location.href='{{ route('fretes.create') }}';
        }catch(e){ alert('Não foi possível salvar: '+e.message); this.salvando=false; }
      },
    };
  }
  function pessoaBusca(qual){
    return { nome:'', telefone:'', documento:'', lista:[], aberto:false,
      init(){ this.$watch('nome', v=>{ if(window._fretePessoas) window._fretePessoas[qual].nome=v; }); this.$watch('telefone', v=>{ if(window._fretePessoas) window._fretePessoas[qual].telefone=v; }); this.$watch('documento', v=>{ if(window._fretePessoas) window._fretePessoas[qual].documento=v; }); },
      async buscar(){ if(this.nome.length<2){this.lista=[];return;} try{ const r=await fetch(`{{ route('pessoas.buscar') }}?q=${encodeURIComponent(this.nome)}`); this.lista=await r.json(); this.aberto=true; }catch(e){} },
      escolher(p){ this.nome=p.nome; this.telefone=p.telefone||''; this.documento=p.documento||''; this.aberto=false; this.lista=[]; } };
  }
  document.addEventListener('DOMContentLoaded',()=>{ if(!window.FBREOffline) return;
    window.FBREOffline.renderers['frete']=function(item,b){ const e=(b&&b.empresa)||{}; const d=item.dados;
      const esc=s=>String(s??'').replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); const moeda=v=>(Number(v)||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
      const total=Math.max((Number(d.subtotal)||0)-(Number(d.desconto)||0),0); const pago=(d.pagamentos||[]).reduce((s,p)=>s+(Number(p.valor)||0),0); const devido=Math.max(total-pago,0);
      const html=`<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Frete ${esc(d.numero_completo)}</title><style>@page{margin:0;size:80mm auto}*{box-sizing:border-box}body{margin:0;padding:3mm 4mm;width:80mm;font-family:"Courier New",monospace;font-size:11.5px}.c{text-align:center}.r{text-align:right}.b{font-weight:bold}.xl{font-size:18px}.lg{font-size:14px}.sm{font-size:10px;color:#333}hr{border:0;border-top:1px dashed #000;margin:4px 0}table{width:100%;border-collapse:collapse}td{padding:2px 0;vertical-align:top}</style></head><body onload="window.print()">
      <div class="c b lg">${esc((e.nome||'FBRE').toUpperCase())}</div>${e.cnpj?`<div class="c sm">CNPJ ${esc(e.cnpj)}</div>`:''}<hr>
      <div class="c b xl">FRETE ${esc(d.numero_completo)}</div><div class="c sm">${new Date().toLocaleString('pt-BR')}</div><hr>
      <table><tr><td class="b">ORIGEM</td><td class="r">${esc(d.origem)}</td></tr><tr><td class="b">DESTINO</td><td class="r">${esc(d.destino)}</td></tr><tr><td class="b">VOLUMES</td><td class="r">${d.volumes}</td></tr>${d.peso?`<tr><td class="b">PESO</td><td class="r">${d.peso} kg</td></tr>`:''}</table><hr>
      <div class="b">REMETENTE</div><div>${esc(d.remetente_nome)}</div><div class="b" style="margin-top:3px">DESTINATÁRIO</div><div>${esc(d.destinatario_nome)}</div><hr>
      <div class="b">CARGA</div><div class="sm">${esc(d.descricao)}</div><hr>
      <table><tr class="lg"><td class="b">TOTAL</td><td class="r b">${moeda(total)}</td></tr><tr><td>Pago</td><td class="r">${moeda(pago)}</td></tr><tr><td class="b">DEVIDO</td><td class="r b">${moeda(devido)}</td></tr></table>
      ${e.rodape?`<hr><div class="c sm">${esc(e.rodape)}</div>`:''}<hr><div class="c sm">Apresente para retirar a carga.</div><br><br></body></html>`;
      const w=window.open('','fbre_print','width=420,height=640'); if(!w)return alert('Permita pop-ups.'); w.document.open(); w.document.write(html); w.document.close(); };
  });
</script>
@endpush
