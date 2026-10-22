@extends('layouts.app')
@section('titulo','Nova passagem')
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
  .pax { @apply rounded-xl p-2.5 border; background:#fff; border-color:#e2e8f0; }
  .pax:nth-child(even){ background:#f8fafc; }
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
<form method="post" action="{{ route('passagens.store') }}" x-data="novaPassagem()" @submit.prevent="salvar($event)"
      class="mx-auto max-w-[1440px] space-y-2" style="--cor1:#059669;--cor2:#34d399">
@csrf
<div class="flex items-center justify-between gap-3 flex-wrap">
  <div class="flex items-center gap-x-4 gap-y-1 flex-wrap">
    <h1 class="text-lg font-extrabold" style="color:var(--cor1)">🎫 Nova passagem</h1>
    <span class="text-[11px] font-bold rounded-lg px-2 py-0.5 text-white" style="background:var(--cor1)">Nº <span x-text="numeroPreview"></span></span>
    <div x-show="viagemData" x-cloak class="flex items-center gap-1.5 relative">
      <span class="lbl !mb-0 whitespace-nowrap">Viagem<span x-show="linhaNome" class="normal-case font-normal" x-text="' · '+linhaNome"></span></span>
      <button type="button" @click="abrirCal()" class="field !min-h-0 !py-1 text-sm font-semibold whitespace-nowrap" style="width:auto">
        📅 <span x-text="viagemLabel || 'escolher'"></span>
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
        <div class="text-[10px] text-slate-400 mt-1 text-center">Só os dias de viagem da linha ficam disponíveis.</div>
      </div>
    </div>
    @if($podeRetroativo)
    <div class="flex items-center gap-1.5">
      <span class="lbl !mb-0 whitespace-nowrap">Lançamento</span>
      <input type="date" class="field !min-h-0 !py-1 w-32 text-sm" x-model="lancamentoEm" :max="hojeISO">
    </div>
    @endif
  </div>
  <a href="{{ route('passagens.index') }}" class="text-sm text-slate-500 hover:underline">← voltar</a>
</div>

<div class="grid gap-2 lg:grid-cols-12 items-start">

  {{-- COLUNA 1: rota + comprador --}}
  <div class="lg:col-span-3 space-y-2">
    <section class="sec shrink-0">
      <div class="sec-h"><span class="n">1</span><span class="t">Rota</span></div>
      <div class="sec-b">
        <div class="grid grid-cols-2 gap-2">
          <div><label class="lbl">Origem</label>
            <select class="field" x-model="origemSel" required @change="onRota()">
              <option value="">—</option>
              @foreach($cidades as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
            </select>
          </div>
          <div><label class="lbl">Destino</label>
            <select class="field" x-model="destinoSel" required @change="onRota()">
              <option value="">—</option>
              @foreach($cidades as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div><label class="lbl">Transporte</label>
            <select class="field" x-model="embarcacaoSel"><option value="">—</option>
              @foreach($embarcacoes as $b)<option value="{{ $b->id }}">{{ $b->nome }}{{ $b->usa_poltrona?' 🪑':'' }}</option>@endforeach
            </select></div>
          <div><label class="lbl">Data</label>
            <input type="date" class="field" x-model="viagemData" @change="viagemLabel=rotulo(viagemData)"></div>
        </div>
        <div x-show="precoInfo" class="text-[11px] font-semibold" style="color:var(--cor1)" x-cloak>
          💲 Preço do trecho carregado
        </div>
      </div>
    </section>

    <section class="sec shrink-0">
      <div class="sec-h"><span class="n">2</span><span class="t">Comprador (opcional)</span></div>
      <div class="sec-b">
        <div class="pessoa" x-data="pessoaBusca()">
          <div class="relative">
            <label class="lbl">Nome</label>
            <input class="field" autocomplete="off" x-model="compradorNome" @input.debounce.300ms="buscar" @focus="aberto=true" @click.outside="aberto=false" placeholder="Quem está comprando">
            <div x-show="aberto && lista.length" x-cloak class="absolute z-20 mt-1 w-full bg-white rounded-xl border shadow-xl max-h-48 overflow-auto">
              <template x-for="p in lista" :key="p.nome+p.telefone">
                <button type="button" @click="escolher(p)" class="w-full text-left px-3 py-2 hover:bg-slate-50 text-sm border-b last:border-0">
                  <span class="font-semibold" x-text="p.nome"></span> <span class="text-slate-500 text-xs" x-text="p.telefone"></span>
                </button>
              </template>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <input class="field" inputmode="tel" placeholder="WhatsApp" x-model="compradorTel">
            <input class="field" inputmode="numeric" placeholder="CPF" x-model="compradorDoc">
          </div>
        </div>
      </div>
    </section>
  </div>

  {{-- COLUNA 2: passageiros --}}
  <section class="sec lg:col-span-6">
    <div class="sec-h">
      <span class="n">3</span><span class="t">Passageiros</span>
      <span class="ml-auto text-[13px] font-bold">(<span x-text="passageiros.length"></span>)</span>
      <button type="button" class="rounded-lg bg-white/20 hover:bg-white/30 px-2.5 py-1 text-[12px] font-bold" @click="addPax()">＋ passageiro</button>
    </div>
    <div class="sec-b">
      <div class="rounded-xl border border-slate-200 bg-slate-50 lg:max-h-[52vh] lg:overflow-auto p-2 space-y-1.5">
        <template x-for="(p,i) in passageiros" :key="p.id">
          <div class="pax">
            <div class="flex items-center gap-2 mb-1.5">
              <span class="w-6 h-6 rounded-full text-white text-[11px] font-bold flex items-center justify-center shrink-0" style="background:var(--cor1)" x-text="i+1"></span>
              <input :id="'pax-nome-'+i" class="field !min-h-0 py-1 flex-1" placeholder="Nome do passageiro" x-model="p.nome" required>
              <button type="button" class="w-8 h-8 rounded-full text-slate-400 hover:bg-red-50 hover:text-red-600 text-lg leading-none" @click="rmPax(i)" x-show="passageiros.length>1" title="Remover">×</button>
            </div>
            <div><label class="lbl">Tipo</label>
              <select class="field !min-h-0 py-1 text-sm" x-model="p.tipo_id" @change="aplicarTipo(p)">
                @foreach($tipos as $t)<option value="{{ $t->id }}">{{ $t->nome }}@if(($t->modo ?? 'valor')==='porcentagem' && !$t->eh_normal) ({{ $t->percentual }}%)@endif</option>@endforeach
              </select></div>
            <div class="mt-1"><label class="lbl">Valor (R$)</label>
              <input type="number" step="0.01" min="0" class="field !min-h-0 py-1 text-right text-sm font-semibold" :value="paxTotal(p)" @input="setPaxTotal(p,$event.target.value)" @focus="$event.target.select()"></div>
            <div class="flex items-center gap-2 mt-1.5">
              <input class="field !min-h-0 py-1 text-sm flex-1" placeholder="Documento (opcional)" x-model="p.documento">
              <button type="button" x-show="usaPoltrona" x-cloak class="rounded-lg border-2 px-3 py-1 text-sm font-bold whitespace-nowrap" style="border-color:var(--cor1);color:var(--cor1)" @click="abrirMapa(i)">
                <span x-text="p.poltrona ? ('🪑 '+p.poltrona) : '🪑 poltrona'"></span>
              </button>
            </div>
          </div>
        </template>
        <div x-show="!passageiros.length" class="text-center py-10 text-slate-400">
          <div class="text-3xl mb-1">🎫</div><div class="text-sm">Nenhum passageiro. Toque em “＋ passageiro”.</div>
        </div>
      </div>
      <input class="field" placeholder="Observação (opcional)" x-model="observacao">
    </div>
  </section>

  {{-- COLUNA 3: pagamento --}}
  <div class="lg:col-span-3 space-y-2">
    <section class="sec shrink-0">
      <div class="sec-h"><span class="n">4</span><span class="t">Pagamento</span></div>
      <div class="sec-b">
        <div class="flex gap-1.5">
          @foreach(\App\Models\Passagem::FORMAS as $k=>$l)
          <label class="forma" :class="forma==='{{ $k }}' && 'on'"><input type="radio" value="{{ $k }}" x-model="forma" class="sr-only"> {{ $l }}</label>
          @endforeach
        </div>
        <div x-show="forma!=='faturado'" class="grid grid-cols-2 gap-2">
          <template x-for="m in meios" :key="m.k">
            <div><label class="lbl" x-text="m.l"></label>
              <input type="number" step="0.01" min="0" inputmode="decimal" class="field text-right font-semibold" x-model.number="pag[m.k]" placeholder="0,00" @focus="$event.target.select()"></div>
          </template>
          <div><label class="lbl">Desconto</label><input type="number" step="0.01" min="0" inputmode="decimal" class="field text-right" x-model.number="desconto" placeholder="0,00" @focus="$event.target.select()"></div>
          <div class="flex items-end"><button type="button" class="w-full rounded-xl border-2 font-bold text-[13px] py-2 transition hover:text-white" style="border-color:var(--cor1);color:var(--cor1)" onmouseover="this.style.background='var(--cor1)'" onmouseout="this.style.background=''" @click="pag.dinheiro = +Math.max(total - pagoSemDinheiro, 0).toFixed(2)">Tudo em dinheiro</button></div>
        </div>
        <p x-show="forma==='faturado'" class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-xl p-3">Faturado: nada é recebido agora.</p>

        <div class="rounded-2xl p-2.5 text-white" style="background: linear-gradient(160deg, var(--cor1), color-mix(in srgb, var(--cor1) 75%, black))">
          <div class="flex justify-between text-xs opacity-75"><span>Tarifa de Transporte</span><span x-text="FBRE.moeda(subPassagem)"></span></div>
          <div class="flex justify-between text-xs opacity-75"><span>Alimentação + Adicional</span><span x-text="FBRE.moeda(subAlim+subTaxa)"></span></div>
          <div class="flex justify-between text-xs opacity-75" x-show="desconto>0"><span>Desconto</span><span x-text="'- '+FBRE.moeda(desconto)"></span></div>
          <div class="flex justify-between items-baseline mt-0.5"><span class="text-sm font-semibold opacity-90">Total</span><span class="text-xl font-extrabold tracking-tight" x-text="FBRE.moeda(total)"></span></div>
          <div class="h-px bg-white/20 my-1"></div>
          <div class="flex justify-between text-sm"><span>Pago</span><span x-text="FBRE.moeda(pago)"></span></div>
          <div class="flex justify-between items-center text-sm font-bold mt-0.5">
            <span>Devido</span>
            <span class="rounded-lg px-2 py-0.5" :class="devido>0.009 ? 'bg-amber-400 text-slate-900' : 'bg-emerald-400 text-slate-900'" x-text="devido>0.009 ? FBRE.moeda(devido) : 'Pago ✓'"></span>
          </div>
        </div>
      </div>
    </section>

    <section class="sec shrink-0">
      <div class="p-2.5 space-y-2">
        <div class="flex flex-wrap gap-x-3 gap-y-1 text-[13px] text-slate-600">
          <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" value="navegador" x-model="imprimir" class="accent-[var(--cor1)]"> Imprimir</label>
          <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" value="nao" x-model="imprimir" class="accent-[var(--cor1)]"> Não imprimir</label>
        </div>
        <button x-show="total>0 || forma!=='avista'" class="w-full rounded-xl py-3 text-base font-extrabold text-slate-900 shadow-lg transition hover:brightness-105 active:scale-[.98] disabled:opacity-40" style="background:var(--cor2)" :disabled="salvando || !passageiros.length">
          <span x-show="!salvando">💾 Salvar passagem</span><span x-show="salvando">Salvando…</span>
        </button>
        <p class="text-[11px] text-slate-400 text-center" x-show="!passageiros.length">Adicione ao menos um passageiro.</p>
      </div>
    </section>
  </div>
</div>

{{-- MAPA DE POLTRONAS --}}
<div x-show="mapaAberto" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-3" @click="mapaAberto=false">
  <div class="bg-white rounded-2xl p-4 w-full max-w-md max-h-[85vh] overflow-auto" @click.stop>
    <div class="flex items-center justify-between mb-3">
      <div class="font-bold">Escolha a poltrona</div><button type="button" class="text-xl px-2" @click="mapaAberto=false">×</button>
    </div>
    <div class="flex gap-3 text-xs mb-2">
      <span class="chip bg-slate-100 text-slate-600">livre</span>
      <span class="chip bg-red-100 text-red-700">ocupada</span>
      <span class="chip" style="background:color-mix(in srgb,var(--cor1) 15%,white);color:var(--cor1)">selecionada</span>
    </div>
    <div class="mx-auto" style="max-width:260px">
      <div class="text-center text-[11px] font-bold text-slate-400 mb-1">⬆ PROA (frente)</div>
      <div class="rounded-t-[36px] rounded-b-2xl border-2 border-slate-300 bg-slate-50 px-2 py-3 space-y-1.5">
        <template x-for="(f,ri) in fileiras" :key="ri">
          <div class="flex items-center justify-center gap-1">
            <div class="flex gap-1.5"><template x-for="n in f.esq" :key="n">
              <button type="button" class="w-9 h-10 rounded-t-xl rounded-b-md border-2 text-xs font-bold" :class="assentoClasse(n)" :disabled="estaOcupada(n) && !poltronaMinha(n)" @click="escolherPoltrona(n)" x-text="n"></button>
            </template></div>
            <div class="w-6 text-center text-[9px] text-slate-300 select-none">║</div>
            <div class="flex gap-1.5"><template x-for="n in f.dir" :key="n">
              <button type="button" class="w-9 h-10 rounded-t-xl rounded-b-md border-2 text-xs font-bold" :class="assentoClasse(n)" :disabled="estaOcupada(n) && !poltronaMinha(n)" @click="escolherPoltrona(n)" x-text="n"></button>
            </template></div>
          </div>
        </template>
      </div>
      <div class="text-center text-[11px] font-bold text-slate-400 mt-1">POPA (fundo) ⬇</div>
    </div>
  </div>
</div>
</form>
@endsection

@push('scripts')
<script>
  window.FBRE_OP = @js(['prefixo'=>auth()->user()->prefixo ?: 'OP'.auth()->id(), 'proximoSeq'=>(int)(auth()->user()->proximo_seq ?: 1)]);

  function novaPassagem(){
    return {
      salvando:false, forma:'avista', imprimir:'navegador',
      cidades:@js($cidades), linhas:@js($linhas), tipos:@js($tipos), trechoPrecos:@js($trechoPrecos), taxas:@js($taxas), normalId:@js($normalId),
      embarcacoesData:@js($embarcacoes->map(fn($e)=>['id'=>(string)$e->id,'poltrona'=>(bool)$e->usa_poltrona,'qtd'=>(int)$e->poltronas_qtd,'colunas'=>(int)($e->poltronas_colunas ?: 4)])->values()),
      podeRetroativo:@js($podeRetroativo),
      usaEmbarque:@js($usaTaxaEmbarque ?? false), usaAlim:@js($usaAlimentacao ?? true), labelTaxa:@js($labelTaxa ?? 'Taxa de embarque'), labelAlim:@js($labelAlim ?? 'Alimentação'),
      trechoMap:{}, taxaMap:{},
      origemSel:@js($ultimaOrigem ?? ''), destinoSel:'',
      embarcacaoSel:@js((string) old('embarcacao_id', $embarcacaoPrincipal)),
      compradorNome:'', compradorTel:'', compradorDoc:'', observacao:'',
      lancamentoEm:@js(now()->toDateString()),
      desconto:null, pag:{dinheiro:null,pix:null,debito:null,credito:null},
      meios:[{k:'dinheiro',l:'Dinheiro'},{k:'pix',l:'PIX'},{k:'debito',l:'Débito'},{k:'credito',l:'Crédito'}],
      passageiros:[],
      precoInfo:false,
      // viagem
      rotaId:'', linhaNome:'', paradasInfo:[], viagemData:'', viagemLabel:'', viagemDia:null, viagemHora:'', calAberto:false, calMes:null,
      diasCurto:['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
      // poltrona
      usaPoltrona:false, qtdPoltronas:0, colunasPoltrona:4, ocupadas:[], mapaAberto:false, paxAtual:0,
      numeroPreview:'—',

      init(){
        const op=window.FBRE_OP||{}; this.numeroPreview=(op.prefixo||'OP')+'-'+String(op.proximoSeq||1).padStart(4,'0');
        // mapas de preco por tipo e de taxa de embarque (origem|destino[|tipo])
        (this.trechoPrecos||[]).forEach(r=>{ this.trechoMap[(String(r.origem).toLowerCase())+'|'+(String(r.destino).toLowerCase())+'|'+r.tipo_passageiro_id]={tarifa:Number(r.tarifa)||0, alim:Number(r.alimentacao)||0, integral:Number(r.integral)||0}; });
        (this.taxas||[]).forEach(r=>{ this.taxaMap[(String(r.origem).toLowerCase())+'|'+(String(r.destino).toLowerCase())]=Number(r.taxa)||0; });
        // garante tipo_id inicial nos passageiros
        if(this.tipos.length && this.normalId==null) this.normalId=this.tipos[0].id;
        try{ const o=localStorage.getItem('fbre_origem'); if(o) this.origemSel=o; }catch(e){}
        if(!this.origemSel && this.cidades.length) this.origemSel=this.cidades[0];
        try{ const dd=localStorage.getItem('fbre_destino'); if(dd) this.destinoSel=dd; }catch(e){}
        try{ const t=localStorage.getItem('fbre_transporte'); if(t) this.embarcacaoSel=t; }catch(e){}
        this.$watch('origemSel', v=>{ try{ if(v) localStorage.setItem('fbre_origem',v);}catch(e){} });
        this.$watch('destinoSel', v=>{ try{ if(v) localStorage.setItem('fbre_destino',v); else localStorage.removeItem('fbre_destino');}catch(e){} });
        this.$watch('embarcacaoSel', ()=>this.mudarEmbarcacao());
        this.addPax();
        this.mudarEmbarcacao();
        this.onRota();
      },

      // ---- passageiros ----
      novoPax(){ const t=this.tipos[0]||{id:this.normalId,nome:'Adulto',percentual:100,paga_taxa:true,modo:'valor',eh_normal:true}; return {id:Date.now()+Math.random(), nome:'', tipo_id:t.id, tipo:t.nome, percentual:t.percentual, paga_taxa:!!t.paga_taxa, modo:t.modo||'valor', documento:'', poltrona:'', valor_passagem:0, valor_taxa:0, valor_alimentacao:0, valor_integral:0}; },
      addPax(){ const p=this.novoPax(); this.aplicarTipo(p); this.passageiros.push(p); if(this.usaPoltrona){ this.$nextTick(()=>this.abrirMapa(this.passageiros.length-1)); } },
      paxTotal(p){ return Math.round(((Number(p.valor_passagem)||0)+(Number(p.valor_taxa)||0)+(Number(p.valor_alimentacao)||0))*100)/100; },
      setPaxTotal(p,v){ const total=Math.max(Number(v)||0,0); const fixo=(Number(p.valor_taxa)||0)+(Number(p.valor_alimentacao)||0); p.valor_passagem=Math.round(Math.max(total-fixo,0)*100)/100; },
      rmPax(i){ this.passageiros.splice(i,1); },
      aplicarTipo(p){
        const t=this.tipos.find(x=>String(x.id)===String(p.tipo_id)) || this.tipos[0];
        if(t){ p.tipo_id=t.id; p.tipo=t.nome; p.percentual=t.percentual; p.paga_taxa=!!t.paga_taxa; p.modo=t.modo||'valor'; }
        const pr=this.precoDoTipo(t);
        p.valor_passagem = pr.tarifa;
        p.valor_alimentacao = this.usaAlim ? pr.alim : 0;
        p.valor_taxa = (this.usaEmbarque && p.paga_taxa) ? this.taxaDoTrecho() : 0;
        p.valor_integral = Math.max(Number(pr.integral)||0, (Number(p.valor_passagem)||0)+(Number(p.valor_alimentacao)||0)+(Number(p.valor_taxa)||0));
      },
      trechoGet(tipoId){ const o=(this.origemSel||'').trim().toLowerCase(), d=(this.destinoSel||'').trim().toLowerCase();
        return this.trechoMap[o+'|'+d+'|'+tipoId] || this.trechoMap[d+'|'+o+'|'+tipoId] || null; },
      precoDoTipo(t){
        if(!t) return {tarifa:0,alim:0,integral:0};
        // override salvo para este trecho+tipo (manual) tem prioridade
        const c=this.trechoGet(t.id);
        if(c){ const tt=Number(c.tarifa)||0, aa=this.usaAlim?Number(c.alim)||0:0; const ii=Math.max(Number(c.integral)||0, tt+aa); return {tarifa:tt, alim:aa, integral:ii}; }
        // porcentagem automatica: calcula a partir do Normal
        if((t.modo||'valor')==='porcentagem' && !t.eh_normal){
          const n=this.tipos.find(x=>String(x.id)===String(this.normalId));
          const nc=n?this.trechoGet(n.id):null;
          const baseT=nc?Number(nc.tarifa)||0:(n?Number(n.tarifa_padrao)||0:0);
          const baseA=nc?Number(nc.alim)||0:(n?Number(n.alimentacao_padrao)||0:0);
          const pct=(Number(t.percentual)||0)/100;
          const tarifa=Math.round(baseT*pct*100)/100;
          const alim=this.usaAlim ? ((t.pct_sobre==='tudo') ? Math.round(baseA*pct*100)/100 : Math.round(baseA*100)/100) : 0;
          const integral=this.integralNormal();
          return {tarifa:tarifa, alim:alim, integral:Math.max(integral, tarifa+alim)};
        }
        // valor sem linha: usa padrao do tipo
        const tt=Number(t.tarifa_padrao)||0, aa=this.usaAlim?Number(t.alimentacao_padrao)||0:0;
        return {tarifa: tt, alim: aa, integral: tt+aa};
      },
      taxaDoTrecho(){ const o=(this.origemSel||'').trim().toLowerCase(), d=(this.destinoSel||'').trim().toLowerCase();
        return Number(this.taxaMap[o+'|'+d] ?? this.taxaMap[d+'|'+o] ?? 0)||0; },
      get baseNormal(){ const n=this.tipos.find(x=>String(x.id)===String(this.normalId)); const c=n?this.trechoGet(n.id):null; return c?Number(c.tarifa)||0:(n?Number(n.tarifa_padrao)||0:0); },
      integralNormal(){ const n=this.tipos.find(x=>String(x.id)===String(this.normalId)); if(!n) return 0; const pr=this.precoDoTipo(n); return Math.round(((Number(pr.tarifa)||0)+(this.usaAlim?(Number(pr.alim)||0):0))*100)/100; },

      // ---- preço pela tabela (origem/destino) ----
      aplicarPreco(){
        this.passageiros.forEach(p=>this.aplicarTipo(p));
        this.precoInfo = this.baseNormal>0 || this.passageiros.some(p=>Number(p.valor_passagem)>0);
      },
      onRota(){ this.resolverLinha(); this.aplicarPreco(); if(this.usaPoltrona) this.carregarOcupadas(); },

      // ---- viagem (igual encomenda) ----
      isoLocal(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); },
      fmtBr(d){ return String(d.getDate()).padStart(2,'0')+'/'+String(d.getMonth()+1).padStart(2,'0')+'/'+d.getFullYear(); },
      get hojeISO(){ return this.isoLocal(new Date()); },
      proximaViagem(dia){ const d=new Date(); d.setHours(0,0,0,0); while(d.getDay()!==dia) d.setDate(d.getDate()+1); return this.isoLocal(d); },
      rotulo(iso){ if(!iso) return ''; const [y,m,day]=iso.split('-').map(Number); const d=new Date(y,m-1,day); const tag=iso<this.hojeISO?' · anterior':(iso===this.hojeISO?' · hoje':''); return this.diasCurto[d.getDay()]+' '+this.fmtBr(d)+(this.viagemHora?' '+this.viagemHora:'')+tag; },
      recalcularDatas(){
        let p=this.paradasInfo.find(x=>x.n===this.origemSel)||this.paradasInfo[0];
        if(!p || p.d===null||p.d===undefined||p.d===''){ this.viagemDia=null; this.viagemData=''; this.viagemLabel=''; return; }
        this.viagemDia=Number(p.d); this.viagemHora=p.h||'';
        this.viagemData=this.proximaViagem(this.viagemDia);
        this.$nextTick(()=>{ this.viagemLabel=this.rotulo(this.viagemData); });
      },
      abrirCal(){ if(this.viagemDia===null) return; const base=this.viagemData||this.hojeISO; const [y,m]=base.split('-').map(Number); this.calMes=new Date(y,m-1,1); this.calAberto=true; },
      mudarMes(n){ const d=new Date(this.calMes); d.setMonth(d.getMonth()+n); this.calMes=d; },
      get nomeMes(){ return this.calMes ? this.calMes.toLocaleDateString('pt-BR',{month:'long',year:'numeric'}) : ''; },
      get calDias(){
        if(!this.calMes) return [];
        const ano=this.calMes.getFullYear(), mes=this.calMes.getMonth();
        const ini=new Date(ano,mes,1); ini.setDate(1-ini.getDay());
        const out=[];
        for(let i=0;i<42;i++){ const d=new Date(ini); d.setDate(ini.getDate()+i); const iso=this.isoLocal(d); const outroMes=d.getMonth()!==mes;
          const valido=!outroMes && d.getDay()===this.viagemDia && (this.podeRetroativo || iso>=this.hojeISO);
          out.push({iso,dia:d.getDate(),valido,outroMes,sel:iso===this.viagemData}); }
        return out;
      },
      escolherDia(iso){ this.viagemData=iso; this.viagemLabel=this.rotulo(iso); this.calAberto=false; },
      resolverLinha(){
        this.rotaId=''; this.linhaNome=''; this.paradasInfo=[];
        if(!this.origemSel){ this.viagemDia=null; this.viagemData=''; this.viagemLabel=''; return; }
        const idx=(L,c)=>L.paradas.findIndex(p=>p.n===c);
        const linha =
             this.linhas.find(L=>{ const io=idx(L,this.origemSel), id=idx(L,this.destinoSel); return io>=0 && this.destinoSel && id>=0 && io<id; })
          || this.linhas.find(L=>idx(L,this.origemSel)>=0 && (!this.destinoSel || idx(L,this.destinoSel)>=0))
          || this.linhas.find(L=>idx(L,this.origemSel)>=0);
        if(!linha){ this.viagemDia=null; this.viagemData=''; this.viagemLabel=''; return; }
        this.rotaId=String(linha.id); this.linhaNome=linha.nome; this.paradasInfo=linha.paradas;
        this.recalcularDatas();
      },

      // ---- poltrona ----
      mudarEmbarcacao(){
        const e=this.embarcacoesData.find(x=>x.id===String(this.embarcacaoSel));
        this.usaPoltrona=!!(e&&e.poltrona); this.qtdPoltronas=e?e.qtd:0; this.colunasPoltrona=e&&e.colunas?e.colunas:4;
        if(this.usaPoltrona) this.carregarOcupadas(); else this.passageiros.forEach(p=>p.poltrona='');
      },
      async carregarOcupadas(){
        if(!this.usaPoltrona || !this.embarcacaoSel || !navigator.onLine) return;
        try{ const u=new URL('/passagens/assentos',location.origin); u.searchParams.set('embarcacao_id',this.embarcacaoSel);
          if(this.viagemData)u.searchParams.set('data_viagem',this.viagemData);
          if(this.rotaId)u.searchParams.set('rota_id',this.rotaId);
          if(this.origemSel)u.searchParams.set('origem',this.origemSel);
          if(this.destinoSel)u.searchParams.set('destino',this.destinoSel);
          const r=await fetch(u,{headers:{Accept:'application/json'},credentials:'same-origin'}); const j=await r.json();
          this.ocupadas=(j.ocupadas||[]).map(String); if(j.colunas)this.colunasPoltrona=j.colunas;
        }catch(e){ this.ocupadas=[]; }
      },
      abrirMapa(i){ this.paxAtual=i; this.mapaAberto=true; this.carregarOcupadas(); },
      estaOcupada(n){ return this.ocupadas.includes(String(n)); },
      poltronaMinha(n){ return this.passageiros.some(p=>String(p.poltrona)===String(n)); },
      assentoClasse(n){ const minha=String(this.passageiros[this.paxAtual]?.poltrona)===String(n); if(minha) return 'text-white'; if(this.estaOcupada(n)||this.poltronaMinha(n)) return 'bg-red-100 text-red-700 border-red-200 opacity-70'; return 'bg-slate-50 hover:bg-slate-100 border-slate-200'; },
      escolherPoltrona(n){ if(this.estaOcupada(n)&&!this.poltronaMinha(n))return; const i=this.paxAtual; this.passageiros[i].poltrona=String(n); this.mapaAberto=false; this.$nextTick(()=>{ const el=document.getElementById('pax-nome-'+i); if(el){ el.focus(); el.select && el.select(); } }); },

      // mapa no formato da lancha: fileiras com corredor no meio
      get fileiras(){
        const col=this.colunasPoltrona||4, qtd=this.qtdPoltronas||0, meio=Math.ceil(col/2);
        const rows=[]; let n=1;
        while(n<=qtd){ const r=[]; for(let c=0;c<col && n<=qtd;c++){ r.push(n++); } rows.push({esq:r.slice(0,meio), dir:r.slice(meio)}); }
        return rows;
      },

      // ---- totais ----
      get subPassagem(){ return this.passageiros.reduce((s,p)=>s+(Number(p.valor_passagem)||0),0); },
      get subTaxa(){ return this.passageiros.reduce((s,p)=>s+(Number(p.valor_taxa)||0),0); },
      get subAlim(){ return this.passageiros.reduce((s,p)=>s+(Number(p.valor_alimentacao)||0),0); },
      get total(){ return Math.max(this.subPassagem+this.subTaxa+this.subAlim-(Number(this.desconto)||0),0); },
      get pago(){ return this.forma==='faturado'?0:Object.values(this.pag).reduce((s,v)=>s+(Number(v)||0),0); },
      get pagoSemDinheiro(){ return (Number(this.pag.pix)||0)+(Number(this.pag.debito)||0)+(Number(this.pag.credito)||0); },
      get devido(){ return Math.max(this.total-this.pago,0); },

      async salvar(ev){
        if(this.salvando) return;
        if(!this.passageiros.length){ alert('Adicione ao menos um passageiro.'); return; }
        if(this.passageiros.some(p=>!p.nome.trim())){ alert('Informe o nome de todos os passageiros.'); return; }
        if(this.usaPoltrona){ const ass=this.passageiros.map(p=>p.poltrona).filter(Boolean); if(new Set(ass).size!==ass.length){ alert('Há poltronas repetidas.'); return; } }
        this.salvando=true;
        try{ if(this.origemSel) localStorage.setItem('fbre_origem',this.origemSel); if(this.embarcacaoSel) localStorage.setItem('fbre_transporte',this.embarcacaoSel); }catch(e){}
        const dados={
          origem:this.origemSel, destino:this.destinoSel,
          embarcacao_id:this.embarcacaoSel||null, rota_id:this.rotaId||null, viagem_data:this.viagemData||null,
          comprador_nome:this.compradorNome||null, comprador_telefone:this.compradorTel||null, comprador_documento:this.compradorDoc||null,
          forma:this.forma, desconto:Number(this.desconto)||0, observacao:this.observacao||null,
          passageiros:this.passageiros.map(p=>({nome:p.nome, tipo:p.tipo, percentual:p.percentual, modo:p.modo||'porcentagem', documento:p.documento||null, poltrona:p.poltrona||null, valor_passagem:Number(p.valor_passagem)||0, valor_taxa:Number(p.valor_taxa)||0, valor_alimentacao:Number(p.valor_alimentacao)||0, valor_integral:Number(p.valor_integral)||0})),
          pagamentos:this.forma==='faturado'?[]:this.meios.filter(m=>Number(this.pag[m.k])>0).map(m=>({meio:m.k, valor:Number(this.pag[m.k])})),
        };
        try{
          const r=await FBREOffline.salvarModulo('passagem', dados);
          if(r.servidor && r.servidor.ok){ window.location.href=r.servidor.url+(this.imprimir==='navegador'?'?print=1':''); return; }
          if(this.imprimir!=='nao') await FBREOffline.imprimirModuloLocal(r.local);
          alert('Sem conexão: passagem '+r.local.dados.numero_completo+' guardada neste aparelho. Sobe sozinha quando a internet voltar.');
          window.location.href='{{ route('passagens.create') }}';
        }catch(e){ alert('Não foi possível salvar: '+e.message); this.salvando=false; }
      },
    };
  }

  function pessoaBusca(){
    return { lista:[], aberto:false,
      async buscar(){ const q=this.compradorNome; if(!q || q.length<2){this.lista=[];return;} try{ const r=await fetch(`{{ route('pessoas.buscar') }}?q=${encodeURIComponent(q)}`); this.lista=await r.json(); this.aberto=true; }catch(e){} },
      escolher(p){ this.compradorNome=p.nome; this.compradorTel=p.telefone||''; this.compradorDoc=p.documento||''; this.aberto=false; this.lista=[]; } };
  }

  // comprovante local 80mm (offline)
  document.addEventListener('DOMContentLoaded',()=>{
    if(!window.FBREOffline) return;
    window.FBREOffline.renderers['passagem']=function(item,b){
      const e=(b&&b.empresa)||{}; const d=item.dados;
      const esc=s=>String(s??'').replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
      const moeda=v=>(Number(v)||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
      const subT=d.passageiros.reduce((s,p)=>s+(Number(p.valor_passagem)||0),0);
      const subA=d.passageiros.reduce((s,p)=>s+(Number(p.valor_alimentacao)||0),0);
      const subX=d.passageiros.reduce((s,p)=>s+(Number(p.valor_taxa)||0),0);
      const sub=subT+subA+subX;
      const desc=Number(d.desconto)||0, total=Math.max(sub-desc,0);
      const pago=(d.pagamentos||[]).reduce((s,p)=>s+(Number(p.valor)||0),0), devido=Math.max(total-pago,0);
      const linhas=d.passageiros.map(p=>{
        const vt=Number(p.valor_passagem)||0, va=Number(p.valor_alimentacao)||0, vx=Number(p.valor_taxa)||0;
        const pago=vt+va+vx; const vi=Number(p.valor_integral)||0;
        let bd='Transp. '+moeda(vt).replace('R$','').trim(); if((va+vx)>0) bd+=' · Alim.+Adic. '+moeda(va+vx).replace('R$','').trim();
        const normal = (vi>pago+0.009) ? `<div class="sm">Preço normal ${moeda(vi).replace('R$','').trim()} · Pago ${moeda(pago).replace('R$','').trim()}</div>` : '';
        return `<tr><td>${esc(p.nome)}<div class="sm">${esc(p.tipo)}${p.poltrona?(' · poltrona '+esc(p.poltrona)):''}</div>${normal}<div class="sm">${bd}</div></td><td class="r b">${moeda(pago).replace('R$','').trim()}</td></tr>`;
      }).join('');
      const html=`<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Passagem ${esc(d.numero_completo)}</title>
      <style>@page{margin:0;size:80mm auto}*{box-sizing:border-box}body{margin:0;padding:3mm 4mm;width:80mm;font-family:"Courier New",monospace;font-size:11.5px}
      .c{text-align:center}.r{text-align:right}.b{font-weight:bold}.xl{font-size:18px}.lg{font-size:14px}.sm{font-size:10px;color:#333}hr{border:0;border-top:1px dashed #000;margin:4px 0}
      table{width:100%;border-collapse:collapse}td{padding:2px 0;vertical-align:top}</style></head><body onload="window.print()">
      <div class="c b lg">${esc((e.nome||'FBRE').toUpperCase())}</div>${e.cnpj?`<div class="c sm">CNPJ ${esc(e.cnpj)}</div>`:''}<hr>
      <div class="c b xl">PASSAGEM ${esc(d.numero_completo)}</div>
      <div class="c sm">${new Date().toLocaleString('pt-BR')}</div><hr>
      <table><tr><td class="b">ORIGEM</td><td class="r">${esc(d.origem)}</td></tr><tr><td class="b">DESTINO</td><td class="r">${esc(d.destino)}</td></tr>${d.viagem_data?`<tr><td class="b">VIAGEM</td><td class="r">${esc(d.viagem_data)}</td></tr>`:''}</table><hr>
      <div class="b">PASSAGEIROS</div><table>${linhas}</table><hr>
      <table><tr><td>Tarifa de Transporte</td><td class="r">${moeda(subT)}</td></tr><tr><td>Alimentação + Adicional</td><td class="r">${moeda(subA+subX)}</td></tr>${desc>0?`<tr><td>Desconto</td><td class="r">-${moeda(desc)}</td></tr>`:''}<tr class="lg"><td class="b">TOTAL</td><td class="r b">${moeda(total)}</td></tr><tr><td>Pago</td><td class="r">${moeda(pago)}</td></tr><tr><td class="b">DEVIDO</td><td class="r b">${moeda(devido)}</td></tr></table>
      ${e.rodape?`<hr><div class="c sm">${esc(e.rodape)}</div>`:''}<hr><div class="c sm">Apresente esta passagem no embarque.</div><br><br></body></html>`;
      const w=window.open('','fbre_print','width=420,height=640'); if(!w)return alert('Permita pop-ups para imprimir.'); w.document.open(); w.document.write(html); w.document.close();
    };
  });
</script>
@endpush
