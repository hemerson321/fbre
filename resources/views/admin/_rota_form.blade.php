@php $edit = (bool) $rota; @endphp
<form method="post" action="{{ $edit ? route('admin.rotas.update',$rota) : route('admin.rotas.store') }}"
      x-data="rotaForm({{ $edit ? $rota->paradas->map(fn($p)=>['localidade'=>$p->localidade,'dia_semana'=>$p->dia_semana,'hora'=>$p->hora? substr($p->hora,0,5):''])->toJson() : '[]' }}, @js($trechoPrecosMapa ?? []), @js(($tiposPassageiro ?? collect())->map(fn($t)=>['id'=>$t->id,'nome'=>$t->nome,'cor'=>$t->cor ?: '#2563eb','modo'=>$t->modo ?: 'valor','percentual'=>(int)$t->percentual,'tarifa_padrao'=>(float)$t->tarifa_padrao,'alimentacao_padrao'=>(float)$t->alimentacao_padrao,'eh_normal'=>(bool)$t->eh_normal,'pct_sobre'=>$t->pct_sobre ?: 'tarifa'])->values()), @js(['alim'=>($usaAlimentacao ?? true)]))" class="space-y-3">
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
  <label class="label">Preços das passagens (por trecho e por tipo)</label>
  <p class="text-xs text-slate-400 mb-2">Tipos <b>Valor</b> têm Tarifa (e Alimentação) próprios. Tipos <b>Porcentagem</b> saem automáticos do <b>Normal</b>. Subida e descida podem ter preços diferentes.</p>
  {{-- legenda de cores --}}
  <div class="flex flex-wrap gap-x-3 gap-y-1 mb-3 text-xs">
    <template x-for="tp in tipos" :key="'lg'+tp.id">
      <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" :style="`background:${tp.cor}`"></span><span x-text="tp.nome"></span></span>
    </template>
  </div>
  {{-- busca + acoes --}}
  <div class="flex items-center gap-2 mb-2 flex-wrap">
    <input x-model="filtroTrecho" placeholder="🔎 Filtrar por cidade (origem ou destino)" class="input !min-h-[38px] flex-1 min-w-[180px]">
    <span class="text-xs text-slate-400 whitespace-nowrap" x-text="trechosFiltrados.length + ' de ' + trechos.length"></span>
    <button type="button" @click="expandirTodos()" class="btn btn-ghost btn-sm">Abrir todos</button>
    <button type="button" @click="abertos={}" class="btn btn-ghost btn-sm">Fechar</button>
  </div>
  <div class="space-y-1.5">
    <template x-for="t in trechosFiltrados" :key="t.key">
      <div class="rounded-xl border border-slate-200 overflow-hidden">
        <button type="button" @click="toggle(t.key)" class="w-full flex items-center gap-2 px-3 py-2.5 text-left hover:bg-slate-50">
          <span class="font-semibold text-sm flex-1 truncate" x-text="t.o + ' → ' + t.d"></span>
          <span class="text-xs whitespace-nowrap" :class="resumoTrecho(t)?'text-emerald-600 font-semibold':'text-slate-300'" x-text="resumoTrecho(t) || 'definir'"></span>
          <svg class="w-4 h-4 opacity-50 transition shrink-0" :class="aberto(t.key)?'rotate-180':''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <template x-if="aberto(t.key)">
          <div class="px-3 pb-3 pt-1 border-t border-slate-100 bg-slate-50/50">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
              <template x-for="tp in tipos" :key="t.key+'-'+tp.id">
                <div class="rounded-lg border p-2 bg-white" :style="`border-left:4px solid ${tp.cor}`">
                  <div class="text-[11px] font-bold uppercase leading-tight" :style="`color:${tp.cor}`"
                       x-text="tp.nome + ((tp.modo==='porcentagem' && !tp.eh_normal) ? ' ('+tp.percentual+'%)' : '')"></div>
                  <template x-if="tp.modo==='valor' || tp.eh_normal">
                    <div class="mt-1" x-data="{c: ensure(t.key, tp.id, tp)}">
                      <div class="grid grid-cols-2 gap-2">
                        <div><div class="text-[10px] text-slate-500 font-semibold">Integral</div>
                          <input class="input !min-h-[32px] w-full text-right text-sm" x-money="c.integral"></div>
                        <div><div class="text-[10px] text-slate-500 font-semibold">Com desconto</div>
                          <input type="text" inputmode="numeric" class="input !min-h-[32px] w-full text-right text-sm font-semibold" :value="FBRE.fmtMoney(comDesc(c))" @input="setComDesc(c, FBRE.parseMoney($event.target.value)); $event.target.value=FBRE.fmtMoney(comDesc(c))"></div>
                        <div><div class="text-[10px] text-slate-500 font-semibold">Tarifa de Transporte</div>
                          <input class="input !min-h-[32px] w-full text-right text-sm" x-money="c.tarifa"></div>
                        <div x-show="usaAlim"><div class="text-[10px] text-slate-500 font-semibold">Alimentação + Adicional</div>
                          <input class="input !min-h-[32px] w-full text-right text-sm" x-money="c.alim"></div>
                      </div>
                      <div class="text-[11px] mt-1 leading-tight"><span class="text-slate-500">Paga</span> <b :style="`color:${tp.cor}`" x-text="moeda(comDesc(c))"></b> <span class="text-slate-400" x-show="Number(c.integral)>comDesc(c)+0.009">(normal <span x-text="moeda(Number(c.integral)||0)"></span>)</span></div>
                    </div>
                  </template>
                  <template x-if="tp.modo==='porcentagem' && !tp.eh_normal">
                    <div class="mt-1 leading-tight" x-data="{c: ensure(t.key, tp.id, tp)}">
                      {{-- AUTO: calculado a partir do Normal, se ajusta sozinho --}}
                      <template x-if="!c.manual">
                        <div>
                          <div class="text-[11px] text-slate-400">Preço normal (integral): <b class="text-slate-500" x-text="moeda(normalIntegral(t.key))"></b></div>
                          <div class="text-sm font-bold" :style="`color:${tp.cor}`"><span class="text-[11px] font-normal text-slate-500">Valor com desconto</span> <span x-text="'= '+moeda(calcPct(t.key, tp))"></span></div>
                          <div class="text-[11px]"><span class="text-slate-500">Transporte</span> <b x-text="moeda(calcPctTarifa(t.key, tp))"></b><template x-if="usaAlim"><span> · <span class="text-slate-500">Alim.+Adic.</span> <b x-text="moeda(calcPctAlim(t.key, tp))"></b></span></template></div>
                          <button type="button" @click="ajustarManual(t.key, tp)" class="text-[10px] text-sky-600 underline mt-0.5">✎ dividir manual</button>
                        </div>
                      </template>
                      {{-- MANUAL: valores digitados, fixos --}}
                      <template x-if="c.manual">
                        <div>
                      <div class="grid grid-cols-2 gap-2">
                        <div><div class="text-[10px] text-slate-500 font-semibold">Integral</div>
                          <input class="input !min-h-[32px] w-full text-right text-sm" x-money="c.integral"></div>
                        <div><div class="text-[10px] text-slate-500 font-semibold">Com desconto</div>
                          <input type="text" inputmode="numeric" class="input !min-h-[32px] w-full text-right text-sm font-semibold" :value="FBRE.fmtMoney(comDesc(c))" @input="setComDesc(c, FBRE.parseMoney($event.target.value)); $event.target.value=FBRE.fmtMoney(comDesc(c))"></div>
                        <div><div class="text-[10px] text-slate-500 font-semibold">Tarifa de Transporte</div>
                          <input class="input !min-h-[32px] w-full text-right text-sm" x-money="c.tarifa"></div>
                        <div x-show="usaAlim"><div class="text-[10px] text-slate-500 font-semibold">Alimentação + Adicional</div>
                          <input class="input !min-h-[32px] w-full text-right text-sm" x-money="c.alim"></div>
                      </div>
                          <div class="text-[11px] mt-1"><span class="text-slate-500">Paga</span> <b :style="`color:${tp.cor}`" x-text="moeda(comDesc(c))"></b> <span class="text-slate-400" x-show="Number(c.integral)>comDesc(c)+0.009">(normal <span x-text="moeda(Number(c.integral)||0)"></span>)</span>
                            <button type="button" @click="voltarAuto(t.key, tp)" class="text-[10px] text-slate-500 underline ml-2">↻ voltar ao automático</button></div>
                        </div>
                      </template>
                    </div>
                  </template>
                </div>
              </template>
            </div>
          </div>
        </template>
      </div>
    </template>
    <div x-show="!trechosFiltrados.length" class="text-sm text-slate-400 text-center py-4">Nenhum trecho encontrado.</div>
  </div>
  {{-- hidden: uma linha por (trecho x tipo Valor) --}}
  <template x-for="(r,i) in submitRows" :key="'h'+i">
    <span class="hidden">
      <input type="hidden" :name="`precos[${i}][origem]`" :value="r.origem">
      <input type="hidden" :name="`precos[${i}][destino]`" :value="r.destino">
      <input type="hidden" :name="`precos[${i}][tipo_id]`" :value="r.tipo_id">
      <input type="hidden" :name="`precos[${i}][tarifa]`" :value="r.tarifa">
      <input type="hidden" :name="`precos[${i}][alim]`" :value="r.alim">
    </span>
  </template>
