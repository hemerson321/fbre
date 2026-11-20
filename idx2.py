f='resources/views/passagens/index.blade.php'
s=open(f,encoding='utf-8').read()
orig=s

# 1) abrir() inteligente: 1 selecionada -> modal completo
a1="abrir(){ if(!this.sel.length) return; this.f={embarcacao_id:'',data_viagem:'',origem:'',destino:'',forma:''}; this.modal=true; },"
b1="abrir(){ if(!this.sel.length) return; if(this.sel.length===1){ this.editarUm(this.sel[0]); return; } this.f={embarcacao_id:'',data_viagem:'',origem:'',destino:'',forma:''}; this.modal=true; },"
if a1 in s and 'if(this.sel.length===1)' not in s: s=s.replace(a1,b1,1)

# 2) lista de tipos no estado Alpine
a2="        ed:{numero:'',embarcacao_id:'',data_viagem:'',origem:'',destino:'',forma:'avista',comprador_nome:'',comprador_telefone:'',comprador_documento:'',observacao:'',passageiros:[]},"
b2=a2+"\n        tiposPax: @json(($tiposPax ?? collect())->pluck('nome')->values()),"
if 'tiposPax:' not in s: s=s.replace(a2,b2,1)

# 3) cabecalho do passageiro: numero + select de tipo
a3='''                  <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500" x-text="(i+1)+'. '+px.tipo + (px.papel==='colo'?' · bebê de colo':'')"></span>
                    <span class="text-[11px] text-slate-400" x-show="px.poltrona" x-text="'Poltrona '+px.poltrona"></span>
                  </div>'''
b3='''                  <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                      <span class="text-xs font-bold text-slate-400" x-text="(i+1)+'.'"></span>
                      <select x-show="px.papel!=='colo'" x-model="px.tipo" class="input !min-h-0 h-8 py-0 text-xs font-semibold" style="width:auto">
                        <template x-for="t in (px.tipo && !tiposPax.includes(px.tipo) ? [px.tipo].concat(tiposPax) : tiposPax)" :key="t"><option :value="t" x-text="t"></option></template>
                      </select>
                      <span x-show="px.papel==='colo'" class="text-xs font-bold text-slate-500">Bebê de colo</span>
                    </div>
                    <span class="text-[11px] text-slate-400 shrink-0" x-show="px.poltrona" x-text="'Poltrona '+px.poltrona"></span>
                  </div>'''
if a3 in s: s=s.replace(a3,b3,1)

open(f,'w',encoding='utf-8').write(s)
print("abrir:", 'if(this.sel.length===1)' in s, "| tiposPax:", 'tiposPax:' in s, "| select:", 'tiposPax.includes(px.tipo)' in s, "| changed:", s!=orig)
