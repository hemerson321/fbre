@extends('layouts.app')
@section('titulo','Nova encomenda')
@push('head')
<style type="text/tailwindcss">
  /* ---- cartão de seção com cabeçalho colorido ---- */
  .sec { @apply rounded-2xl overflow-hidden flex flex-col; background:#fff; box-shadow: 0 1px 2px rgba(15,23,42,.06), 0 8px 24px -12px rgba(15,23,42,.18); }
  .sec-h { @apply flex items-center gap-2.5 px-4 py-2.5 text-white; background: linear-gradient(90deg, var(--cor1), color-mix(in srgb, var(--cor1) 70%, var(--cor2))); }
  .sec-h .n { @apply inline-flex w-6 h-6 rounded-full items-center justify-center text-[12px] font-extrabold text-slate-900; background: var(--cor2); }
  .sec-h .t { @apply text-[13px] font-bold uppercase tracking-wide; }
  .sec-b { @apply p-4 flex-1 flex flex-col gap-3; }
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
  .pessoa { @apply rounded-xl p-3 space-y-2 border; background: color-mix(in srgb, var(--cor2) 7%, white); border-color: color-mix(in srgb, var(--cor2) 30%, white); }
  @media (min-width:1024px){ .field{min-height:40px;font-size:14px} }
</style>
@endpush
@section('conteudo')
<form method="post" action="{{ route('encomendas.store') }}" x-data="novaEncomenda()" @submit="salvando=true"
      class="mx-auto max-w-[1440px] lg:h-[calc(100vh-100px-2.5rem)] lg:flex lg:flex-col gap-3">
@csrf
<div class="flex items-center justify-between shrink-0">
  <div class="flex items-center gap-3">
    <h1 class="text-xl font-extrabold" style="color:var(--cor1)">Nova encomenda</h1>
    <span class="hidden sm:inline text-xs text-slate-500">Preencha as 4 etapas e salve. O comprovante e as etiquetas saem na hora.</span>
  </div>
  <a href="{{ route('encomendas.index') }}" class="text-sm text-slate-500 hover:underline">← voltar</a>
</div>

<div class="grid gap-3 lg:grid-cols-12 lg:flex-1 lg:min-h-0">

  {{-- ===== COLUNA 1 ===== --}}
  <div class="lg:col-span-3 flex flex-col gap-3 lg:min-h-0">
    <section class="sec">
      <div class="sec-h"><span class="n">1</span><span class="t">Rota</span></div>
      <div class="sec-b">
        <div class="grid grid-cols-2 gap-2">
          <div><label class="lbl">Origem</label><input name="origem" list="locais" class="field" required value="{{ old('origem', $ultimaOrigem) }}" autocomplete="off"></div>
          <div><label class="lbl">Destino</label><input name="destino" list="locais" class="field" required value="{{ old('destino') }}" autocomplete="off" autofocus></div>
          <div><label class="lbl">Transporte</label>
            <select name="embarcacao_id" class="field"><option value="">—</option>
              @foreach($embarcacoes as $b)<option value="{{ $b->id }}" @selected(old('embarcacao_id')==$b->id)>{{ $b->nome }}</option>@endforeach
            </select></div>
          <div><label class="lbl">Setor</label><input name="setor" list="setores" class="field" value="{{ old('setor', auth()->user()->setor) }}"></div>
        </div>
        <datalist id="locais">@foreach($localidades as $l)<option value="{{ $l }}">@endforeach</datalist>
        <datalist id="setores">@foreach($setores as $s)<option value="{{ $s }}">@endforeach</datalist>
      </div>
    </section>

    <section class="sec flex-1">
      <div class="sec-h"><span class="n">2</span><span class="t">Remetente e destinatário</span></div>
      <div class="sec-b">
        @foreach(['remetente'=>['Remetente','Quem está enviando'],'destinatario'=>['Destinatário','Quem vai receber']] as $k=>[$lbl,$dica])
        <div class="pessoa" x-data="pessoa()">
          <div class="relative">
            <label class="lbl">{{ $lbl }} <span class="normal-case font-normal text-slate-400">· {{ $dica }}</span></label>
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
  <section class="sec lg:col-span-6 lg:min-h-0">
    <div class="sec-h">
      <span class="n">3</span><span class="t">Produtos / volumes</span>
      <div class="ml-auto flex items-center gap-2">
        <span class="text-[11px] font-bold uppercase opacity-80">Volumes</span>
        <input name="volumes" type="number" min="1" x-model.number="volumes" class="w-16 rounded-lg text-center font-extrabold text-slate-900 py-1 text-base" style="background:var(--cor2)" inputmode="numeric">
      </div>
    </div>
    <div class="sec-b lg:min-h-0">
      <div>
        <div class="lbl">Toque para adicionar</div>
        <div class="flex flex-wrap gap-1.5">
          @foreach($produtos as $p)
            <button type="button" class="pill" @click="addItem('{{ addslashes($p->nome) }}', {{ (float)$p->valor_padrao }})">{{ $p->nome }} <span class="v">{{ number_format($p->valor_padrao,2,',','.') }}</span></button>
          @endforeach
          <button type="button" class="pill pill-add" @click="addItem('',0)">＋ outro item</button>
        </div>
      </div>

      <div class="flex-1 lg:min-h-0 lg:overflow-auto rounded-xl border border-slate-200 bg-slate-50">
        <div class="sticky top-0 hidden lg:grid grid-cols-12 gap-2 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-100 border-b">
          <div class="col-span-5">Descrição</div><div class="col-span-2 text-center">Qtd</div><div class="col-span-2 text-right">Valor unit.</div><div class="col-span-2 text-right">Total</div><div class="col-span-1"></div>
        </div>
        <div class="p-2 space-y-1.5">
          <template x-for="(it,i) in itens" :key="it.id">
            <div class="item">
              <input :name="`itens[${i}][descricao]`" x-model="it.descricao" class="field col-span-12 lg:col-span-5" placeholder="Descrição" required>
              <input :name="`itens[${i}][quantidade]`" x-model.number="it.quantidade" type="number" step="1" min="0.01" class="field col-span-3 lg:col-span-2 text-center font-semibold" inputmode="decimal" @focus="$event.target.select()">
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
  <div class="lg:col-span-3 flex flex-col gap-3 lg:min-h-0">
    <section class="sec flex-1">
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

        <div class="mt-auto rounded-2xl p-4 text-white" style="background: linear-gradient(160deg, var(--cor1), color-mix(in srgb, var(--cor1) 75%, black))">
          <div class="flex justify-between text-xs opacity-75"><span>Subtotal</span><span x-text="FBRE.moeda(subtotal)"></span></div>
          <div class="flex justify-between text-xs opacity-75" x-show="desconto>0"><span>Desconto</span><span x-text="'- '+FBRE.moeda(desconto)"></span></div>
          <div class="flex justify-between items-baseline mt-1"><span class="text-sm font-semibold opacity-90">Total</span><span class="text-3xl font-extrabold tracking-tight" x-text="FBRE.moeda(total)"></span></div>
          <div class="h-px bg-white/20 my-2.5"></div>
          <div class="flex justify-between text-sm"><span>Pago</span><span x-text="FBRE.moeda(pago)"></span></div>
          <div class="flex justify-between items-center text-sm font-bold mt-0.5">
            <span>Devido</span>
            <span class="rounded-lg px-2 py-0.5" :class="devido>0.009 ? 'bg-amber-400 text-slate-900' : 'bg-emerald-400 text-slate-900'" x-text="devido>0.009 ? FBRE.moeda(devido) : 'Pago ✓'"></span>
          </div>
        </div>
      </div>
    </section>

    <section class="sec">
      <div class="p-3 space-y-2.5">
        <div class="flex flex-wrap gap-x-3 gap-y-1 text-[13px] text-slate-600">
          <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" name="imprimir" value="navegador" x-model="imprimir" class="accent-[var(--cor1)]"> Imprimir</label>
          @if(\App\Support\Tenant::empresa()->temRecurso('print_agent'))
          <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" name="imprimir" value="direto" x-model="imprimir" class="accent-[var(--cor1)]"> Direta</label>
          @endif
          <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" name="imprimir" value="nao" x-model="imprimir" class="accent-[var(--cor1)]"> Não imprimir</label>
        </div>
        <button class="w-full rounded-xl py-3.5 text-base font-extrabold text-slate-900 shadow-lg transition hover:brightness-105 active:scale-[.98] disabled:opacity-40 disabled:shadow-none" style="background:var(--cor2)" :disabled="salvando || !itens.length">
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
    itens:[], desconto:null, pag:{dinheiro:null,pix:null,debito:null,credito:null},
    meios:[{k:'dinheiro',l:'Dinheiro'},{k:'pix',l:'PIX'},{k:'debito',l:'Débito'},{k:'credito',l:'Crédito'}],
    addItem(d,v){ this.itens.push({id:Date.now()+Math.random(), descricao:d, quantidade:1, valor:v, obs:''}); },
    get subtotal(){ return this.itens.reduce((s,i)=>s+(Number(i.quantidade)||0)*(Number(i.valor)||0),0); },
    get total(){ return Math.max(this.subtotal-(Number(this.desconto)||0),0); },
    get pago(){ return this.forma==='faturado'?0:Object.values(this.pag).reduce((s,v)=>s+(Number(v)||0),0); },
    get pagoSemDinheiro(){ return (Number(this.pag.pix)||0)+(Number(this.pag.debito)||0)+(Number(this.pag.credito)||0); },
    get devido(){ return Math.max(this.total-this.pago,0); },
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
