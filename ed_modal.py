f='resources/views/passagens/index.blade.php'
s=open(f,encoding='utf-8').read()
if 'edModal' in s and 'id="ed-modal-full"' in s:
    print("JA TEM MODAL"); raise SystemExit(0)
anchor="    <script>\n    function editarMassa(){"
assert anchor in s, "anchor script nao encontrado"
modal=r'''    <div id="ed-modal-full" x-show="edModal" x-cloak style="position:fixed;inset:0;z-index:70;background:rgba(0,0,0,.55);overflow-y:auto" class="p-3" @click.self="edModal=false">
      <div class="bg-white rounded-2xl w-full max-w-2xl mx-auto my-4 shadow-2xl">
        <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b">
          <div class="font-bold text-lg">✏️ Editar passagem <span class="text-slate-400" x-text="ed.numero"></span></div>
          <button type="button" @click="edModal=false" class="text-slate-400 text-3xl leading-none hover:text-slate-600">&times;</button>
        </div>
        <div x-show="edLoading" class="py-12 text-center text-slate-400">Carregando…</div>
        <div x-show="!edLoading" class="px-5 py-4 space-y-4">
          <div>
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Viagem</div>
            <div class="grid grid-cols-2 gap-2">
              <div><label class="text-xs font-semibold text-slate-600">Transporte</label>
                <select x-model="ed.embarcacao_id" class="input w-full"><option value="">— nenhum —</option>@foreach($embarcacoes as $e)<option value="{{ $e->id }}">🚤 {{ $e->nome }}</option>@endforeach</select></div>
              <div><label class="text-xs font-semibold text-slate-600">Data da viagem</label>
                <input type="date" x-model="ed.data_viagem" class="input w-full"></div>
              <div><label class="text-xs font-semibold text-slate-600">Origem</label>
                <input type="text" x-model="ed.origem" class="input w-full"></div>
              <div><label class="text-xs font-semibold text-slate-600">Destino</label>
                <input type="text" x-model="ed.destino" class="input w-full"></div>
              <div class="col-span-2"><label class="text-xs font-semibold text-slate-600">Forma de pagamento</label>
                <select x-model="ed.forma" class="input w-full">@foreach(\App\Models\Passagem::FORMAS as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            </div>
          </div>
          <div>
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Comprador</div>
            <div class="grid grid-cols-3 gap-2">
              <div class="col-span-3 sm:col-span-1"><label class="text-xs font-semibold text-slate-600">Nome</label><input type="text" x-model="ed.comprador_nome" class="input w-full"></div>
              <div><label class="text-xs font-semibold text-slate-600">Telefone</label><input type="text" x-model="ed.comprador_telefone" class="input w-full"></div>
              <div><label class="text-xs font-semibold text-slate-600">Documento</label><input type="text" x-model="ed.comprador_documento" class="input w-full"></div>
            </div>
          </div>
          <div>
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Passageiros (<span x-text="ed.passageiros.length"></span>)</div>
            <div class="space-y-2">
              <template x-for="(px,i) in ed.passageiros" :key="px.id">
                <div class="border border-slate-200 rounded-xl p-3 space-y-2">
                  <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500" x-text="(i+1)+'. '+px.tipo + (px.papel==='colo'?' · bebê de colo':'')"></span>
                    <span class="text-[11px] text-slate-400" x-show="px.poltrona" x-text="'Poltrona '+px.poltrona"></span>
                  </div>
                  <input type="text" x-model="px.nome" placeholder="Nome do passageiro" class="input w-full">
                  <div class="grid grid-cols-2 gap-2">
                    <input type="text" x-model="px.documento" placeholder="Documento" class="input w-full">
                    <input type="text" x-model="px.poltrona" placeholder="Poltrona" class="input w-full">
                    <input type="date" x-model="px.nascimento" class="input w-full">
                    <select x-model="px.sexo" class="input w-full"><option value="">Sexo</option><option value="M">Masculino</option><option value="F">Feminino</option></select>
                    <input type="text" x-model="px.telefone" placeholder="Telefone" class="input w-full col-span-2">
                  </div>
                  <div class="grid grid-cols-3 gap-2" x-show="px.papel!=='colo'">
                    <div><label class="text-[11px] font-semibold text-slate-500">Tarifa</label><input type="number" step="0.01" min="0" x-model.number="px.valor_passagem" class="input w-full"></div>
                    <div><label class="text-[11px] font-semibold text-slate-500">Taxa</label><input type="number" step="0.01" min="0" x-model.number="px.valor_taxa" class="input w-full"></div>
                    <div><label class="text-[11px] font-semibold text-slate-500">Alimentação</label><input type="number" step="0.01" min="0" x-model.number="px.valor_alimentacao" class="input w-full"></div>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
        <div x-show="!edLoading" class="flex justify-end gap-2 px-5 py-4 border-t sticky bottom-0 bg-white rounded-b-2xl">
          <button type="button" @click="edModal=false" class="rounded-lg px-4 py-2 text-sm font-semibold border">Cancelar</button>
          <button type="button" @click="salvarUm()" :disabled="edSalvando" class="rounded-lg px-5 py-2 text-sm font-bold bg-slate-900 text-white disabled:opacity-50"><span x-show="!edSalvando">Salvar tudo</span><span x-show="edSalvando">Salvando…</span></button>
        </div>
      </div>
    </div>
'''
s=s.replace(anchor, modal+anchor, 1)
open(f,'w',encoding='utf-8').write(s)
print("MODAL INSERIDO")
