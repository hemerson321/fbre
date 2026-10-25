@extends('layouts.app')
@section('titulo','Demo do agente')
@section('conteudo')
<div class="max-w-2xl mx-auto" x-data="demoChat()" x-init="init()">

  <div class="flex items-center justify-between mb-3">
    <div>
      <a href="{{ route('admin.agentes') }}" class="text-xs text-slate-400 hover:underline">← Agentes</a>
      <h1 class="text-lg font-bold">🧪 Demo · {{ $agente->nome }}</h1>
      <p class="text-xs text-slate-500">
        {{ \App\Support\Modulos::rotulo($agente->modulo) }} ·
        {{ $agente->llm?->nome ?? 'sem IA' }} ·
        @if($agente->pagamento_api_id) PIX: {{ $agente->pagamentoApi?->nome }} @else sem pagamento @endif
      </p>
    </div>
    <span class="text-[11px] px-2 py-1 rounded-full bg-slate-100 text-slate-600" x-text="'Funil: '+funilLabel(funil)"></span>
  </div>

  <div class="card overflow-hidden">
    <!-- aviso -->
    <div class="bg-amber-50 border-b border-amber-100 text-amber-800 text-[11px] px-3 py-2">
      Conversa de teste — não usa seu WhatsApp e não gera cobrança real. O PIX é fictício; use <b>Simular pagamento</b> pra testar a confirmação.
    </div>

    <!-- mensagens -->
    <div class="p-3 space-y-2 overflow-y-auto" style="height:58vh;background:#e9ecef" x-ref="scroll">
      <template x-for="m in mensagens" :key="m.id">
        <div>
          <!-- sistema (centralizado) -->
          <template x-if="m.origem==='sistema'">
            <div class="text-center">
              <span class="inline-block bg-white/80 text-slate-600 text-[12px] px-3 py-1.5 rounded-lg whitespace-pre-line shadow-sm" x-text="m.texto"></span>
            </div>
          </template>
          <!-- pix (copia e cola) -->
          <template x-if="m.pix">
            <div class="flex justify-start">
              <div class="max-w-[85%] bg-white rounded-xl rounded-tl-sm shadow-sm px-3 py-2">
                <div class="text-[10px] text-slate-400 mb-1">PIX copia e cola</div>
                <div class="font-mono text-[11px] break-all text-slate-700" x-text="m.texto"></div>
                <button class="btn btn-sm btn-ghost mt-2" @click="copiar(m.texto,$event)">📋 Copiar</button>
              </div>
            </div>
          </template>
          <!-- normal -->
          <template x-if="m.origem!=='sistema' && !m.pix">
            <div class="flex" :class="m.mine?'justify-end':'justify-start'">
              <div class="max-w-[85%] px-3 py-2 rounded-xl shadow-sm whitespace-pre-line text-[14px] leading-snug"
                   :class="m.mine?'bg-[#d9fdd3] rounded-tr-sm':'bg-white rounded-tl-sm'">
                <span x-text="m.texto"></span>
                <span class="block text-[10px] text-slate-400 text-right mt-0.5" x-text="m.hora"></span>
              </div>
            </div>
          </template>
        </div>
      </template>

      <!-- digitando -->
      <div x-show="pensando" class="flex justify-start">
        <div class="bg-white rounded-xl rounded-tl-sm shadow-sm px-3 py-2 text-slate-400 text-sm">digitando…</div>
      </div>

      <div x-show="!mensagens.length && !pensando" class="text-center text-slate-400 text-sm mt-10">
        Escreva como se fosse o cliente. Ex.: <i>"oi, quero uma passagem"</i>
      </div>
    </div>

    <!-- barra de ações -->
    <div class="border-t bg-white">
      <div class="flex items-center gap-2 px-3 pt-2 pb-1 flex-wrap">
        <button class="btn btn-sm btn-accent" @click="pagar()" :disabled="ocupado">💰 Simular pagamento</button>
        <label class="btn btn-sm btn-ghost cursor-pointer">
          📷 Enviar foto do documento
          <input type="file" accept="image/*" class="hidden" @change="enviarFoto($event)">
        </label>
        <button class="btn btn-sm btn-ghost ml-auto" @click="reiniciar()" :disabled="ocupado">🔄 Reiniciar</button>
      </div>
      <form @submit.prevent="enviar()" class="flex items-end gap-2 p-3 pt-1">
        <textarea x-model="texto" rows="1" x-ref="inp" @keydown.enter.prevent="enviar()"
          class="input flex-1 py-3 resize-none" style="min-height:48px" placeholder="Mensagem do cliente…"></textarea>
        <button type="submit" class="btn btn-primary" :disabled="ocupado || !texto.trim()">Enviar</button>
      </form>
    </div>
  </div>
