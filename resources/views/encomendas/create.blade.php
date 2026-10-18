@extends('layouts.app')
@section('titulo','Nova encomenda')
@push('head')
<style type="text/tailwindcss">
  /* ---- cartão de seção com cabeçalho colorido ---- */
  .sec { @apply rounded-2xl overflow-hidden flex flex-col; background:#fff; box-shadow: 0 1px 2px rgba(15,23,42,.06), 0 8px 24px -12px rgba(15,23,42,.18); }
  .sec-h { @apply flex items-center gap-2.5 px-3.5 py-2 text-white; background: linear-gradient(90deg, var(--cor1), color-mix(in srgb, var(--cor1) 70%, var(--cor2))); }
  .sec-h .n { @apply inline-flex w-6 h-6 rounded-full items-center justify-center text-[12px] font-extrabold text-slate-900; background: var(--cor2); }
  .sec-h .t { @apply text-[13px] font-bold uppercase tracking-wide; }
  .sec-b { @apply p-3 flex-1 flex flex-col gap-2.5; }
  .field { @apply rounded-xl bg-slate-50 border border-slate-200 px-3 py-2 text-[15px] w-full transition; min-height: 44px; }
  .field:focus { @apply outline-none bg-white; border-color: var(--cor2); box-shadow: 0 0 0 3px color-mix(in srgb, var(--cor2) 25%, transparent); }
  .lbl { @apply block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1; }
  .pill { @apply inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[13px] font-semibold transition active:scale-95; border-color: color-mix(in srgb, var(--cor1) 25%, white); background: color-mix(in srgb, var(--cor1) 6%, white); color: var(--cor1); }
  .pill:hover { background: var(--cor1); color: #fff; }
  .pill .v { @apply text-[11px] font-bold rounded-full px-1.5 py-0.5; background: var(--cor2); color:#0f172a; }
  .pill:hover .v { background:#fff; }
  .pill-add { @apply border-dashed; background:#fff; }
  .forma { @apply flex-1 rounded-xl border-2 py-2.5 text-[13px] font-bold text-center cursor-pointer transition text-slate-500 border-slate-200 bg-white; }
  .forma.on { background: var(--cor1); color:#fff; border-color: var(--cor1); }
  .item { @apply grid grid-cols-12 gap-2 items-center rounded-xl p-2 border; background:#fff; border-color:#e2e8f0; }
  .item:nth-child(even) { background:#f8fafc; }
  .item input.field { min-height: 38px; @apply text-sm py-1 bg-white; }
  .pessoa { @apply rounded-xl p-2.5 space-y-1.5 border; background: color-mix(in srgb, var(--cor2) 7%, white); border-color: color-mix(in srgb, var(--cor2) 30%, white); }
  /* ---- compacto no desktop pra tudo caber sem rolagem ---- */
  @media (min-width:1024px){
    .field{min-height:36px;font-size:14px;padding-top:.3rem;padding-bottom:.3rem}
    .sec-b{padding:.55rem .6rem;gap:.45rem}
    .sec-h{padding-top:.3rem;padding-bottom:.3rem}
    .lbl{margin-bottom:.1rem;font-size:10px}
    .pessoa{padding:.5rem;}
    .pessoa>*+*{margin-top:.35rem}
    .forma{padding-top:.4rem;padding-bottom:.4rem}
    .item input.field{min-height:34px}
  }
</style>
@endpush
@section('conteudo')
<form method="post" action="{{ route('encomendas.store') }}" x-data="novaEncomenda()" @submit.prevent="salvar($event)"
      class="mx-auto max-w-[1440px] space-y-2">
@csrf
<div class="flex items-center justify-between">
  <div class="flex items-center gap-3">
    <h1 class="text-lg font-extrabold" style="color:var(--cor1)">Nova encomenda</h1>
    <span class="hidden xl:inline text-xs text-slate-500">Preencha as 4 etapas e salve. O comprovante e as etiquetas saem na hora.</span>
  </div>
  <a href="{{ route('encomendas.index') }}" class="text-sm text-slate-500 hover:underline">← voltar</a>
</div>

<div class="grid gap-2 lg:grid-cols-12 items-start">

  {{-- ===== COLUNA 1 ===== --}}
  <div class="lg:col-span-3 space-y-2">
    <section class="sec shrink-0">
      <div class="sec-h"><span class="n">1</span><span class="t">Rota</span></div>
      <div class="sec-b">
        {{-- 1º: Origem e Destino --}}
        <div class="grid grid-cols-2 gap-2">
          <div><label class="lbl">Origem</label>
            <select name="origem" class="field" x-model="origemSel" required @change="resolverLinha()">
              <option value="">—</option>
              <template x-for="c in cidades" :key="'o'+c"><option :value="c" x-text="c"></option></template>
            </select>
          </div>
          <div><label class="lbl">Destino</label>
            <select name="destino" class="field" x-model="destinoSel" required @change="resolverLinha()">
              <option value="">—</option>
              <template x-for="c in cidades" :key="'d'+c"><option :value="c" x-text="c"></option></template>
            </select>
          </div>
        </div>

        {{-- Data da viagem: identificada pela origem/destino --}}
        <div x-show="viagemDatas.length" x-cloak>
          <label class="lbl">Data da viagem <span x-show="linhaNome" class="normal-case text-slate-400 font-normal" x-text="'· '+linhaNome"></span></label>
          <select name="viagem_data" class="field font-semibold" x-model="viagemData">
            <template x-for="d in viagemDatas" :key="d.v"><option :value="d.v" x-text="d.label"></option></template>
          </select>
        </div>
        <input type="hidden" name="rota_id" :value="rotaId">

        {{-- Transporte / Setor --}}
        <div class="grid grid-cols-2 gap-2">
          <div><label class="lbl">Transporte</label>
            <select name="embarcacao_id" class="field"><option value="">—</option>
              @foreach($embarcacoes as $b)<option value="{{ $b->id }}" @selected(old('embarcacao_id', $embarcacaoPrincipal)==$b->id)>{{ $b->nome }}</option>@endforeach
            </select></div>
          <div><label class="lbl">Setor</label>
            @if($restringeSetor)
              <select name="setor" class="field">@foreach($setores as $s)<option value="{{ $s }}" @selected(old('setor', auth()->user()->setor)==$s)>{{ $s }}</option>@endforeach</select>
            @else
              <input name="setor" list="setores" class="field" value="{{ old('setor', auth()->user()->setor) }}">
            @endif
          </div>
        </div>

        <div>
          <label class="lbl">Data do lançamento</label>
          <input type="date" name="lancamento_em" class="field" x-model="lancamentoEm" :max="hojeISO">
          <p class="text-[11px] text-amber-600 mt-0.5" x-show="lancamentoEm && lancamentoEm < hojeISO">Lançando em data anterior — entra no caixa/relatório nesse dia.</p>
        </div>
        <datalist id="setores">@foreach($setores as $s)<option value="{{ $s }}">@endforeach</datalist>
      </div>
    </section>

    <section class="sec shrink-0">
      <div class="sec-h"><span class="n">2</span><span class="t">Remetente e destinatário</span></div>
      <div class="sec-b">
        @foreach(['remetente'=>['Remetente','Quem está enviando'],'destinatario'=>['Destinatário','Quem vai receber']] as $k=>[$lbl,$dica])
        <div class="pessoa" x-data="pessoa()">
          <div class="relative">
            <label class="lbl">{{ $lbl }} <span class="normal-case font-normal text-slate-400 text-[10px]">· {{ $dica }}</span></label>
            <input name="{{ $k }}_nome" class="field" required value="{{ old($k.'_nome') }}" autocomplete="off"
                   x-model="nome" @input.debounce.300ms="buscar" @focus="aberto=true" @click.outside="aberto=false" placeholder="Nome completo">
            <div x-show="aberto && lista.length" x-cloak class="absolute z-20 mt-1 w-full bg-white rounded-xl border shadow-xl max-h-48 overflow-auto">
              <template x-for="p in lista" :key="p.nome+p.telefone">
                <button type="button" @click="escolher(p)" class="w-full text-left px-3 py-2 hover:bg-slate-50 text-sm border-b last:border-0">
                  <span class="font-semibold" x-text="p.nome"></span> <span class="text-slate-500 text-xs" x-text="p.telefone"></span>
                </button>
              </template>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <input name="{{ $k }}_telefone" class="field" inputmode="tel" placeholder="WhatsApp" x-model="telefone" value="{{ old($k.'_telefone') }}">
            <input name="{{ $k }}_documento" class="field" inputmode="numeric" placeholder="CPF / CNPJ" x-model="documento" value="{{ old($k.'_documento') }}">
          </div>
        </div>
        @endforeach
      </div>
    </section>
  </div>

  {{-- ===== COLUNA 2: PRODUTOS ===== --}}
  <section class="sec lg:col-span-6">
    <div class="sec-h">
      <span class="n">3</span><span class="t">Produtos / volumes</span>
      <div class="ml-auto flex items-center gap-2">
        <span class="text-[11px] font-bold uppercase opacity-80">Volumes</span>
        <input name="volumes" type="number" min="1" x-model.number="volumes" class="w-16 rounded-lg text-center font-extrabold text-slate-900 py-1 text-base" style="background:var(--cor2)" inputmode="numeric">
      </div>
    </div>
    <div class="sec-b">
      <div>
        <div class="lbl">Toque para adicionar</div>
        <div class="flex flex-wrap gap-1.5">
          @foreach($produtos as $p)
            <button type="button" class="pill" @click="addItem('{{ addslashes($p->nome) }}', {{ (float)$p->valor_padrao }})">{{ $p->nome }} <span class="v">{{ number_format($p->valor_padrao,2,',','.') }}</span></button>
          @endforeach
          <button type="button" class="pill pill-add" @click="addItem('',0)">＋ outro item</button>
        </div>
      </div>

      <div class="rounded-xl border border-slate-200 bg-slate-50 lg:max-h-[46vh] lg:overflow-auto">
        <div class="sticky top-0 hidden lg:grid grid-cols-12 gap-2 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-100 border-b">
          <div class="col-span-5">Descrição</div><div class="col-span-2 text-center">Qtd</div><div class="col-span-2 text-right">Valor unit.</div><div class="col-span-2 text-right">Total</div><div class="col-span-1"></div>
        </div>
        <div class="p-2 space-y-1.5">
          <template x-for="(it,i) in itens" :key="it.id">
            <div class="item">
              <input :name="`itens[${i}][descricao]`" x-model="it.descricao" class="field col-span-12 lg:col-span-5" placeholder="Descrição" required>
              <input :name="`itens[${i}][quantidade]`" x-model.number="it.quantidade" type="number" step="any" min="0.01" class="field col-span-3 lg:col-span-2 text-center font-semibold" inputmode="decimal" @focus="$event.target.select()">
              <input :name="`itens[${i}][valor_unitario]`" x-model.number="it.valor" type="number" step="0.01" min="0" class="field col-span-4 lg:col-span-2 text-right" inputmode="decimal" @focus="$event.target.select()">
              <div class="col-span-4 lg:col-span-2 text-right text-sm font-extrabold whitespace-nowrap" style="color:var(--cor1)" x-text="FBRE.moeda(it.quantidade*it.valor)"></div>
              <button type="button" @click="itens.splice(i,1)" class="col-span-1 w-8 h-8 mx-auto rounded-full text-slate-400 hover:bg-red-50 hover:text-red-600 text-lg leading-none" title="Remover">×</button>
              <input :name="`itens[${i}][observacao]`" x-model="it.obs" class="col-span-12 text-xs bg-transparent border-0 border-b border-dashed border-slate-300 px-1 py-0.5 focus:outline-none" placeholder="observação do item (opcional)">
            </div>
          </template>
          <div x-show="!itens.length" class="text-center py-10 text-slate-400">
            <div class="text-3xl mb-1">📦</div><div class="text-sm">Nenhum item ainda. Toque em um produto acima.</div>
          </div>
        </div>
      </div>
      <input name="observacao" class="field" placeholder="Observação geral da encomenda (opcional)" value="{{ old('observacao') }}">
    </div>
  </section>

  {{-- ===== COLUNA 3: PAGAMENTO ===== --}}
  <div class="lg:col-span-3 space-y-2">
    <section class="sec shrink-0">
      <div class="sec-h"><span class="n">4</span><span class="t">Pagamento</span></div>
      <div class="sec-b">
        <div class="flex gap-1.5">
          @foreach(\App\Models\Encomenda::FORMAS as $k=>$l)
          <label class="forma" :class="forma==='{{ $k }}' && 'on'"><input type="radio" name="forma" value="{{ $k }}" x-model="forma" class="sr-only"> {{ $l }}</label>
          @endforeach
        </div>

        <div x-show="forma!=='faturado'" class="grid grid-cols-2 gap-2">
          <template x-for="m in meios" :key="m.k">
            <div><label class="lbl" x-text="m.l"></label>
              <input type="number" step="0.01" min="0" inputmode="decimal" class="field text-right font-semibold" x-model.number="pag[m.k]" placeholder="0,00" @focus="$event.target.select()"></div>
          </template>
          <div><label class="lbl">Desconto</label><input name="desconto" type="number" step="0.01" min="0" inputmode="decimal" class="field text-right" x-model.number="desconto" placeholder="0,00" @focus="$event.target.select()"></div>
          <div class="flex items-end"><button type="button" class="w-full rounded-xl border-2 font-bold text-[13px] py-2 transition hover:text-white" style="border-color:var(--cor1);color:var(--cor1)" onmouseover="this.style.background='var(--cor1)'" onmouseout="this.style.background=''" @click="pag.dinheiro = +Math.max(total - pagoSemDinheiro, 0).toFixed(2)">Tudo em dinheiro</button></div>
        </div>
        <template x-for="m in meios" :key="'h'+m.k">
          <template x-if="pag[m.k]>0 && forma!=='faturado'">
            <span><input type="hidden" :name="`pagamentos[${m.k}][meio]`" :value="m.k"><input type="hidden" :name="`pagamentos[${m.k}][valor]`" :value="pag[m.k]"></span>
          </template>
        </template>
        <p x-show="forma==='faturado'" class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-xl p-3">Faturado: nada é recebido agora. A cobrança fica pendente.</p>

        <div class="rounded-2xl p-2.5 text-white" style="background: linear-gradient(160deg, var(--cor1), color-mix(in srgb, var(--cor1) 75%, black))">
          <div class="flex justify-between text-xs opacity-75"><span>Subtotal</span><span x-text="FBRE.moeda(subtotal)"></span></div>
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
          <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" name="imprimir" value="navegador" x-model="imprimir" class="accent-[var(--cor1)]"> Imprimir</label>
          @if(\App\Support\Tenant::empresa()->temRecurso('print_agent'))
          <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" name="imprimir" value="direto" x-model="imprimir" class="accent-[var(--cor1)]"> Direta</label>
          @endif
          <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" name="imprimir" value="nao" x-model="imprimir" class="accent-[var(--cor1)]"> Não imprimir</label>
        </div>
        <button class="w-full rounded-xl py-3 text-base font-extrabold text-slate-900 shadow-lg transition hover:brightness-105 active:scale-[.98] disabled:opacity-40 disabled:shadow-none" style="background:var(--cor2)" :disabled="salvando || !itens.length">
          <span x-show="!salvando">💾 Salvar encomenda</span><span x-show="salvando">Salvando…</span>
        </button>
        <p class="text-[11px] text-slate-400 text-center" x-show="!itens.length">Adicione ao menos um produto.</p>
      </div>
    </section>
  </div>
</div>
</form>
@endsection

@push('scripts')
<script>
function novaEncomenda(){
  return {
    salvando:false, volumes:1, forma:'avista', imprimir:'navegador',
    rotaId:'', linhaNome:'', paradasInfo: [],
    cidades: @js($cidades), linhas: @js($linhas),
    origemSel: @js(old('origem', $ultimaOrigem)), destinoSel: @js(old('destino', '')),
    lancamentoEm: @js(old('lancamento_em', now()->toDateString())),
    viagemData:'', viagemDatas:[], viagemHora:'',
    diasCurto:['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
    itens:[], desconto:null, pag:{dinheiro:null,pix:null,debito:null,credito:null},
    meios:[{k:'dinheiro',l:'Dinheiro'},{k:'pix',l:'PIX'},{k:'debito',l:'Débito'},{k:'credito',l:'Crédito'}],
    init(){ this.resolverLinha(); },
    addItem(d,v){ this.itens.push({id:Date.now()+Math.random(), descricao:d, quantidade:1, valor:v, obs:''}); },
    // ---- datas de viagem a partir do dia da semana da linha ----
    isoLocal(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); },
    fmtBr(d){ return String(d.getDate()).padStart(2,'0')+'/'+String(d.getMonth()+1).padStart(2,'0')+'/'+d.getFullYear(); },
    get hojeISO(){ return this.isoLocal(new Date()); },
    gerarDatas(dia,hora,passadas,futuras){
      const out=[]; const hoje=this.isoLocal(new Date());
      const base=new Date(); base.setHours(0,0,0,0);
      while(base.getDay()!==dia) base.setDate(base.getDate()+1);     // próxima ocorrência >= hoje
      const d=new Date(base); d.setDate(d.getDate()-7*passadas);     // começa nas semanas anteriores
      for(let i=0;i<passadas+futuras;i++){
        const v=this.isoLocal(d);
        const tag = v<hoje ? ' · anterior' : (v===hoje ? ' · hoje' : '');
        out.push({v, label:this.diasCurto[dia]+' '+this.fmtBr(d)+(hora?' '+hora:'')+tag});
        d.setDate(d.getDate()+7);
      }
      return out;
    },
    // datas da viagem a partir da ORIGEM escolhida (dia/hora em que o barco sai de lá); lista passadas + futuras
    recalcularDatas(){
      if (!this.rotaId || !this.paradasInfo.length) { this.viagemDatas=[]; this.viagemData=''; return; }
      const p = this.paradasInfo.find(x=>x.n===this.origemSel) || this.paradasInfo[0];
      this.viagemHora = p.h || '';
      if (p.d===null||p.d===undefined||p.d==='') { this.viagemDatas=[]; this.viagemData=''; return; }
      this.viagemDatas = this.gerarDatas(Number(p.d), this.viagemHora, 8, 20);   // 8 semanas atrás + 20 à frente
      // padrão: viagem de hoje se for dia de viagem, senão a PRÓXIMA (nunca cai numa passada)
      const hoje = this.isoLocal(new Date());
      const alvo = (this.viagemDatas.find(d=>d.v>=hoje) || this.viagemDatas[this.viagemDatas.length-1]).v;
      this.$nextTick(()=>{ this.viagemData = alvo; });   // espera as opções renderizarem antes de selecionar
    },
    // acha a linha a partir da origem (e destino, se escolhido) e monta as datas
    resolverLinha(){
      this.rotaId=''; this.linhaNome=''; this.paradasInfo=[];
      if (!this.origemSel) { this.viagemDatas=[]; this.viagemData=''; return; }
      const tem=(L,c)=>L.paradas.some(p=>p.n===c);
      const linha = this.linhas.find(L=>tem(L,this.origemSel) && (!this.destinoSel || tem(L,this.destinoSel)))
                 || this.linhas.find(L=>tem(L,this.origemSel));
      if (!linha) { this.viagemDatas=[]; this.viagemData=''; return; }  // cidade fora de linha = avulsa
      this.rotaId=String(linha.id); this.linhaNome=linha.nome; this.paradasInfo=linha.paradas;
      this.recalcularDatas();
    },
    get subtotal(){ return this.itens.reduce((s,i)=>s+(Number(i.quantidade)||0)*(Number(i.valor)||0),0); },
    get total(){ return Math.max(this.subtotal-(Number(this.desconto)||0),0); },
    get pago(){ return this.forma==='faturado'?0:Object.values(this.pag).reduce((s,v)=>s+(Number(v)||0),0); },
    get pagoSemDinheiro(){ return (Number(this.pag.pix)||0)+(Number(this.pag.debito)||0)+(Number(this.pag.credito)||0); },
    get devido(){ return Math.max(this.total-this.pago,0); },

    // Salva no aparelho primeiro, depois envia. Sem internet: imprime comprovante provisório e continua.
    async salvar(ev){
      const form = ev.target;
      if (!form.reportValidity()) return;
      this.salvando = true;
      const fd = new FormData(form);
      const dados = {
        origem: fd.get('origem'), destino: fd.get('destino'), embarcacao_id: fd.get('embarcacao_id') || null, rota_id: fd.get('rota_id') || null, viagem_data: this.viagemData || null, lancamento_em: this.lancamentoEm || null, setor: fd.get('setor'),
        remetente_nome: fd.get('remetente_nome'), remetente_telefone: fd.get('remetente_telefone'), remetente_documento: fd.get('remetente_documento'),
        destinatario_nome: fd.get('destinatario_nome'), destinatario_telefone: fd.get('destinatario_telefone'), destinatario_documento: fd.get('destinatario_documento'),
        volumes: this.volumes, forma: this.forma, observacao: fd.get('observacao'), desconto: Number(this.desconto)||0,
        itens: this.itens.map(i => ({descricao:i.descricao, quantidade:Number(i.quantidade)||1, valor_unitario:Number(i.valor)||0, observacao:i.obs||null})),
        pagamentos: this.forma==='faturado' ? [] : this.meios.filter(m => Number(this.pag[m.k])>0).map(m => ({meio:m.k, valor:Number(this.pag[m.k])})),
      };
      try {
        const r = await FBREOffline.salvarEncomenda(dados);
        if (r.servidor && r.servidor.ok) {
          // online: segue o fluxo normal (tela da encomenda + impressão)
          const url = r.servidor.url + (this.imprimir==='navegador' ? '?print=1' : '');
          if (this.imprimir==='direto') { fetch('/imprimir/direto/'+r.servidor.id+'/comprovante',{method:'POST',headers:{'X-CSRF-TOKEN':FBRE.csrf}}); fetch('/imprimir/direto/'+r.servidor.id+'/etiqueta',{method:'POST',headers:{'X-CSRF-TOKEN':FBRE.csrf}}); }
          window.location.href = url; return;
        }
        // offline (ou servidor indisponível): guardado no aparelho
        if (this.imprimir !== 'nao') await FBREOffline.imprimirComprovanteLocal(r.local);
        alert('Sem conexão: encomenda ' + r.local.dados.numero_provisorio + ' guardada neste aparelho. Será enviada automaticamente quando a internet voltar.');
        window.location.href = '{{ route('encomendas.create') }}';
      } catch (e) {
        alert('Não foi possível salvar: ' + e.message); this.salvando = false;
      }
    },
  }
}
function pessoa(){
  return {
    nome:'', telefone:'', documento:'', lista:[], aberto:false,
    async buscar(){ if(this.nome.length<2){this.lista=[];return;}
      const r=await fetch(`{{ route('pessoas.buscar') }}?q=${encodeURIComponent(this.nome)}`); this.lista=await r.json(); this.aberto=true; },
    escolher(p){ this.nome=p.nome; this.telefone=p.telefone||''; this.documento=p.documento||''; this.aberto=false; this.lista=[]; }
  }
}
</script>
@endpush
