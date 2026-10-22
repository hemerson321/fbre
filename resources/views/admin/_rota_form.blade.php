@php $edit = (bool) $rota; @endphp
<form method="post" action="{{ $edit ? route('admin.rotas.update',$rota) : route('admin.rotas.store') }}"
      x-data="rotaForm({{ $edit ? $rota->paradas->map(fn($p)=>['localidade'=>$p->localidade,'dia_semana'=>$p->dia_semana,'hora'=>$p->hora? substr($p->hora,0,5):''])->toJson() : '[]' }}, @js($precosMapa ?? []), @js(['taxa'=>($usaTaxaServico ?? false),'alim'=>($usaAlimentacao ?? false)]))" class="space-y-3">
@csrf @if($edit) @method('PUT') @endif
<div class="grid sm:grid-cols-2 gap-3">
  <div><label class="label">Nome da rota</label><input name="nome" class="input" required value="{{ $edit ? $rota->nome : '' }}" placeholder="Ex.: Linha Tefé"></div>
  <div><label class="label">Embarcação (opcional)</label>
    <select name="embarcacao_id" class="input"><option value="">—</option>
      @foreach($embarcacoes as $b)<option value="{{ $b->id }}" @selected($edit && $rota->embarcacao_id==$b->id)>{{ $b->nome }}</option>@endforeach
    </select></div>
</div>

<div>
  <div class="flex items-center justify-between mb-1">
    <label class="label !mb-0">Paradas (na ordem da viagem)</label>
    <button type="button" class="btn btn-ghost btn-sm" @click="add()">＋ parada</button>
  </div>
  <div class="space-y-2">
    <template x-for="(p,i) in paradas" :key="i">
      <div class="grid grid-cols-12 gap-2 items-center">
        <span class="col-span-1 text-center text-slate-400 font-bold" x-text="i+1"></span>
        <input :name="`paradas[${i}][localidade]`" x-model="p.localidade" list="locais" class="input col-span-5" placeholder="Cidade" required>
        <select :name="`paradas[${i}][dia_semana]`" x-model="p.dia_semana" class="input col-span-3">
          <option value="">dia —</option>
          @foreach($dias as $k=>$d)<option value="{{ $k }}">{{ $d }}</option>@endforeach
        </select>
        <input :name="`paradas[${i}][hora]`" x-model="p.hora" type="time" class="input col-span-2">
        <button type="button" @click="paradas.splice(i,1)" class="col-span-1 text-red-500 text-xl">×</button>
      </div>
    </template>
  </div>
  <datalist id="locais">@foreach($localidades as $l)<option value="{{ $l }}">@endforeach</datalist>
  <p class="text-xs text-slate-400 mt-1">A 1ª parada define quando a viagem começa (dia da semana da partida).</p>
</div>
<div x-show="trechos.length" x-cloak class="border-t pt-3">
  <label class="label">Preços das passagens (por trecho)</label>
  <p class="text-xs text-slate-400 mb-2">Digite o valor de cada origem → destino. <b>Tarifa</b> é o transporte<span x-show="usaTaxa"> · <b>Serviço</b> é a taxa de serviço</span><span x-show="usaAlim"> · <b>Alim.</b> é a alimentação</span>. Subida e descida podem ter preços diferentes; se deixar o inverso em branco, usa o outro sentido.</p>
  <div class="grid gap-x-4 gap-y-1.5" :class="(usaTaxa||usaAlim)?'sm:grid-cols-1':'sm:grid-cols-2'">
    <template x-for="t in trechos" :key="t.key">
      <div class="flex items-center gap-2 flex-wrap" x-data="{c: ensure(t.key)}">
        <span class="text-sm flex-1 min-w-[120px] truncate" x-text="t.o + ' → ' + t.d"></span>
        <label class="flex items-center gap-1 shrink-0"><span class="text-[11px] text-slate-400 w-10 text-right">Tarifa</span>
          <div class="relative"><span class="absolute left-1.5 top-1/2 -translate-y-1/2 text-slate-400 text-[11px]">R$</span>
          <input type="number" step="0.01" min="0" class="input !min-h-[34px] !w-24 !pl-6 text-right text-sm" x-model.number="c.p" placeholder="0,00"></div></label>
        <label class="flex items-center gap-1 shrink-0" x-show="usaTaxa"><span class="text-[11px] text-slate-400 w-10 text-right">Serviço</span>
          <div class="relative"><span class="absolute left-1.5 top-1/2 -translate-y-1/2 text-slate-400 text-[11px]">R$</span>
          <input type="number" step="0.01" min="0" class="input !min-h-[34px] !w-24 !pl-6 text-right text-sm" x-model.number="c.t" placeholder="0,00"></div></label>
        <label class="flex items-center gap-1 shrink-0" x-show="usaAlim"><span class="text-[11px] text-slate-400 w-10 text-right">Alim.</span>
          <div class="relative"><span class="absolute left-1.5 top-1/2 -translate-y-1/2 text-slate-400 text-[11px]">R$</span>
          <input type="number" step="0.01" min="0" class="input !min-h-[34px] !w-24 !pl-6 text-right text-sm" x-model.number="c.a" placeholder="0,00"></div></label>
      </div>
    </template>
  </div>
  <template x-for="(t,i) in trechos" :key="'h'+t.key">
    <span class="hidden">
      <input type="hidden" :name="`precos[${i}][origem]`" :value="t.o">
      <input type="hidden" :name="`precos[${i}][destino]`" :value="t.d">
      <input type="hidden" :name="`precos[${i}][valor]`" :value="(precos[t.key]||{}).p || 0">
      <input type="hidden" :name="`precos[${i}][taxa]`" :value="(precos[t.key]||{}).t || 0">
      <input type="hidden" :name="`precos[${i}][alim]`" :value="(precos[t.key]||{}).a || 0">
    </span>
  </template>
</div>

<div class="flex gap-2"><button class="btn btn-accent">{{ $edit ? 'Salvar alterações' : 'Criar rota' }}</button></div>
</form>

@once
@push('scripts')
<script>
function rotaForm(paradas, precos, flags){
  const p = (paradas && paradas.length) ? paradas : [{localidade:'',dia_semana:'',hora:''},{localidade:'',dia_semana:'',hora:''}];
  const norm = {};
  Object.keys(precos||{}).forEach(k=>{ const v=precos[k];
    norm[k] = (v && typeof v==='object') ? {p:Number(v.p)||0,t:Number(v.t)||0,a:Number(v.a)||0} : {p:Number(v)||0,t:0,a:0};
  });
  return {
    paradas: p.map(x=>({localidade:x.localidade||'', dia_semana:(x.dia_semana??'')+'', hora:x.hora||''})),
    precos: norm,
    usaTaxa: !!(flags&&flags.taxa), usaAlim: !!(flags&&flags.alim),
    ensure(key){ if(!this.precos[key]) this.precos[key]={p:0,t:0,a:0}; return this.precos[key]; },
    add(){ this.paradas.push({localidade:'',dia_semana:'',hora:''}); },
    get trechos(){
      const cidades = this.paradas.map(p=>(p.localidade||'').trim()).filter(Boolean);
      const vistos = new Set(); const out=[];
      for(let i=0;i<cidades.length;i++){ for(let j=0;j<cidades.length;j++){ if(i===j) continue;
        const o=cidades[i], d=cidades[j]; if(o.toLowerCase()===d.toLowerCase()) continue;
        const key=(o+'|'+d).toLowerCase(); if(vistos.has(key)) continue; vistos.add(key);
        out.push({o,d,key});
      }}
      return out;
    },
  };
}
</script>
@endpush
@endonce
