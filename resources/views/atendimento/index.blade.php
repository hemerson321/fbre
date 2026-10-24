@extends('layouts.app')
@section('titulo','Atendimento')
@section('conteudo')
@php $sel = $conversa; @endphp
<div class="h-[calc(100vh-10rem)] lg:grid lg:grid-cols-3 gap-4">

  {{-- LISTA --}}
  <div class="lg:col-span-1 flex flex-col min-h-0 {{ $sel ? 'hidden lg:flex' : 'flex' }}">
    <div class="flex items-center justify-between mb-2">
      <h1 class="text-lg font-bold">Atendimento</h1>
      @if($aguardando)<span class="chip bg-amber-100 text-amber-700 text-xs font-bold">{{ $aguardando }} aguardando</span>@endif
    </div>
    <div class="flex gap-1 mb-2 text-xs">
      @foreach(['ativas'=>'Ativas','aguardando'=>'Aguardando','humano'=>'Comigo','fechadas'=>'Fechadas'] as $k=>$l)
        <a href="{{ route('atendimento.index',['status'=>$k]) }}" class="px-2.5 py-1 rounded-full font-semibold {{ $filtro===$k ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600' }}">{{ $l }}</a>
      @endforeach
    </div>
    <div class="card flex-1 overflow-y-auto divide-y">
      @forelse($conversas as $c)
        <a href="{{ route('atendimento.show',$c) }}" class="flex items-center gap-3 p-3 hover:bg-slate-50 {{ $sel && $sel->id===$c->id ? 'bg-slate-100' : '' }}">
          <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center font-bold text-slate-500 shrink-0">{{ mb_strtoupper(mb_substr($c->nomeExibicao(),0,1)) }}</div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-2">
              <span class="font-semibold truncate">{{ $c->nomeExibicao() }}</span>
              <span class="text-[10px] text-slate-400 shrink-0">{{ optional($c->ultima_msg_em)->format('d/m H:i') }}</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="text-xs text-slate-500 truncate flex-1">{{ $c->ultima_msg_texto }}</span>
              @if($c->nao_lidas)<span class="min-w-[18px] text-center rounded-full bg-emerald-500 text-white text-[10px] font-bold px-1">{{ $c->nao_lidas }}</span>@endif
            </div>
            <span class="chip text-[10px] bg-{{ $c->cor() }}-100 text-{{ $c->cor() }}-700 mt-0.5">{{ $c->statusLabel() }}</span>@if($c->funilLabel())<span class="chip text-[10px] bg-indigo-100 text-indigo-700 mt-0.5 ml-1">{{ $c->funilLabel() }}</span>@endif
          </div>
        </a>
      @empty
        <div class="p-8 text-center text-slate-400 text-sm">Nenhuma conversa.</div>
      @endforelse
    </div>
  </div>

  {{-- CONVERSA --}}
  <div class="lg:col-span-2 flex flex-col min-h-0 {{ $sel ? 'flex' : 'hidden lg:flex' }}">
    @if($sel)
    <div x-data="chat({{ $sel->id }}, {{ optional($sel->mensagens->last())->id ?? 0 }})" class="flex flex-col h-full card overflow-hidden">
      {{-- header --}}
      <div class="flex items-center gap-3 p-3 border-b bg-slate-50">
        <a href="{{ route('atendimento.index') }}" class="lg:hidden text-slate-500">←</a>
        <div class="w-9 h-9 rounded-full bg-slate-200 flex items-center justify-center font-bold text-slate-500">{{ mb_strtoupper(mb_substr($sel->nomeExibicao(),0,1)) }}</div>
        <div class="min-w-0 flex-1">
          <div class="font-bold truncate">{{ $sel->nomeExibicao() }}</div>
          <div class="text-xs text-slate-500">{{ $sel->cliente_telefone }} · <span class="font-semibold text-{{ $sel->cor() }}-600">{{ $sel->statusLabel() }}</span>{{ $sel->atendente ? ' · '.$sel->atendente->name : '' }}@if($sel->funilLabel()) · <span class="chip bg-indigo-100 text-indigo-700 text-[10px] font-bold">{{ $sel->funilLabel() }}</span>@endif</div>
        </div>
        @if($sel->status!=='humano')
        <form method="post" action="{{ route('atendimento.assumir',$sel) }}">@csrf<button class="btn btn-primary btn-sm" style="background:var(--cor1)">Assumir</button></form>
        @endif
        <form method="post" action="{{ route('atendimento.reiniciar',$sel) }}" onsubmit="return confirm('Reiniciar a sessão? O agente atende como novo.')">@csrf<button class="btn btn-ghost btn-sm" title="Agente esquece o historico e atende como novo">🔄 Reiniciar</button></form>
        <form method="post" action="{{ route('atendimento.fechar',$sel) }}" onsubmit="return confirm('Finalizar o pedido? A conversa é encerrada e, se o cliente voltar, começa do zero.')">@csrf<button class="btn btn-primary btn-sm" style="background:var(--cor2)">✅ Finalizar</button></form>
      </div>

      {{-- mensagens --}}
      <div id="msgs" x-ref="msgs" class="flex-1 overflow-y-auto p-4 space-y-2 bg-slate-50/50">
        @foreach($sel->mensagens as $m)
          <div class="flex {{ $m->direcao==='entrada' ? 'justify-start' : 'justify-end' }}">
            <div class="max-w-[75%] rounded-2xl px-3 py-2 text-sm {{ $m->direcao==='entrada' ? 'bg-white border border-slate-200' : ($m->origem==='ia' ? 'bg-cyan-600 text-white' : 'bg-emerald-600 text-white') }}">
              @if($m->direcao!=='entrada')<div class="text-[10px] opacity-80 font-semibold">{{ $m->origem==='ia' ? '🤖 Agente' : ($m->autor?->name ?? 'Atendente') }}</div>@endif
              <div class="whitespace-pre-wrap">{{ $m->texto }}</div>
              <div class="text-[10px] opacity-60 text-right mt-0.5">{{ $m->created_at?->format('H:i') }}</div>
            </div>
          </div>
        @endforeach
      </div>

      {{-- responder --}}
      <form method="post" action="{{ route('atendimento.responder',$sel) }}" class="p-3 border-t flex gap-2">@csrf
        <input name="texto" class="input flex-1" placeholder="Escreva uma mensagem…" autocomplete="off" required maxlength="4000" autofocus>
        <button class="btn btn-primary" style="background:var(--cor1)">Enviar</button>
      </form>
    </div>
    @else
    <div class="hidden lg:flex flex-1 items-center justify-center text-slate-400 text-sm card">Selecione uma conversa</div>
    @endif
  </div>
