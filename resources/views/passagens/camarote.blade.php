@extends('layouts.app')
@section('titulo','Vender suíte / camarote')
@section('conteudo')
<div class="max-w-xl mx-auto" x-data="vendaCamarote()">
  <div class="flex items-center justify-between mb-3">
    <h1 class="text-lg font-bold">🛏️ Vender Suíte / Camarote</h1>
    <a href="{{ route('passagens.create') }}" class="text-xs text-slate-400 hover:underline">Passagem comum →</a>
  </div>

  @if(collect($camarotes)->isEmpty())
    <div class="card p-4 text-sm text-amber-700 bg-amber-50">
      Nenhuma suíte/camarote cadastrada ainda.
      <a href="{{ route('admin.camarotes') }}" class="underline font-semibold">Cadastrar agora</a>.
    </div>
  @else
  <form method="POST" action="{{ route('passagens.camarote.store') }}" class="space-y-4" @submit="prep()">
    @csrf
    <input type="hidden" name="camarote_id" :value="cam?cam.id:''">
    <input type="hidden" name="rota_id" :value="rotaId||''">
    <input type="hidden" name="viagem_id" :value="viagemId||''">

    {{-- camarote --}}
    <div class="card p-4 space-y-3">
      <label class="block"><span class="text-xs text-slate-500">Suíte / Camarote</span>
        <select x-model.number="camId" @change="trocaCam()" class="input" required>
          <option value="">Escolha…</option>
          <template x-for="c in camarotes" :key="c.id"><option :value="c.id" x-text="c.nome+' (até '+c.capacidade+' pessoas)'"></option></template>
        </select>
      </label>
      <template x-if="cam && cam.descricao">
        <div class="text-xs text-slate-500 bg-slate-50 rounded-lg p-2 whitespace-pre-line" x-text="cam.descricao"></div>
      </template>

      <div class="grid grid-cols-2 gap-2">
        <label class="block"><span class="text-xs text-slate-500">Origem</span>
          <select x-model="origem" @change="recalc()" class="input" required>
            <option value="">—</option>
            @foreach($localidades as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach
          </select></label>
        <label class="block"><span class="text-xs text-slate-500">Destino</span>
          <select x-model="destino" @change="recalc()" class="input" required>
            <option value="">—</option>
            @foreach($localidades as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach
          </select></label>
      </div>

      <template x-if="cam && origem && destino">
        <div>
          <template x-if="base!==null">
            <div class="text-sm text-emerald-700 font-semibold">Camarote <span x-text="origem"></span> → <span x-text="destino"></span>: R$ <span x-text="fmt(base)"></span> <span class="font-normal text-slate-400">(cobre até <span x-text="cam.capacidade"></span> pessoa(s))</span></div>
          </template>
          <template x-if="base===null">
            <div class="text-sm text-red-600">Sem preço cadastrado para esse trecho nesta suíte. <a href="{{ route('admin.camarotes') }}" class="underline">Cadastrar preço</a>.</div>
          </template>
        </div>
      </template>

      <div class="grid grid-cols-2 gap-2">
        <label class="block"><span class="text-xs text-slate-500">Data da viagem</span>
          <input type="date" name="data_viagem" x-model="dataViagem" @change="resolveViagem()" class="input"></label>
        @if($rotas->isNotEmpty())
        <label class="block"><span class="text-xs text-slate-500">Rota (opcional)</span>
          <select x-model.number="rotaId" class="input">
            <option value="">Automático</option>
            @foreach($rotas as $r)<option value="{{ $r->id }}">{{ $r->nome }}</option>@endforeach
          </select></label>
        @endif
      </div>
    </div>

    {{-- ocupantes --}}
    <div class="card p-4 space-y-3">
      <div class="flex items-center justify-between">
        <span class="font-semibold text-sm">Ocupantes (um bilhete por pessoa)</span>
        <span class="text-[11px] text-slate-400" x-text="ocupantes.length+' / '+(cam?cam.capacidade:'?')+' incluído(s)'"></span>
      </div>

      <template x-for="(o,i) in ocupantes" :key="i">
        <div class="rounded-xl border border-slate-200 p-3 space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                  :class="papel(i)==='Titular'?'bg-cyan-100 text-cyan-700':(papel(i)==='Adicional'?'bg-amber-100 text-amber-700':'bg-slate-100 text-slate-600')"
                  x-text="'Pessoa '+(i+1)+' · '+papel(i)"></span>
            <button type="button" x-show="ocupantes.length>1" @click="ocupantes.splice(i,1);recalc()" class="text-red-500 text-sm">remover</button>
          </div>
          <input :name="`ocupantes[${i}][nome]`" x-model="o.nome" placeholder="Nome completo" class="input py-2" required>
          <div class="grid grid-cols-2 gap-2">
            <input :name="`ocupantes[${i}][documento]`" x-model="o.documento" placeholder="RG/CPF" class="input py-2 text-sm">
            <input :name="`ocupantes[${i}][nascimento]`" x-model="o.nascimento" type="date" class="input py-2 text-sm">
          </div>
          <template x-if="papel(i)==='Adicional'">
            <label class="block"><span class="text-[11px] text-amber-600">Adicional desta pessoa (R$) — pode editar ou deixar 0</span>
              <input :name="`ocupantes[${i}][adicional]`" x-model.number="o.adicional" @input="recalc()" type="number" step="0.01" min="0" class="input py-2 text-sm"></label>
          </template>
          <label class="flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" :name="`ocupantes[${i}][checkin]`" value="1" x-model="o.checkin" class="w-4 h-4"> Já fazer check-in</label>
        </div>
      </template>

      <button type="button" @click="addOcup()" class="btn btn-sm btn-ghost w-full">+ Adicionar pessoa</button>
    </div>

    {{-- comprador + pagamento --}}
    <div class="card p-4 space-y-3">
      <div class="flex items-center justify-between">
        <span class="font-semibold text-sm">Comprador</span>
        <button type="button" @click="compradorDoTitular()" class="text-[11px] text-cyan-600 underline">usar 1º ocupante</button>
      </div>
      <input name="comprador_nome" x-model="comprador.nome" placeholder="Nome do comprador" class="input py-2">
      <div class="grid grid-cols-2 gap-2">
        <input name="comprador_telefone" x-model="comprador.telefone" placeholder="Telefone" class="input py-2 text-sm">
        <input name="comprador_documento" x-model="comprador.documento" placeholder="Documento" class="input py-2 text-sm">
      </div>

      <label class="block"><span class="text-xs text-slate-500">Forma</span>
        <select name="forma" x-model="forma" class="input py-2">
          <option value="avista">À vista</option>
          @unless($soAvista)
          <option value="areceber">A receber</option>
          <option value="faturado">Faturado</option>
          @endunless
        </select></label>

      <template x-if="forma==='avista'">
        <div class="grid grid-cols-2 gap-2">
          <template x-for="(lbl,meio) in meios" :key="meio">
            <label class="block"><span class="text-[11px] text-slate-500" x-text="lbl"></span>
              <input type="number" step="0.01" min="0" x-model.number="pag[meio]" class="input py-2 text-sm" placeholder="0,00"></label>
          </template>
        </div>
      </template>
      {{-- pagamentos ocultos p/ envio --}}
      <template x-for="(lbl,meio) in meios" :key="'h'+meio">
        <span>
          <input type="hidden" :name="`pagamentos[${meio}][meio]`" :value="meio">
          <input type="hidden" :name="`pagamentos[${meio}][valor]`" :value="(forma==='avista'?(pag[meio]||0):0)">
        </span>
      </template>
    </div>

    {{-- total + enviar --}}
    <div class="card p-4 space-y-3">
      <div class="flex items-center justify-between text-lg font-bold">
        <span>Total</span><span>R$ <span x-text="fmt(total())"></span></span>
      </div>
      <div class="text-[11px] text-slate-400" x-show="adicionalTotal()>0">Camarote R$ <span x-text="fmt(base||0)"></span> + adicionais R$ <span x-text="fmt(adicionalTotal())"></span></div>
      <select name="imprimir" class="input py-2 text-sm">
        <option value="navegador">Imprimir comprovante (navegador)</option>
        <option value="nao">Não imprimir</option>
      </select>
      <button class="btn btn-primary w-full" :disabled="!cam || base===null || !origem || !destino" x-text="(base===null&&cam&&origem&&destino)?'Sem preço p/ esse trecho':'Salvar camarote'"></button>
    </div>
  </form>
  @endif
</div>

<script>
function vendaCamarote(){
  return {
    camarotes: @json($camarotes),
    linhas: @json($linhas),
    camId:'', cam:null,
    origem: @json($ultimaOrigem ?? ''), destino:'',
    dataViagem:'', rotaId:'', viagemId:'',
    base:null,
    ocupantes:[{nome:'',documento:'',nascimento:'',adicional:0,checkin:{{ $checkinAuto ? 'true':'false' }}}],
    comprador:{nome:'',telefone:'',documento:''},
    forma:'avista',
    meios:{dinheiro:'Dinheiro',pix:'PIX',debito:'Débito',credito:'Crédito'},
    pag:{dinheiro:0,pix:0,debito:0,credito:0},
    fmt(v){ return (Number(v)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); },
    trocaCam(){ this.cam=this.camarotes.find(c=>c.id===this.camId)||null; this.recalc(); },
    recalc(){
      if(this.cam && this.origem && this.destino){
        const k=(this.origem+'|'+this.destino).toLowerCase();
        const v=this.cam.precos[k];
        this.base=(v===undefined||v===null)?null:Number(v);
      } else { this.base=null; }
    },
    papel(i){ if(i===0) return 'Titular'; if(this.cam && i < this.cam.capacidade) return 'Incluído'; return 'Adicional'; },
    addOcup(){ const ad=this.cam?Number(this.cam.adicional||0):0; this.ocupantes.push({nome:'',documento:'',nascimento:'',adicional:ad,checkin:{{ $checkinAuto ? 'true':'false' }}}); },
    adicionalTotal(){ let s=0; this.ocupantes.forEach((o,i)=>{ if(this.papel(i)==='Adicional') s+=Number(o.adicional||0); }); return s; },
    total(){ return (this.base||0) + this.adicionalTotal(); },
    compradorDoTitular(){ const t=this.ocupantes[0]; if(t){ this.comprador.nome=t.nome; this.comprador.documento=t.documento||''; } },
    resolveViagem(){ /* resolução fica no servidor via rota_id + data */ },
    prep(){ if(!this.comprador.nome && this.ocupantes[0]) this.comprador.nome=this.ocupantes[0].nome; },
  };
}
</script>
@endsection
