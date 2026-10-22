@php $edit = (bool) $rota; @endphp
<form method="post" action="{{ $edit ? route('admin.rotas.update',$rota) : route('admin.rotas.store') }}"
      x-data="rotaForm({{ $edit ? $rota->paradas->map(fn($p)=>['localidade'=>$p->localidade,'dia_semana'=>$p->dia_semana,'hora'=>$p->hora? substr($p->hora,0,5):''])->toJson() : '[]' }}, @js($precosMapa ?? []))" class="space-y-3">
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
  <p class="text-xs text-slate-400 mb-2">Digite o valor de cada origem → destino. O mesmo valor vale no sentido inverso. Trechos atualizam sozinhos conforme as cidades acima.</p>
  <div class="grid sm:grid-cols-2 gap-x-4 gap-y-1.5">
    <template x-for="t in trechos" :key="t.key">
      <div class="flex items-center gap-2">
        <span class="text-sm flex-1 truncate" x-text="t.o + ' → ' + t.d"></span>
        <div class="relative shrink-0"><span class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 text-xs">R$</span>
          <input type="number" step="0.01" min="0" class="input !min-h-[36px] !w-28 !pl-7 text-right" x-model.number="precos[t.key]" placeholder="0,00"></div>
      </div>
    </template>
  </div>
  <template x-for="(t,i) in trechos" :key="'h'+t.key">
    <span class="hidden">
      <input type="hidden" :name="`precos[${i}][origem]`" :value="t.o">
      <input type="hidden" :name="`precos[${i}][destino]`" :value="t.d">
      <input type="hidden" :name="`precos[${i}][valor]`" :value="precos[t.key] || 0">
    </span>
  </template>
</div>

<div class="flex gap-2"><button class="btn btn-accent">{{ $edit ? 'Salvar alterações' : 'Criar rota' }}</button></div>
</form>

@once
@push('scripts')
<script>
function rotaForm(paradas, precos){
  const p = (paradas && paradas.length) ? paradas : [{localidade:'',dia_semana:'',hora:''},{localidade:'',dia_semana:'',hora:''}];
  return {
    paradas: p.map(x=>({localidade:x.localidade||'', dia_semana:(x.dia_semana??'')+'', hora:x.hora||''})),
    precos: Object.assign({}, precos||{}),
    add(){ this.paradas.push({localidade:'',dia_semana:'',hora:''}); },
    get trechos(){
      const cidades = this.paradas.map(p=>(p.localidade||'').trim()).filter(Boolean);
      const vistos = new Set(); const out=[];
      for(let i=0;i<cidades.length;i++){ for(let j=i+1;j<cidades.length;j++){
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
