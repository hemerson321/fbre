{{-- Mapa de poltronas clicavel p/ transferencia/troca. Usado dentro do escopo transfCal/trocaCal. --}}
<div x-show="mapaAberto" x-cloak class="fixed inset-0 z-[60] bg-black/50 flex items-end sm:items-center justify-center p-3" @click="mapaAberto=false">
  <div class="bg-white rounded-2xl p-4 w-full max-w-md max-h-[85vh] overflow-auto" @click.stop>
    <div class="flex items-center justify-between mb-2">
      <div class="font-bold">🪑 Escolha a poltrona</div>
      <button type="button" class="text-xl px-2" @click="mapaAberto=false">&times;</button>
    </div>
    <div class="flex flex-wrap gap-2 text-xs mb-3">
      <span class="chip bg-slate-100 text-slate-600">livre</span>
      <span class="chip bg-red-100 text-red-700">ocupada</span>
      <span class="chip text-white" style="background:var(--cor1)">selecionada</span>
      <span class="chip bg-emerald-100 text-emerald-700 font-bold" x-text="'livres: '+contLivres()"></span>
    </div>
    <template x-if="temLayout">
      <div class="mx-auto" style="max-width:360px">
        <div class="text-center text-[11px] font-bold text-slate-400 mb-1">&uarr; PROA (frente)</div>
        <div class="rounded-t-[44px] rounded-b-2xl border-2 px-2 py-3" style="border-color:var(--cor1);background:linear-gradient(180deg,color-mix(in srgb,var(--cor1) 10%,white),#eef2f7)">
          <template x-for="(bloco,bi) in blocos()" :key="bi">
            <div :class="bi>0 ? 'mt-2.5 pt-2.5 border-t border-dashed border-slate-300 space-y-1.5' : 'space-y-1.5'">
              <template x-for="(f,ri) in bloco" :key="ri">
                <div class="flex items-center justify-center gap-1">
                  <template x-for="(col,ci) in (f.cols||[f.esq,f.dir])" :key="ci">
                    <div class="flex items-center gap-1">
                      <div class="w-3 text-center text-[9px] text-slate-300 select-none" x-show="ci>0 && col.length">&#9553;</div>
                      <div class="flex gap-1"><template x-for="n in col" :key="n">
                        <button type="button" class="min-w-[36px] h-9 px-0.5 rounded-t-lg rounded-b-md border-2 text-[11px] font-bold leading-none transition" :class="classeAssento(n)" :style="selPol(n)?'background:var(--cor1);border-color:var(--cor1)':''" :disabled="(estaOcupada(n)||minhaPol(n)) && !selPol(n)" @click="escolherPol(n)" x-text="n"></button>
                      </template></div>
                    </div>
                  </template>
                </div>
              </template>
            </div>
          </template>
        </div>
        <div class="text-center text-[11px] font-bold text-slate-400 mt-1">POPA (fundo) &darr;</div>
      </div>
    </template>
    <div x-show="!temLayout" class="mx-auto" style="max-width:260px">
      <div class="text-center text-[11px] font-bold text-slate-400 mb-1">&uarr; PROA (frente)</div>
      <div class="rounded-t-[36px] rounded-b-2xl border-2 border-slate-300 bg-slate-50 px-2 py-3 space-y-1.5">
        <template x-for="(f,ri) in fileiras()" :key="ri">
          <div class="flex items-center justify-center gap-1">
            <div class="flex gap-1.5"><template x-for="n in f.esq" :key="n">
              <button type="button" class="w-9 h-10 rounded-t-xl rounded-b-md border-2 text-xs font-bold transition" :class="classeAssento(n)" :style="selPol(n)?'background:var(--cor1);border-color:var(--cor1)':''" :disabled="(estaOcupada(n)||minhaPol(n)) && !selPol(n)" @click="escolherPol(n)" x-text="n"></button>
            </template></div>
            <div class="w-6 text-center text-[9px] text-slate-300 select-none">&#9553;</div>
            <div class="flex gap-1.5"><template x-for="n in f.dir" :key="n">
              <button type="button" class="w-9 h-10 rounded-t-xl rounded-b-md border-2 text-xs font-bold transition" :class="classeAssento(n)" :style="selPol(n)?'background:var(--cor1);border-color:var(--cor1)':''" :disabled="(estaOcupada(n)||minhaPol(n)) && !selPol(n)" @click="escolherPol(n)" x-text="n"></button>
            </template></div>
          </div>
        </template>
      </div>
      <div class="text-center text-[11px] font-bold text-slate-400 mt-1">POPA (fundo) &darr;</div>
    </div>
  </div>
</div>