</div>

<script>
function chat(id, ultimoId){
  return {
    ultimo: ultimoId,
    init(){ this.rolar(); this.timer = setInterval(()=>this.puxar(), 4000); },
    rolar(){ this.$nextTick(()=>{ var m=this.$refs.msgs; if(m) m.scrollTop=m.scrollHeight; }); },
    async puxar(){
      try{
        var r = await fetch('/passagens/atendimento/'+id+'/mensagens?desde='+this.ultimo, {headers:{'X-Requested-With':'fetch'}});
        if(!r.ok) return;
        var d = await r.json();
        if(d.mensagens && d.mensagens.length){
          var box = this.$refs.msgs;
          d.mensagens.forEach(function(m){
            this.ultimo = m.id;
            var ent = m.direcao==='entrada';
            var cls = ent ? 'bg-white border border-slate-200' : (m.origem==='ia' ? 'bg-cyan-600 text-white' : 'bg-emerald-600 text-white');
            var autor = !ent ? ('<div class="text-[10px] opacity-80 font-semibold">'+(m.origem==='ia'?'🤖 Agente':(m.autor||'Atendente'))+'</div>') : '';
            var html = '<div class="flex '+(ent?'justify-start':'justify-end')+'"><div class="max-w-[75%] rounded-2xl px-3 py-2 text-sm '+cls+'">'+autor+'<div class="whitespace-pre-wrap"></div><div class="text-[10px] opacity-60 text-right mt-0.5">'+(m.hora||'')+'</div></div></div>';
            var tmp=document.createElement('div'); tmp.innerHTML=html;
            tmp.querySelector('.whitespace-pre-wrap').textContent = m.texto || '';
            box.appendChild(tmp.firstChild);
          }.bind(this));
          this.rolar();
        }
      }catch(e){}
    }
  }
}
</script>
@endsection
