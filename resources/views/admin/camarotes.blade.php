@extends('layouts.app')
@section('titulo','Suítes / Camarotes')
@section('conteudo')
<div class="max-w-3xl mx-auto space-y-5">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-xl font-bold">🛏️ Suítes / Camarotes</h1>
      <p class="text-sm text-slate-500">Cadastre o tipo (capacidade e o que tem) e o preço por trecho da rota — igual à tabela de preços da passagem. Pessoas além da capacidade entram como adicional na venda.</p>
    </div>
    <a href="{{ route('passagens.camarote.create') }}" class="btn btn-sm btn-primary">🎫 Vender</a>
  </div>

  @foreach($camarotes as $c)
  <div class="card p-4 space-y-3"
       x-data="camCard(
         {{ $c->id }},
         @js($rotas),
         @js($c->precos->mapWithKeys(fn($p)=>[mb_strtolower($p->origem.'|'.$p->destino)=>number_format((float)$p->valor,2,'.','')]))
       )">
    <div class="flex items-center justify-between">
      <div class="cursor-pointer" @click="ab=!ab">
        <span class="font-bold">🛏️ {{ $c->nome }}</span>
        <span class="text-xs text-slate-400">· até {{ $c->capacidade }} pessoa(s) · {{ $c->precos->count() }} trecho(s) @if(!$c->ativo)· inativo @endif</span>
      </div>
      <span class="text-slate-400 cursor-pointer" @click="ab=!ab" x-text="ab?'▲':'▼'"></span>
    </div>

    <div x-show="ab" x-cloak class="pt-2 border-t space-y-4">
      {{-- dados do tipo --}}
      <form method="POST" action="{{ route('admin.camarotes.salvar', $c) }}" class="space-y-3">
        @csrf
        <div class="grid grid-cols-2 gap-2">
          <label class="block"><span class="text-xs text-slate-500">Nome</span>
            <input name="nome" value="{{ $c->nome }}" class="input" required></label>
          <label class="block"><span class="text-xs text-slate-500">Capacidade (pessoas)</span>
            <input name="capacidade" type="number" min="1" max="50" value="{{ $c->capacidade }}" class="input" required></label>
        </div>
        <label class="block"><span class="text-xs text-slate-500">Descrição (TV, ar-condicionado, cama de casal…)</span>
          <textarea name="descricao" rows="2" class="input py-2">{{ $c->descricao }}</textarea></label>
        <div class="grid grid-cols-2 gap-2 items-end">
          <label class="block"><span class="text-xs text-slate-500">Adicional por pessoa extra (R$)</span>
            <input name="valor_adicional" type="number" step="0.01" min="0" value="{{ number_format((float)$c->valor_adicional,2,'.','') }}" class="input"></label>
          <label class="flex items-center gap-2 pb-3"><input type="checkbox" name="ativo" value="1" @checked($c->ativo) class="w-5 h-5"> <span class="text-sm">Ativo</span></label>
        </div>
        <div class="flex gap-2">
          <button class="btn btn-sm btn-primary">Salvar dados</button>
          <button form="del-cam-{{ $c->id }}" class="btn btn-sm btn-danger ml-auto" onclick="return confirm('Remover este camarote e seus preços?')">Remover</button>
        </div>
      </form>
      <form id="del-cam-{{ $c->id }}" method="POST" action="{{ route('admin.camarotes.excluir', $c) }}" class="hidden">@csrf @method('DELETE')</form>

      {{-- preços por trecho, a partir da ROTA --}}
      <form method="POST" action="{{ route('admin.camarotes.precos', $c) }}" class="space-y-2">
        @csrf
        <div class="text-xs font-semibold text-slate-600 uppercase">Preço por trecho</div>

        @if(collect($rotas)->isEmpty())
          <p class="text-xs text-amber-600">Cadastre uma rota primeiro em <a href="{{ route('admin.rotas') }}" class="underline">Rotas</a> pra listar os trechos aqui.</p>
        @else
          <label class="block"><span class="text-xs text-slate-500">Rota</span>
            <select x-model.number="rotaId" @change="gerar()" class="input py-2">
              <option value="">Escolha a rota…</option>
              <template x-for="r in rotas" :key="r.id"><option :value="r.id" x-text="r.nome"></option></template>
            </select>
          </label>

          <template x-if="rotaId && trechos.length">
            <div class="space-y-1.5">
              <div class="flex items-center gap-2">
                <input x-model="filtro" placeholder="🔎 filtrar cidade" class="input !min-h-[36px] flex-1 text-sm">
                <span class="text-[11px] text-slate-400" x-text="preenchidos()+' de '+trechos.length"></span>
              </div>
              <template x-for="t in trechosFiltrados()" :key="t.key">
                <div class="flex items-center gap-2">
                  <span class="text-sm flex-1 truncate"><span x-text="t.o"></span> <span class="text-slate-400">→</span> <span x-text="t.d"></span></span>
                  <div class="relative">
                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 text-xs">R$</span>
                    <input type="number" step="0.01" min="0" x-model="vals[t.key]" placeholder="0,00" class="input !min-h-[36px] w-28 pl-7 text-right text-sm">
                  </div>
                </div>
              </template>
            </div>
          </template>
          <template x-if="rotaId && !trechos.length">
            <p class="text-xs text-slate-400">Essa rota não tem paradas suficientes.</p>
          </template>

          {{-- hidden p/ envio: todos os trechos da rota escolhida --}}
          <template x-for="t in trechos" :key="'h'+t.key">
            <span class="hidden">
              <input type="hidden" :name="`precos[${t.key}][origem]`" :value="t.o">
              <input type="hidden" :name="`precos[${t.key}][destino]`" :value="t.d">
              <input type="hidden" :name="`precos[${t.key}][valor]`" :value="vals[t.key] || ''">
            </span>
          </template>

          <div class="flex">
            <button class="btn btn-sm btn-accent ml-auto" :disabled="!rotaId">Salvar preços</button>
          </div>
          <p class="text-[11px] text-slate-400">Deixe em branco (ou 0) pra não vender aquele trecho nesta suíte. Salva um sentido; o sistema aceita ida e volta.</p>
        @endif
      </form>
    </div>
  </div>
  @endforeach

  {{-- novo camarote --}}
  <div class="card p-4 space-y-3">
    <div class="font-bold text-sm text-slate-600 uppercase">➕ Novo tipo de suíte/camarote</div>
    <form method="POST" action="{{ route('admin.camarotes.salvar') }}" class="space-y-3">
      @csrf
      <div class="grid grid-cols-2 gap-2">
        <label class="block"><span class="text-xs text-slate-500">Nome</span>
          <input name="nome" placeholder="Ex.: Suíte Master" class="input" required></label>
        <label class="block"><span class="text-xs text-slate-500">Capacidade (pessoas)</span>
          <input name="capacidade" type="number" min="1" max="50" value="2" class="input" required></label>
      </div>
      <label class="block"><span class="text-xs text-slate-500">Descrição (TV, ar-condicionado, cama de casal…)</span>
        <textarea name="descricao" rows="2" class="input py-2"></textarea></label>
      <label class="block"><span class="text-xs text-slate-500">Adicional por pessoa extra (R$)</span>
        <input name="valor_adicional" type="number" step="0.01" min="0" value="0" class="input"></label>
      <input type="hidden" name="ativo" value="1">
      <button class="btn btn-primary">Criar</button>
      <p class="text-xs text-slate-400">Depois de criar, abra o card, escolha a rota e preencha o preço de cada trecho.</p>
    </form>
  </div>
</div>

@push('scripts')
<script>
function camCard(id, rotas, precos){
  return {
    id, rotas, vals: Object.assign({}, precos||{}),
    ab:false, rotaId:'', filtro:'', trechos:[],
    gerar(){
      const r = this.rotas.find(x=>x.id===this.rotaId);
      const out=[]; const seen=new Set();
      if(r){
        const c=(r.cidades||[]).map(x=>(x||'').trim()).filter(Boolean);
        for(let i=0;i<c.length;i++){ for(let j=0;j<c.length;j++){ if(i===j) continue;
          const o=c[i], d=c[j]; if(o.toLowerCase()===d.toLowerCase()) continue;
          const key=(o+'|'+d).toLowerCase(); if(seen.has(key)) continue; seen.add(key);
          out.push({key, o, d});
        }}
      }
      this.trechos=out;
    },
    trechosFiltrados(){ const f=(this.filtro||'').trim().toLowerCase(); if(!f) return this.trechos;
      return this.trechos.filter(t=>(t.o+' '+t.d).toLowerCase().includes(f)); },
    preenchidos(){ return this.trechos.filter(t=>Number(this.vals[t.key])>0).length; },
  };
}
</script>
@endpush
@endsection
