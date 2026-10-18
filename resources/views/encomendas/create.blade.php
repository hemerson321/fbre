@extends('layouts.app')
@section('titulo','Nova encomenda')
@section('conteudo')
<form method="post" action="{{ route('encomendas.store') }}" x-data="novaEncomenda()" @submit="salvando=true" class="space-y-4 max-w-5xl mx-auto">
@csrf
<div class="flex items-center justify-between">
  <h1 class="text-xl font-bold">Nova encomenda</h1>
  <a href="{{ route('encomendas.index') }}" class="text-sm text-slate-500 hover:underline">← voltar</a>
</div>

<div class="grid lg:grid-cols-2 gap-4">

  {{-- ROTA --}}
  <section class="card p-4 space-y-3">
    <h2 class="font-bold text-sm text-slate-500 uppercase">1 · Rota</h2>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="label">Origem</label>
        <input name="origem" list="locais" class="input" required value="{{ old('origem', $ultimaOrigem) }}" autocomplete="off"></div>
      <div><label class="label">Destino</label>
        <input name="destino" list="locais" class="input" required value="{{ old('destino') }}" autocomplete="off"></div>
    </div>
    <datalist id="locais">@foreach($localidades as $l)<option value="{{ $l }}">@endforeach</datalist>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="label">Transporte</label>
        <select name="embarcacao_id" class="input">
          <option value="">—</option>
          @foreach($embarcacoes as $b)<option value="{{ $b->id }}" @selected(old('embarcacao_id')==$b->id)>{{ $b->nome }}</option>@endforeach
        </select></div>
      <div><label class="label">Setor</label>
        <input name="setor" list="setores" class="input" value="{{ old('setor', auth()->user()->setor) }}">
        <datalist id="setores">@foreach($setores as $s)<option value="{{ $s }}">@endforeach</datalist></div>
    </div>
  </section>

  {{-- PESSOAS --}}
  <section class="card p-4 space-y-3">
    <h2 class="font-bold text-sm text-slate-500 uppercase">2 · Remetente e destinatário</h2>
    @foreach(['remetente'=>'Remetente','destinatario'=>'Destinatário'] as $k=>$lbl)
    <div class="rounded-xl bg-slate-50 p-3 space-y-2" x-data="pessoa('{{ $k }}')">
      <div class="relative">
        <label class="label">{{ $lbl }}</label>
        <input name="{{ $k }}_nome" class="input" required value="{{ old($k.'_nome') }}" autocomplete="off"
               x-model="nome" @input.debounce.300ms="buscar" @focus="aberto=true" @click.outside="aberto=false" placeholder="Nome">
        <div x-show="aberto && lista.length" x-cloak class="absolute z-20 mt-1 w-full card max-h-56 overflow-auto">
          <template x-for="p in lista" :key="p.nome+p.telefone">
            <button type="button" @click="escolher(p)" class="w-full text-left px-3 py-2 hover:bg-slate-50 text-sm border-b last:border-0">
              <span class="font-semibold" x-text="p.nome"></span> <span class="text-slate-500" x-text="p.telefone"></span>
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

  {{-- PRODUTOS --}}
  <section class="card p-4 space-y-3 lg:col-span-2">
    <div class="flex items-center justify-between">
      <h2 class="font-bold text-sm text-slate-500 uppercase">3 · Produtos / volumes</h2>
      <div class="flex items-center gap-2">
        <label class="label mb-0">Volumes</label>
        <input name="volumes" type="number" min="1" x-model.number="volumes" class="input !w-20 text-center" inputmode="numeric">
      </div>
    </div>

    <div class="flex flex-wrap gap-2">
      @foreach($produtos as $p)
        <button type="button" class="btn btn-sm btn-ghost" @click="addItem('{{ addslashes($p->nome) }}', {{ (float)$p->valor_padrao }})">{{ $p->nome }} <span class="text-slate-400">{{ number_format($p->valor_padrao,2,',','.') }}</span></button>
      @endforeach
      <button type="button" class="btn btn-sm btn-accent" @click="addItem('',0)">+ outro</button>
    </div>

    <div class="space-y-2">
      <template x-for="(it,i) in itens" :key="it.id">
        <div class="grid grid-cols-12 gap-2 items-center">
          <input :name="`itens[${i}][descricao]`" x-model="it.descricao" class="input col-span-12 sm:col-span-5" placeholder="Descrição" required>
          <input :name="`itens[${i}][quantidade]`" x-model.number="it.quantidade" type="number" step="0.01" min="0.01" class="input col-span-3 sm:col-span-2 text-center" inputmode="decimal" placeholder="Qtd">
          <input :name="`itens[${i}][valor_unitario]`" x-model.number="it.valor" type="number" step="0.01" min="0" class="input col-span-4 sm:col-span-2 text-right" inputmode="decimal" placeholder="Valor">
          <div class="col-span-4 sm:col-span-2 text-right font-bold" x-text="FBRE.moeda(it.quantidade*it.valor)"></div>
          <button type="button" @click="itens.splice(i,1)" class="col-span-1 text-red-500 text-xl">✕</button>
          <input :name="`itens[${i}][observacao]`" x-model="it.obs" class="input col-span-12 !min-h-[40px] text-sm" placeholder="Observação do item (opcional)">
        </div>
      </template>
      <p x-show="!itens.length" class="text-sm text-slate-400 text-center py-4">Toque em um produto acima para adicionar.</p>
    </div>
    <div><label class="label">Observação geral</label><textarea name="observacao" rows="2" class="input !min-h-[60px] py-2">{{ old('observacao') }}</textarea></div>
  </section>

  {{-- PAGAMENTO --}}
  <section class="card p-4 space-y-3 lg:col-span-2">
    <h2 class="font-bold text-sm text-slate-500 uppercase">4 · Pagamento</h2>
    <div class="grid sm:grid-cols-3 gap-2">
      @foreach(\App\Models\Encomenda::FORMAS as $k=>$l)
      <label class="btn btn-ghost cursor-pointer" :class="forma==='{{ $k }}' && 'ring-2 ring-[var(--cor2)] bg-sky-50'">
        <input type="radio" name="forma" value="{{ $k }}" x-model="forma" class="sr-only"> {{ $l }}
      </label>
      @endforeach
    </div>

    <div class="grid sm:grid-cols-[1fr_auto] gap-4 items-start">
      <div x-show="forma!=='faturado'" class="space-y-2">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <template x-for="m in meios" :key="m.k">
            <div><label class="label" x-text="m.l"></label>
              <input type="number" step="0.01" min="0" inputmode="decimal" class="input text-right" x-model.number="pag[m.k]" placeholder="0,00"></div>
          </template>
        </div>
        <template x-for="m in meios" :key="'h'+m.k">
          <template x-if="pag[m.k]>0">
            <span><input type="hidden" :name="`pagamentos[${m.k}][meio]`" :value="m.k"><input type="hidden" :name="`pagamentos[${m.k}][valor]`" :value="pag[m.k]"></span>
          </template>
        </template>
        <div class="grid grid-cols-2 gap-2 max-w-xs">
          <div><label class="label">Desconto</label><input name="desconto" type="number" step="0.01" min="0" inputmode="decimal" class="input text-right" x-model.number="desconto" placeholder="0,00"></div>
          <div class="flex items-end"><button type="button" class="btn btn-ghost w-full" @click="pag.dinheiro = Math.max(total - pagoSemDinheiro, 0)">Receber tudo</button></div>
        </div>
      </div>
      <div class="rounded-2xl p-4 text-white min-w-[220px]" style="background: var(--cor1)">
        <div class="flex justify-between text-sm opacity-80"><span>Subtotal</span><span x-text="FBRE.moeda(subtotal)"></span></div>
        <div class="flex justify-between text-sm opacity-80" x-show="desconto>0"><span>Desconto</span><span x-text="'- '+FBRE.moeda(desconto)"></span></div>
        <div class="flex justify-between text-lg font-extrabold mt-1"><span>Total</span><span x-text="FBRE.moeda(total)"></span></div>
        <div class="flex justify-between text-sm mt-2"><span>Pago</span><span x-text="FBRE.moeda(pago)"></span></div>
        <div class="flex justify-between text-sm font-bold" :class="devido>0.009?'text-amber-300':'text-emerald-300'"><span>Devido</span><span x-text="FBRE.moeda(devido)"></span></div>
      </div>
    </div>
  </section>
