@extends('layouts.app')
@section('titulo','Nova encomenda')
@push('head')
<style type="text/tailwindcss">
  .sec-t { @apply flex items-center gap-2 text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-2; }
  .sec-n { @apply inline-flex w-5 h-5 rounded-md items-center justify-center text-white text-[11px]; background: var(--cor2); }
  .prod { @apply rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-[var(--cor2)] hover:text-[var(--cor1)] active:scale-95 transition; }
  .forma { @apply flex-1 rounded-lg border border-slate-200 bg-white py-2 text-xs font-semibold text-center cursor-pointer transition; }
  .forma.on { background: var(--cor1); color:#fff; border-color: var(--cor1); }
  @media (min-width:1024px){
    .input { min-height: 38px; font-size: 14px; }
    .label { margin-bottom: 2px; font-size: 10.5px; }
    .card { @apply rounded-xl; }
  }
</style>
@endpush
@section('conteudo')
<form method="post" action="{{ route('encomendas.store') }}" x-data="novaEncomenda()" @submit="salvando=true"
      class="lg:h-[calc(100vh-56px-3rem)] lg:flex lg:flex-col gap-3">
@csrf
<div class="flex items-center justify-between shrink-0">
  <h1 class="text-lg font-extrabold">Nova encomenda</h1>
  <a href="{{ route('encomendas.index') }}" class="text-sm text-slate-500 hover:underline">← voltar</a>
</div>

<div class="grid gap-3 lg:grid-cols-12 lg:flex-1 lg:min-h-0">

  {{-- ===== COLUNA 1: ROTA + PESSOAS ===== --}}
  <div class="lg:col-span-4 flex flex-col gap-3 lg:min-h-0">
    <section class="card p-3">
      <div class="sec-t"><span class="sec-n">1</span> Rota</div>
      <div class="grid grid-cols-2 gap-2">
        <div><label class="label">Origem</label>
          <input name="origem" list="locais" class="input" required value="{{ old('origem', $ultimaOrigem) }}" autocomplete="off"></div>
        <div><label class="label">Destino</label>
          <input name="destino" list="locais" class="input" required value="{{ old('destino') }}" autocomplete="off" autofocus></div>
        <div><label class="label">Transporte</label>
          <select name="embarcacao_id" class="input">
            <option value="">—</option>
            @foreach($embarcacoes as $b)<option value="{{ $b->id }}" @selected(old('embarcacao_id')==$b->id)>{{ $b->nome }}</option>@endforeach
          </select></div>
        <div><label class="label">Setor</label>
          <input name="setor" list="setores" class="input" value="{{ old('setor', auth()->user()->setor) }}"></div>
      </div>
      <datalist id="locais">@foreach($localidades as $l)<option value="{{ $l }}">@endforeach</datalist>
      <datalist id="setores">@foreach($setores as $s)<option value="{{ $s }}">@endforeach</datalist>
    </section>

    <section class="card p-3 flex-1 flex flex-col gap-3">
      <div class="sec-t"><span class="sec-n">2</span> Remetente e destinatário</div>
      @foreach(['remetente'=>'Remetente','destinatario'=>'Destinatário'] as $k=>$lbl)
      <div class="rounded-lg bg-slate-50 border border-slate-100 p-2.5 space-y-2" x-data="pessoa()">
        <div class="relative">
          <label class="label">{{ $lbl }}</label>
          <input name="{{ $k }}_nome" class="input" required value="{{ old($k.'_nome') }}" autocomplete="off"
                 x-model="nome" @input.debounce.300ms="buscar" @focus="aberto=true" @click.outside="aberto=false" placeholder="Nome completo">
          <div x-show="aberto && lista.length" x-cloak class="absolute z-20 mt-1 w-full card max-h-48 overflow-auto shadow-lg">
            <template x-for="p in lista" :key="p.nome+p.telefone">
              <button type="button" @click="escolher(p)" class="w-full text-left px-3 py-2 hover:bg-slate-50 text-sm border-b last:border-0">
                <span class="font-semibold" x-text="p.nome"></span> <span class="text-slate-500 text-xs" x-text="p.telefone"></span>
              </button>
            </template>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <input name="{{ $k }}_telefone" class="input" inputmode="tel" placeholder="Telefone / WhatsApp" x-model="telefone" value="{{ old($k.'_telefone') }}">
          <input name="{{ $k }}_documento" class="input" inputmode="numeric" placeholder="CPF / CNPJ" x-model="documento" value="{{ old($k.'_documento') }}">
        </div>
      </div>
      @endforeach
    </section>
  </div>

  {{-- ===== COLUNA 2: PRODUTOS ===== --}}
  <section class="card p-3 lg:col-span-5 flex flex-col lg:min-h-0">
    <div class="flex items-center justify-between mb-2">
      <div class="sec-t !mb-0"><span class="sec-n">3</span> Produtos / volumes</div>
      <div class="flex items-center gap-2">
        <label class="label !mb-0">Volumes</label>
        <input name="volumes" type="number" min="1" x-model.number="volumes" class="input !w-16 !min-h-[34px] text-center font-bold" inputmode="numeric">
      </div>
    </div>

    <div class="flex flex-wrap gap-1.5 mb-2">
      @foreach($produtos as $p)
        <button type="button" class="prod" @click="addItem('{{ addslashes($p->nome) }}', {{ (float)$p->valor_padrao }})">{{ $p->nome }} <span class="text-slate-400 font-normal">{{ number_format($p->valor_padrao,2,',','.') }}</span></button>
      @endforeach
      <button type="button" class="prod !border-dashed" @click="addItem('',0)">+ outro</button>
    </div>

    <div class="flex-1 lg:min-h-0 lg:overflow-auto rounded-lg border border-slate-100 bg-slate-50/60">
      <div class="hidden lg:grid grid-cols-12 gap-2 px-2 pt-2 text-[10px] font-bold uppercase text-slate-400">
        <div class="col-span-6">Descrição</div><div class="col-span-2 text-center">Qtd</div><div class="col-span-2 text-right">Valor</div><div class="col-span-2 text-right">Total</div>
      </div>
      <div class="p-2 space-y-1.5">
        <template x-for="(it,i) in itens" :key="it.id">
          <div class="grid grid-cols-12 gap-1.5 items-center bg-white rounded-lg p-1.5 border border-slate-100">
            <input :name="`itens[${i}][descricao]`" x-model="it.descricao" class="input !min-h-[34px] col-span-12 lg:col-span-6 text-sm" placeholder="Descrição" required>
            <input :name="`itens[${i}][quantidade]`" x-model.number="it.quantidade" type="number" step="0.01" min="0.01" class="input !min-h-[34px] col-span-3 lg:col-span-2 text-center text-sm" inputmode="decimal">
            <input :name="`itens[${i}][valor_unitario]`" x-model.number="it.valor" type="number" step="0.01" min="0" class="input !min-h-[34px] col-span-4 lg:col-span-2 text-right text-sm" inputmode="decimal">
            <div class="col-span-4 lg:col-span-1 text-right text-sm font-bold whitespace-nowrap" x-text="FBRE.moeda(it.quantidade*it.valor)"></div>
            <button type="button" @click="itens.splice(i,1)" class="col-span-1 text-slate-400 hover:text-red-500 text-lg leading-none text-right">×</button>
            <input :name="`itens[${i}][observacao]`" x-model="it.obs" class="col-span-12 text-xs bg-transparent border-0 border-b border-dashed border-slate-200 px-1 py-0.5 focus:outline-none focus:border-[var(--cor2)]" placeholder="observação do item (opcional)">
          </div>
        </template>
        <p x-show="!itens.length" class="text-xs text-slate-400 text-center py-6">Toque em um produto acima para adicionar.</p>
      </div>
    </div>
    <input name="observacao" class="input !min-h-[36px] mt-2 text-sm" placeholder="Observação geral da encomenda (opcional)" value="{{ old('observacao') }}">
  </section>

  {{-- ===== COLUNA 3: PAGAMENTO + TOTAIS + SALVAR ===== --}}
  <div class="lg:col-span-3 flex flex-col gap-3 lg:min-h-0">
    <section class="card p-3 flex-1 flex flex-col gap-2.5">
      <div class="sec-t"><span class="sec-n">4</span> Pagamento</div>
      <div class="flex gap-1.5">
        @foreach(\App\Models\Encomenda::FORMAS as $k=>$l)
        <label class="forma" :class="forma==='{{ $k }}' && 'on'">
          <input type="radio" name="forma" value="{{ $k }}" x-model="forma" class="sr-only"> {{ $l }}
        </label>
        @endforeach
      </div>

      <div x-show="forma!=='faturado'" class="grid grid-cols-2 gap-2">
        <template x-for="m in meios" :key="m.k">
          <div><label class="label" x-text="m.l"></label>
            <input type="number" step="0.01" min="0" inputmode="decimal" class="input text-right" x-model.number="pag[m.k]" placeholder="0,00"></div>
        </template>
        <div><label class="label">Desconto</label><input name="desconto" type="number" step="0.01" min="0" inputmode="decimal" class="input text-right" x-model.number="desconto" placeholder="0,00"></div>
        <div class="flex items-end"><button type="button" class="btn btn-ghost btn-sm w-full !min-h-[38px]" @click="pag.dinheiro = Math.max(total - pagoSemDinheiro, 0)">Receber tudo</button></div>
      </div>
      <template x-for="m in meios" :key="'h'+m.k">
        <template x-if="pag[m.k]>0 && forma!=='faturado'">
          <span><input type="hidden" :name="`pagamentos[${m.k}][meio]`" :value="m.k"><input type="hidden" :name="`pagamentos[${m.k}][valor]`" :value="pag[m.k]"></span>
        </template>
      </template>
      <p x-show="forma==='faturado'" class="text-xs text-slate-500 bg-amber-50 border border-amber-200 rounded-lg p-2">Faturado: nada é recebido agora. A cobrança fica pendente.</p>

      <div class="mt-auto rounded-xl p-3 text-white" style="background: var(--cor1)">
        <div class="flex justify-between text-xs opacity-75"><span>Subtotal</span><span x-text="FBRE.moeda(subtotal)"></span></div>
        <div class="flex justify-between text-xs opacity-75" x-show="desconto>0"><span>Desconto</span><span x-text="'- '+FBRE.moeda(desconto)"></span></div>
        <div class="flex justify-between items-baseline mt-1"><span class="text-sm font-semibold">Total</span><span class="text-2xl font-extrabold" x-text="FBRE.moeda(total)"></span></div>
        <div class="h-px bg-white/20 my-2"></div>
        <div class="flex justify-between text-sm"><span>Pago</span><span x-text="FBRE.moeda(pago)"></span></div>
        <div class="flex justify-between text-sm font-bold" :class="devido>0.009?'text-amber-300':'text-emerald-300'"><span>Devido</span><span x-text="FBRE.moeda(devido)"></span></div>
      </div>
    </section>

    <section class="card p-3 space-y-2">
      <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs">
        <label class="flex items-center gap-1 cursor-pointer"><input type="radio" name="imprimir" value="navegador" x-model="imprimir"> Imprimir</label>
        @if(\App\Support\Tenant::empresa()->temRecurso('print_agent'))
        <label class="flex items-center gap-1 cursor-pointer"><input type="radio" name="imprimir" value="direto" x-model="imprimir"> Direta</label>
        @endif
        <label class="flex items-center gap-1 cursor-pointer"><input type="radio" name="imprimir" value="nao" x-model="imprimir"> Não imprimir</label>
      </div>
      <button class="btn btn-accent w-full text-base" :disabled="salvando || !itens.length">
        <span x-show="!salvando">Salvar encomenda</span><span x-show="salvando">Salvando…</span>
      </button>
      <p class="text-[11px] text-slate-400 text-center" x-show="!itens.length">Adicione ao menos um produto.</p>
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
    itens:[], desconto:0, pag:{dinheiro:0,pix:0,debito:0,credito:0},
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
