@php
  $cor1 = $empresa->cor_primaria ?? '#0b1a33';
  $cor2 = $empresa->cor_secundaria ?? '#00a8e8';
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Captura · {{ $empresa->nomeExibicao() }}</title>
<link rel="icon" href="{{ $empresa->logo ? '/'.$empresa->logo : '/img/icon.png' }}">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<style type="text/tailwindcss">
  :root { --cor1: {{ $cor1 }}; --cor2: {{ $cor2 }}; }
  body { background:#eef2f7; font-family:Inter,system-ui,sans-serif; }
  .field { @apply w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-base; min-height:50px; }
  .lbl { @apply block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1; }
  .card { @apply bg-white rounded-2xl shadow-sm border border-slate-100 p-4; }
  [x-cloak]{display:none!important}
</style>
</head>
<body class="min-h-screen">
<div class="max-w-md mx-auto p-4 space-y-3" x-data="captura()">
  <div class="text-center py-2">
    @if($empresa->logo)<img src="/{{ $empresa->logo }}" class="h-12 mx-auto mb-1 rounded-lg">@endif
    <div class="font-extrabold text-lg" style="color:var(--cor1)">{{ $empresa->nomeExibicao() }}</div>
    <div class="text-sm text-slate-500">Captura de passageiro por foto</div>
  </div>

  <div class="card space-y-3">
    <div x-show="enviados>0" x-cloak class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-bold text-center py-2">
      <span x-text="enviados"></span> passageiro(s) enviado(s) nesta sessão
    </div>
    <div class="grid grid-cols-2 gap-2">
      <div><label class="lbl">Origem</label>
        <select class="field" x-model="origem">
          <option value="">—</option>
          @foreach($cidades as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
        </select></div>
      <div><label class="lbl">Destino</label>
        <select class="field" x-model="destino">
          <option value="">—</option>
          @foreach($cidades as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
        </select></div>
    </div>

    <div>
      <label class="lbl">Fotos dos documentos</label>
      <input type="file" accept="image/*" capture="environment" class="hidden" x-ref="cam" @change="escolher($event)">
      <input type="file" accept="image/*" multiple class="hidden" x-ref="gal" @change="escolher($event)">
      <div class="grid grid-cols-2 gap-2">
        <button type="button" @click="$refs.cam.click()" class="rounded-xl text-white font-bold py-4 text-base" style="background:var(--cor1)">📷 Câmera</button>
        <button type="button" @click="$refs.gal.click()" class="rounded-xl font-bold py-4 text-base border-2" style="border-color:var(--cor1);color:var(--cor1)">🖼️ Galeria</button>
      </div>
      <template x-if="fila.length">
        <div class="mt-2 grid grid-cols-3 gap-2">
          <template x-for="(f,i) in fila" :key="f.id">
            <div class="relative">
              <img :src="f.src" class="w-full h-24 object-cover rounded-lg border">
              <span class="absolute bottom-1 left-1 bg-black/60 text-white text-xs rounded px-1" x-text="i+1"></span>
              <button type="button" @click="remover(i)" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 text-sm font-bold leading-none">×</button>
            </div>
          </template>
        </div>
      </template>
    </div>

    <button type="button" @click="enviar()" :disabled="!fila.length || enviando"
      class="w-full rounded-xl text-white font-extrabold py-4 text-lg disabled:opacity-40" style="background:var(--cor2)">
      <span x-text="enviando ? ('Enviando '+progresso+'...') : (fila.length>1 ? ('Enviar '+fila.length+' documentos pra venda') : 'Enviar pra venda')"></span>
    </button>

    <template x-if="msg">
      <div class="rounded-xl px-3 py-3 text-sm text-center" :class="msgOk ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200'" x-text="msg"></div>
    </template>
  </div>

  <p class="text-xs text-slate-400 text-center">As fotos vão pra tela de venda do operador (computador, celular ou maquineta) e os dados são preenchidos automaticamente. Dá pra tirar ou escolher vários documentos e enviar todos de uma vez.</p>
</div>

<script>
function captura(){
  return {
    e:@js($e), u:@js($u), s:@js($s),
    origem:'', destino:'', fila:[], enviando:false, msg:'', msgOk:false, enviados:0, progresso:'', _seq:0,
    init(){
      try{ const o=localStorage.getItem('fbre_cap_origem'); if(o) this.origem=o; const d=localStorage.getItem('fbre_cap_destino'); if(d) this.destino=d; }catch(e){}
      this.$watch('origem', v=>{ try{ localStorage.setItem('fbre_cap_origem', v||''); }catch(e){} });
      this.$watch('destino', v=>{ try{ localStorage.setItem('fbre_cap_destino', v||''); }catch(e){} });
    },
    escolher(ev){
      const files=Array.from(ev.target.files||[]);
      files.forEach(f=>{ const r=new FileReader(); r.onload=()=>{ this.redimensionar(r.result).then(d=>this.fila.push({id:(++this._seq),src:d})); }; r.readAsDataURL(f); });
      ev.target.value='';
    },
    remover(i){ this.fila.splice(i,1); },
    redimensionar(dataUrl){
      return new Promise(res=>{
        const img=new Image();
        img.onload=()=>{
          const max=1600; let w=img.width,h=img.height;
          if(w>max||h>max){ const k=Math.min(max/w,max/h); w=Math.round(w*k); h=Math.round(h*k); }
          const c=document.createElement('canvas'); c.width=w; c.height=h;
          c.getContext('2d').drawImage(img,0,0,w,h);
          res(c.toDataURL('image/jpeg',0.82));
        };
        img.onerror=()=>res(dataUrl);
        img.src=dataUrl;
      });
    },
    async enviar(){
      if(!this.fila.length||this.enviando) return;
      this.enviando=true; this.msg='';
      const total=this.fila.length; let ok=0, falhou=0;
      for(let i=0;i<total;i++){
        this.progresso=(i+1)+' de '+total;
        try{
          const r=await fetch(@js(route('captura.enviar')),{
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]')?.content||''},
            body:JSON.stringify({e:this.e,u:this.u,s:this.s,origem:this.origem,destino:this.destino,foto:this.fila[i].src})
          });
          const j=await r.json();
          if(j.ok){ ok++; this.enviados++; } else { falhou++; }
        }catch(e){ falhou++; }
      }
      this.fila=[];
      this.msgOk=(falhou===0);
      this.msg='✅ '+ok+' passageiro(s) enviado(s)'+(falhou?(' · '+falhou+' falhou'):'')+'. Pode enviar o próximo grupo.';
      this.enviando=false; this.progresso='';
    },
  };
}
</script>
<meta name="csrf-token" content="{{ csrf_token() }}">
</body>
</html>
