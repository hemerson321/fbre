{{-- Editor visual do mapa de poltronas (Transportes). Abre com o evento "editor-poltronas" {id,nome,layout}
     e devolve "editor-poltronas-salvo" {id,layout,qtd}. Grava no MESMO formato de texto do layout avançado
     (fileira por linha · "|" separa os grupos · linha em branco separa blocos). --}}
<style>
  .edp-seat{position:relative;flex:none;width:34px;height:37px;border-radius:10px 10px 5px 5px;padding:0;line-height:1;display:flex;align-items:center;justify-content:center;border:2px solid #cbd5e1;background:#f8fafc;color:#64748b;font-size:11px;font-weight:700;cursor:grab;user-select:none}
  .edp-seat::before{content:"";position:absolute;top:3px;left:4px;right:4px;height:6px;border-radius:5px;background:currentColor;opacity:.15;pointer-events:none}
  .edp-seat::after{content:"";position:absolute;bottom:4px;left:-3px;right:-3px;height:14px;border-left:4px solid currentColor;border-right:4px solid currentColor;border-radius:4px;opacity:.22;pointer-events:none}
  .edp-seat.larga{width:auto;padding:0 7px}
  .edp-seat.sel{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
  .edp-seat.dup{border-color:#dc2626;color:#dc2626}
  .edp-fila{display:grid;justify-content:center;column-gap:16px}
  .edp-zona{display:flex;gap:4px;white-space:nowrap;min-height:41px;padding:2px;border-radius:8px;outline:1px dashed transparent}
  .edp-canvas:hover .edp-zona,.edp-arrastando .edp-zona{outline-color:#cbd5e1}
  .edp-zona.alvo{outline:2px solid #1d4ed8;background:#dbeafe}
  .edp-zona.fim{justify-content:flex-end}
</style>
<div x-data="editorPoltronas()" x-show="aberto" x-cloak @editor-poltronas.window="abrir($event.detail)"
     @keydown.window="tecla($event)" @mousemove.window="mover($event)" @mouseup.window="soltar($event)"
     class="fixed inset-0 z-[60] bg-black/50 flex items-center justify-center p-3">
  <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[94vh] flex flex-col" @click.stop>
    <div class="flex items-center justify-between gap-2 px-4 py-3 border-b">
      <div class="font-bold">🎨 Editor do mapa de poltronas <span class="text-slate-400 font-normal" x-text="nome ? '· '+nome : ''"></span></div>
      <button type="button" class="text-xl px-2" @click="aberto=false">×</button>
    </div>
    <div class="px-4 py-2 border-b flex flex-wrap items-center gap-2 text-xs">
      <button type="button" class="btn btn-ghost btn-sm" @click="setas('ArrowLeft')" :disabled="!sel.length" title="Seta ←">⬅ Esquerda</button>
      <button type="button" class="btn btn-ghost btn-sm" @click="setas('ArrowRight')" :disabled="!sel.length" title="Seta →">Direita ➡</button>
      <button type="button" class="btn btn-ghost btn-sm" @click="setas('ArrowUp')" :disabled="!sel.length" title="Seta ↑">⬆ Sobe</button>
      <button type="button" class="btn btn-ghost btn-sm" @click="setas('ArrowDown')" :disabled="!sel.length" title="Seta ↓">⬇ Desce</button>
      <button type="button" class="btn btn-ghost btn-sm text-rose-600" @click="apagar()" :disabled="!sel.length" title="Delete">🗑 Remover</button>
      <button type="button" class="btn btn-ghost btn-sm" @click="desfazer()" :disabled="!hist.length" title="Ctrl+Z">↩ Desfazer</button>
      <span class="text-slate-300">|</span>
      <button type="button" class="btn btn-ghost btn-sm" @click="addGrupo()">＋ Coluna (corredor)</button>
      <button type="button" class="btn btn-ghost btn-sm" @click="addBloco()">＋ Bloco</button>
      <span class="ml-auto text-slate-500" x-text="total()+' poltronas · '+sel.length+' selecionada(s)'"></span>
    </div>
    <div class="px-4 py-2 border-b flex flex-wrap items-center gap-2 text-xs">
      <span class="text-slate-500">Nova(s):</span>
      <input x-model="novo" @keydown.enter.prevent="adicionar()" @keydown.stop class="input !min-h-[34px] !w-56 text-xs" style="font-family:ui-monospace,monospace" placeholder="27A 27B AREA-12">
      <button type="button" class="btn btn-ghost btn-sm" @click="adicionar()">Adicionar</button>
      <span class="text-slate-400" x-text="zona ? 'entra na fileira '+(zona.r+1)+' do bloco '+(zona.b+1) : 'clique numa fileira pra escolher onde entra'"></span>
    </div>
    <div class="px-4 py-1.5 text-[11px] text-slate-500 leading-snug border-b bg-slate-50">
      Clique seleciona · <b>Ctrl+clique</b> ou <b>arraste um retângulo</b> pega várias · <b>arraste</b> o grupo pra outra coluna/fileira (ou use as setas) · <b>duplo clique</b> renomeia.
    </div>
    <div class="edp-canvas flex-1 overflow-auto p-4 select-none" :class="drag && drag.ativo ? 'edp-arrastando' : ''" x-ref="canvas" @mousedown="iniciaFaixa($event)">
      <div class="mx-auto rounded-t-[44px] rounded-b-2xl border-2 border-slate-300 bg-slate-50/60 px-3 py-4" style="width:max-content;min-width:300px">
        <div class="text-center text-[11px] font-bold text-slate-400 mb-2">⬆ PROA (frente)</div>
        <template x-for="(bloco,bi) in blocos" :key="'b'+bi">
          <div :class="bi>0 ? 'mt-3 pt-3 border-t border-dashed border-slate-300' : ''">
            <template x-for="(fila,ri) in bloco" :key="'r'+bi+'-'+ri">
              <div class="flex items-center gap-1 mb-1">
                <div class="w-6 text-right text-[10px] text-slate-300" x-text="ri+1"></div>
                <div class="edp-fila flex-1" :style="grade()">
                  <template x-for="(grupo,gi) in fila" :key="'g'+gi">
                    <div class="edp-zona" :class="[(gi>0 && gi===nc-1) ? 'fim' : '', ehAlvo(bi,ri,gi) ? 'alvo' : '']" :data-zona="bi+','+ri+','+gi" @mousedown="zona={b:bi,r:ri,g:gi}">
                      <template x-for="n in grupo" :key="n">
                        <div>
                          <div x-show="editando!==n" class="edp-seat" :class="[sel.includes(n)?'sel':'', String(n).length>4?'larga':'', dups().includes(n)?'dup':'']" :data-pol="n"
                               @mousedown.stop.prevent="pegar($event,n,bi,ri,gi)" @dblclick.stop="renomear(n)" x-text="n"></div>
                          <input x-show="editando===n" x-model="editVal" :id="'edp-in-'+n" @keydown.stop @keydown.enter.prevent="confirmaNome()" @keydown.escape.prevent="editando=null" @blur="confirmaNome()" @mousedown.stop
                                 class="border-2 border-blue-600 rounded-md text-[11px] font-bold text-center" style="width:64px;height:37px;font-family:ui-monospace,monospace">
                        </div>
                      </template>
                    </div>
                  </template>
                </div>
                <button type="button" class="w-6 text-slate-300 hover:text-rose-600 text-sm" title="Remover fileira vazia" x-show="fila.every(g=>!g.length)" @mousedown.stop @click="remFila(bi,ri)">✕</button>
                <div class="w-6" x-show="!fila.every(g=>!g.length)"></div>
              </div>
            </template>
            <div class="text-center mt-1"><button type="button" class="text-[11px] text-slate-400 hover:text-blue-700" @mousedown.stop @click="addFila(bi)">＋ fileira</button></div>
          </div>
        </template>
        <div class="text-center text-[11px] font-bold text-slate-400 mt-2">POPA (fundo) ⬇</div>
      </div>
    </div>
    <div class="px-4 py-3 border-t flex items-center gap-2">
      <span class="text-xs text-rose-600 font-semibold" x-show="dups().length" x-text="'Nome repetido: '+dups().join(', ')"></span>
      <button type="button" class="btn btn-ghost btn-sm ml-auto" @click="aberto=false">Cancelar</button>
      <button type="button" class="btn btn-primary btn-sm" :disabled="dups().length>0" @click="salvar()">💾 Salvar mapa</button>
    </div>
  </div>
  <div x-show="faixa" class="fixed border-2 border-blue-600 bg-blue-500/10 pointer-events-none z-[70]" :style="faixa ? faixaCss() : ''"></div>
  <div x-show="drag && drag.ativo" class="fixed pointer-events-none z-[70] rounded-lg bg-blue-700 text-white text-xs font-bold px-2 py-1 shadow-lg" :style="drag ? ('left:'+(drag.x+12)+'px;top:'+(drag.y+12)+'px') : ''" x-text="'↔ '+sel.length+' poltrona(s)'"></div>
</div>
<script>
function editorPoltronas(){ return {
  aberto:false, id:null, nome:'', blocos:[], nc:3, sel:[], hist:[], zona:null, novo:'', drag:null, faixa:null, alvo:null, editando:null, editVal:'',
  abrir(d){ this.id=d.id; this.nome=d.nome||''; this.ler(String(d.layout||'')); this.sel=[]; this.hist=[]; this.zona=null; this.novo=''; this.editando=null; this.aberto=true; },
  ler(txt){
    let bl=txt.replace(/\r/g,'').split(/\n[ \t]*\n/).map(b=>b.split('\n').filter(l=>l.trim()).map(l=>l.split('|').map(x=>x.trim().split(/\s+/).filter(Boolean)))).filter(b=>b.length);
    if(!bl.length) bl=[[[[],[]]]];
    const nc=Math.max(1,...bl.flat().map(r=>r.length));
    bl.forEach(b=>b.forEach((r,i)=>{ if(r.length===2 && nc>=3){ const z=r[1]; r.length=1; while(r.length<nc-1) r.push([]); r.push(z); } while(r.length<nc) r.push([]); }));
    this.blocos=bl; this.nc=nc;
  },
  texto(){ return this.blocos.map(b=>b.filter(r=>r.some(g=>g.length)).map(r=>r.map(g=>g.join(' ')).join(' | ').replace(/ {2,}/g,' ').replace(/\s+$/,'')).join('\n')).filter(Boolean).join('\n\n'); },
  todas(){ return this.blocos.flat(3); },
  total(){ return this.todas().length; },
  dups(){ const v={}; this.todas().forEach(n=>v[n]=(v[n]||0)+1); return Object.keys(v).filter(n=>v[n]>1); },
  grade(){ const w=[]; for(let g=0;g<this.nc;g++){ let m=1; this.blocos.forEach(b=>b.forEach(r=>{ const c=r[g].filter(n=>String(n).length<=4).length; if(c>m) m=c; })); w.push((m*34+(m-1)*4+4)+'px'); } return 'grid-template-columns:'+w.join(' '); },
  foto(){ this.hist.push(JSON.stringify(this.blocos)); if(this.hist.length>60) this.hist.shift(); },
  desfazer(){ if(!this.hist.length) return; this.blocos=JSON.parse(this.hist.pop()); this.nc=this.blocos[0][0].length; this.sel=this.sel.filter(n=>this.todas().includes(n)); },
  achar(n){ for(let b=0;b<this.blocos.length;b++) for(let r=0;r<this.blocos[b].length;r++) for(let g=0;g<this.nc;g++){ const i=this.blocos[b][r][g].indexOf(n); if(i>=0) return {b,r,g,i}; } return null; },
  ordenadas(){ return this.sel.map(n=>({n,p:this.achar(n)})).filter(x=>x.p).sort((a,c)=>a.p.b-c.p.b||a.p.r-c.p.r||a.p.g-c.p.g||a.p.i-c.p.i); },
  tirar(nomes){ this.blocos.forEach(b=>b.forEach(r=>r.forEach((g,gi)=>{ r[gi]=g.filter(n=>!nomes.includes(n)); }))); },
  moverPara(b,r,g,antes){ const nomes=this.ordenadas().map(x=>x.n); if(!nomes.length) return; this.foto(); this.tirar(nomes); const z=this.blocos[b][r][g]; let i=(antes && !nomes.includes(antes)) ? z.indexOf(antes) : -1; if(i<0) i=z.length; z.splice(i,0,...nomes); this.zona={b,r,g}; },
  filas(){ const f=[]; this.blocos.forEach((b,bi)=>b.forEach((r,ri)=>f.push({b:bi,r:ri}))); return f; },
  setas(k){ const it=this.ordenadas(); if(!it.length) return; this.foto(); const fl=this.filas(); const dest=[];
    it.forEach(x=>{ let {b,r,g}=x.p; if(k==='ArrowLeft') g=Math.max(0,g-1); else if(k==='ArrowRight') g=Math.min(this.nc-1,g+1);
      else { let i=fl.findIndex(f=>f.b===b&&f.r===r)+(k==='ArrowUp'?-1:1); if(i<0) i=0; if(i>=fl.length){ const ub=this.blocos.length-1; this.blocos[ub].push(Array.from({length:this.nc},()=>[])); fl.push({b:ub,r:this.blocos[ub].length-1}); } b=fl[i].b; r=fl[i].r; }
      dest.push({n:x.n,b,r,g}); });
    this.tirar(it.map(x=>x.n)); dest.forEach(d=>this.blocos[d.b][d.r][d.g].push(d.n)); },
  apagar(){ if(!this.sel.length) return; this.foto(); this.tirar(this.sel); this.sel=[]; },
  adicionar(){ const nv=String(this.novo).toUpperCase().split(/[\s,|]+/).filter(Boolean).filter(n=>!this.todas().includes(n)); if(!nv.length) return; this.foto(); const z=this.zona||{b:this.blocos.length-1,r:this.blocos[this.blocos.length-1].length-1,g:0}; this.blocos[z.b][z.r][z.g].push(...nv); this.sel=nv; this.novo=''; },
  addFila(b){ this.foto(); this.blocos[b].push(Array.from({length:this.nc},()=>[])); this.zona={b,r:this.blocos[b].length-1,g:0}; },
  remFila(b,r){ this.foto(); this.blocos[b].splice(r,1); if(!this.blocos[b].length){ if(this.blocos.length>1) this.blocos.splice(b,1); else this.blocos[b].push(Array.from({length:this.nc},()=>[])); } this.zona=null; },
  addBloco(){ this.foto(); this.blocos.push([Array.from({length:this.nc},()=>[])]); this.zona={b:this.blocos.length-1,r:0,g:0}; },
  addGrupo(){ if(this.nc>=6) return; this.foto(); this.blocos.forEach(b=>b.forEach(r=>r.push([]))); this.nc++; },
  renomear(n){ this.editando=n; this.editVal=n; this.$nextTick(()=>{ const el=document.getElementById('edp-in-'+n); if(el){ el.focus(); el.select(); } }); },
  confirmaNome(){ const de=this.editando; if(de===null) return; this.editando=null; const para=String(this.editVal).toUpperCase().replace(/[\s|]+/g,''); if(!para || para===de || this.todas().includes(para)) return; const p=this.achar(de); if(!p) return; this.foto(); this.blocos[p.b][p.r][p.g].splice(p.i,1,para); this.sel=this.sel.map(x=>x===de?para:x); },
  ehAlvo(b,r,g){ return !!(this.alvo && this.alvo.b===b && this.alvo.r===r && this.alvo.g===g); },
  pegar(e,n,b,r,g){ this.zona={b,r,g}; const multi=e.ctrlKey||e.metaKey||e.shiftKey; let solto=null;
    if(multi){ if(this.sel.includes(n)) this.sel=this.sel.filter(x=>x!==n); else this.sel=[...this.sel,n]; }
    else if(!this.sel.includes(n)) this.sel=[n]; else solto=n;
    if(this.sel.includes(n)) this.drag={x0:e.clientX,y0:e.clientY,x:e.clientX,y:e.clientY,ativo:false,solto}; },
  iniciaFaixa(e){ if(e.button!==0 || e.target.closest('[data-pol]') || e.target.closest('button') || e.target.closest('input')) return; this.faixa={x0:e.clientX,y0:e.clientY,x1:e.clientX,y1:e.clientY,soma:(e.ctrlKey||e.metaKey||e.shiftKey)}; },
  faixaCss(){ const f=this.faixa; return 'left:'+Math.min(f.x0,f.x1)+'px;top:'+Math.min(f.y0,f.y1)+'px;width:'+Math.abs(f.x1-f.x0)+'px;height:'+Math.abs(f.y1-f.y0)+'px'; },
  zonaEm(x,y){ const el=document.elementFromPoint(x,y); if(!el) return null; const z=el.closest('[data-zona]'); if(!z) return null; const [b,r,g]=z.dataset.zona.split(',').map(Number); const s=el.closest('[data-pol]'); return {b,r,g,antes:s?s.dataset.pol:null}; },
  mover(e){ if(!this.aberto) return;
    if(this.drag){ this.drag.x=e.clientX; this.drag.y=e.clientY; if(!this.drag.ativo && Math.hypot(e.clientX-this.drag.x0,e.clientY-this.drag.y0)>5) this.drag.ativo=true; if(this.drag.ativo) this.alvo=this.zonaEm(e.clientX,e.clientY); }
    if(this.faixa){ this.faixa.x1=e.clientX; this.faixa.y1=e.clientY; } },
  soltar(e){ if(!this.aberto) return;
    if(this.drag){ const d=this.drag; this.drag=null; this.alvo=null; if(d.ativo){ const z=this.zonaEm(e.clientX,e.clientY); if(z) this.moverPara(z.b,z.r,z.g,z.antes); } else if(d.solto) this.sel=[d.solto]; }
    if(this.faixa){ const f=this.faixa; this.faixa=null; const L=Math.min(f.x0,f.x1),R=Math.max(f.x0,f.x1),T=Math.min(f.y0,f.y1),B=Math.max(f.y0,f.y1);
      if(R-L<4 && B-T<4){ if(!f.soma) this.sel=[]; return; }
      const pega=[]; this.$refs.canvas.querySelectorAll('[data-pol]').forEach(el=>{ const q=el.getBoundingClientRect(); if(q.width && q.left<R && q.right>L && q.top<B && q.bottom>T) pega.push(el.dataset.pol); });
      this.sel=f.soma ? [...new Set([...this.sel,...pega])] : pega; } },
  tecla(e){ if(!this.aberto || this.editando!==null) return; const t=e.target.tagName; if(t==='INPUT'||t==='TEXTAREA'||t==='SELECT') return;
    if(['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(e.key)){ if(this.sel.length){ e.preventDefault(); this.setas(e.key); } }
    else if(e.key==='Delete'){ e.preventDefault(); this.apagar(); }
    else if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='z'){ e.preventDefault(); this.desfazer(); }
    else if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='a'){ e.preventDefault(); this.sel=this.todas(); }
    else if(e.key==='Escape'){ if(this.sel.length) this.sel=[]; else this.aberto=false; } },
  salvar(){ if(this.dups().length) return; window.dispatchEvent(new CustomEvent('editor-poltronas-salvo',{detail:{id:this.id,layout:this.texto(),qtd:this.total()}})); this.aberto=false; }
}; }
</script>