</div>

<div class="flex gap-2"><button class="btn btn-accent">{{ $edit ? 'Salvar alterações' : 'Criar rota' }}</button></div>
</form>

@once
@push('scripts')
<script>
function rotaForm(paradas, precos, tipos, flags){
  const p = (paradas && paradas.length) ? paradas : [{localidade:'',dia_semana:'',hora:''},{localidade:'',dia_semana:'',hora:''}];
  return {
    paradas: p.map(x=>({localidade:x.localidade||'', dia_semana:(x.dia_semana??'')+'', hora:x.hora||''})),
    precos: Object.assign({}, precos||{}),
    tipos: (tipos||[]).map(t=>({...t})),
    usaAlim: !!(flags&&flags.alim),
    filtroTrecho: '', abertos: {},
    aberto(k){ return !!this.abertos[k]; },
    toggle(k){ this.abertos = Object.assign({}, this.abertos, {[k]: !this.abertos[k]}); },
    expandirTodos(){ const a={}; this.trechosFiltrados.forEach(t=>a[t.key]=true); this.abertos=a; },
    get trechosFiltrados(){ const f=(this.filtroTrecho||'').trim().toLowerCase(); if(!f) return this.trechos;
      return this.trechos.filter(t=> (t.o+' '+t.d).toLowerCase().includes(f)); },
    resumoTrecho(t){ const n=this.normal; if(!n) return ''; const c=this.precos[this.cellKey(t.key,n.id)];
      const v=c?Number(c.tarifa)||0:0; return v>0 ? (n.nome+' '+this.moeda(v)) : ''; },
    add(){ this.paradas.push({localidade:'',dia_semana:'',hora:''}); },
    moeda(v){ return (Number(v)||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'}); },
    get normal(){ return this.tipos.find(t=>t.eh_normal) || this.tipos[0] || null; },
    get valorTipos(){ return this.tipos.filter(t=>t.modo==='valor'||t.eh_normal); },
    cellKey(trechoKey,tipoId){ return trechoKey+'|'+tipoId; },
    ehPct(tipo){ return tipo && (tipo.modo||'valor')==='porcentagem' && !tipo.eh_normal; },
    ensure(trechoKey,tipoId,tipo){ const k=this.cellKey(trechoKey,tipoId);
      if(!this.precos[k]){
        this.precos[k] = this.ehPct(tipo)
          ? {tarifa:0, alim:0, manual:false}
          : {tarifa: tipo?Number(tipo.tarifa_padrao)||0:0, alim: tipo?Number(tipo.alimentacao_padrao)||0:0, manual:true};
      } else if(this.precos[k].manual===undefined){
        this.precos[k].manual = true; // veio do banco = override salvo
      }
      const c=this.precos[k];
      if(c.integral===undefined) c.integral = (Number(c.tarifa)||0)+(Number(c.alim)||0);
      return c; },
    comDesc(c){ return Math.round(((Number(c.tarifa)||0)+(this.usaAlim?(Number(c.alim)||0):0))*100)/100; },
    setComDesc(c,v){ const total=Math.max(Number(v)||0,0); c.tarifa=Math.round(Math.max(total-(this.usaAlim?(Number(c.alim)||0):0),0)*100)/100; },
    ajustarManual(trechoKey,tp){ const c=this.ensure(trechoKey,tp.id,tp); c.tarifa=this.calcPctTarifa(trechoKey,tp); c.alim=this.calcPctAlim(trechoKey,tp); c.integral=Math.round(this.normalIntegral(trechoKey)*100)/100; c.manual=true; },
    voltarAuto(trechoKey,tp){ const c=this.ensure(trechoKey,tp.id,tp); c.manual=false; },
    calcPctTarifa(trechoKey,tp){ const n=this.normal; if(!n) return 0;
      const nc=this.precos[this.cellKey(trechoKey,n.id)];
      const baseT = nc?Number(nc.tarifa)||0:Number(n.tarifa_padrao)||0;
      return baseT*(Number(tp.percentual)||0)/100; },
    calcPctAlim(trechoKey,tp){ const n=this.normal; if(!n || !this.usaAlim) return 0;
      const nc=this.precos[this.cellKey(trechoKey,n.id)];
      const baseA = nc?Number(nc.alim)||0:Number(n.alimentacao_padrao)||0;
      return (tp.pct_sobre==='tudo') ? baseA*(Number(tp.percentual)||0)/100 : baseA; },
    calcPct(trechoKey,tp){ return this.calcPctTarifa(trechoKey,tp) + this.calcPctAlim(trechoKey,tp); },
    normalIntegral(trechoKey){ const n=this.normal; if(!n) return 0; const nc=this.precos[this.cellKey(trechoKey,n.id)];
      const t=nc?Number(nc.tarifa)||0:Number(n.tarifa_padrao)||0; const a=this.usaAlim?(nc?Number(nc.alim)||0:Number(n.alimentacao_padrao)||0):0; return t+a; },
    get submitRows(){ const rows=[];
      this.trechos.forEach(t=>{ this.tipos.forEach(tp=>{
        const c=this.precos[this.cellKey(t.key,tp.id)];
        if(!c) return;
        if(this.ehPct(tp) && !c.manual) return; // porcentagem automatica nao grava linha
        const tarifa=Number(c.tarifa)||0; const alim=this.usaAlim?Number(c.alim)||0:0;
        const integral=Math.round(Math.max(Number(c.integral)||0, tarifa+alim)*100)/100;
        if(tarifa>0||alim>0||integral>0) rows.push({origem:t.o,destino:t.d,tipo_id:tp.id,tarifa,alim,integral});
      }); });
      return rows; },
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
