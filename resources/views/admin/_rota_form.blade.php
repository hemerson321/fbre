@php $edit = (bool) $rota; @endphp
<form method="post" action="{{ $edit ? route('admin.rotas.update',$rota) : route('admin.rotas.store') }}"
      x-data="rotaForm({{ $edit ? $rota->paradas->map(fn($p)=>['localidade'=>$p->localidade,'dia_semana'=>$p->dia_semana,'hora'=>$p->hora? substr($p->hora,0,5):''])->toJson() : '[]' }})" class="space-y-3">
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
<div class="flex gap-2"><button class="btn btn-accent">{{ $edit ? 'Salvar alterações' : 'Criar rota' }}</button></div>
</form>

@once
@push('scripts')
<script>
function rotaForm(paradas){
  const p = (paradas && paradas.length) ? paradas : [{localidade:'',dia_semana:'',hora:''},{localidade:'',dia_semana:'',hora:''}];
  return { paradas: p.map(x=>({localidade:x.localidade||'', dia_semana:(x.dia_semana??'')+'', hora:x.hora||''})),
           add(){ this.paradas.push({localidade:'',dia_semana:'',hora:''}); } }
}
</script>
@endpush
@endonce