</div>

<script>
function demoChat(){
  return {
    mensagens: @json($mensagens),
    funil: @json($conversa->funil),
    texto: '', pensando:false, ocupado:false,
    csrf: document.querySelector('meta[name=csrf-token]').content,
    base: '{{ route('admin.agentes.demo', $agente) }}',
    init(){ this.$nextTick(()=>this.scrollFim()); },
    funilLabel(f){ return ({novo:'Novo',cotacao:'Cotação',aguardando_pagamento:'Aguardando pagamento',pago:'Pago',finalizado:'Finalizado',perdido:'Perdido'}[f]||f||'—'); },
    scrollFim(){ this.$nextTick(()=>{ const s=this.$refs.scroll; if(s) s.scrollTop=s.scrollHeight; }); },
    aplicar(d){
      if(d.funil) this.funil=d.funil;
      (d.mensagens||[]).forEach(m=>{ if(!this.mensagens.find(x=>x.id===m.id)) this.mensagens.push(m); });
      this.scrollFim();
    },
    async post(url, body, isForm){
      const opt={ method:'POST', headers:{'X-CSRF-TOKEN':this.csrf,'Accept':'application/json'} };
      if(isForm){ opt.body=body; } else { opt.headers['Content-Type']='application/json'; opt.body=JSON.stringify(body||{}); }
      const r=await fetch(url,opt); return r.json();
    },
    async enviar(){
      const t=this.texto.trim(); if(!t||this.ocupado) return;
      this.ocupado=true; this.pensando=true;
      // mostra a msg do cliente na hora
      this.mensagens.push({id:'tmp'+Date.now(), mine:true, origem:'cliente', texto:t, pix:false, hora:''});
      this.texto=''; this.scrollFim();
      try{ const d=await this.post(this.base+'/enviar',{texto:t}); if(d.ok) this.aplicar(d); else alert(d.erro||'erro'); }
      catch(e){ alert('Falha de conexão'); }
      finally{ this.pensando=false; this.ocupado=false; this.$refs.inp.focus(); }
    },
    async enviarFoto(ev){
      const f=ev.target.files[0]; if(!f) return;
      this.ocupado=true; this.pensando=true; this.scrollFim();
      const fd=new FormData(); fd.append('foto',f);
      try{ const d=await this.post(this.base+'/foto',fd,true); if(d.ok) this.aplicar(d); else alert(d.erro||'erro'); }
      catch(e){ alert('Falha de conexão'); }
      finally{ this.pensando=false; this.ocupado=false; ev.target.value=''; }
    },
    async pagar(){
      if(this.ocupado) return; this.ocupado=true; this.pensando=true;
      try{ const d=await this.post(this.base+'/pagar',{}); if(d.ok) this.aplicar(d); else alert(d.erro||'Sem PIX pendente'); }
      catch(e){ alert('Falha de conexão'); }
      finally{ this.pensando=false; this.ocupado=false; }
    },
    async reiniciar(){
      if(this.ocupado) return; if(!confirm('Apagar essa conversa de teste e começar do zero?')) return;
      this.ocupado=true;
      try{ const d=await this.post(this.base+'/reiniciar',{}); if(d.ok){ this.mensagens=[]; this.funil='novo'; } }
      catch(e){ alert('Falha de conexão'); }
      finally{ this.ocupado=false; }
    },
    copiar(t,ev){ navigator.clipboard?.writeText(t); const b=ev.target; const o=b.textContent; b.textContent='✅ Copiado'; setTimeout(()=>b.textContent=o,1500); },
  };
}
</script>
@endsection