</div>

{{-- AÇÕES --}}
<div class="card p-3 flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between sticky bottom-20 lg:bottom-4">
  <div class="flex gap-2 text-sm">
    <label class="flex items-center gap-1"><input type="radio" name="imprimir" value="navegador" x-model="imprimir"> Imprimir</label>
    @if(\App\Support\Tenant::empresa()->temRecurso('print_agent'))
    <label class="flex items-center gap-1"><input type="radio" name="imprimir" value="direto" x-model="imprimir"> Impressão direta</label>
    @endif
    <label class="flex items-center gap-1"><input type="radio" name="imprimir" value="nao" x-model="imprimir"> Não imprimir</label>
  </div>
  <button class="btn btn-accent text-base sm:min-w-[220px]" :disabled="salvando || !itens.length">
    <span x-show="!salvando">💾 Salvar encomenda</span><span x-show="salvando">Salvando…</span>
  </button>
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
function pessoa(prefixo){
  return {
    nome:'', telefone:'', documento:'', lista:[], aberto:false,
    async buscar(){ if(this.nome.length<2){this.lista=[];return;}
      const r=await fetch(`{{ route('pessoas.buscar') }}?q=${encodeURIComponent(this.nome)}`); this.lista=await r.json(); this.aberto=true; },
    escolher(p){ this.nome=p.nome; this.telefone=p.telefone||''; this.documento=p.documento||''; this.aberto=false; this.lista=[]; }
  }
}
</script>
@endpush
