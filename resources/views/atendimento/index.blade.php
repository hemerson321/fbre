@extends('layouts.app')
@section('titulo','Atendimento')
@section('conteudo')
@php $sel = $conversa; @endphp
<div class="h-[calc(100vh-10rem)] lg:grid lg:grid-cols-3 gap-4">

  {{-- LISTA --}}
  <div class="lg:col-span-1 flex flex-col min-h-0 {{ $sel ? 'hidden lg:flex' : 'flex' }}">
    <div class="flex items-center justify-between mb-2">
      <h1 class="text-lg font-bold">Atendimento</h1>
      <div class="flex items-center gap-1.5">
      @if($aguardando)<span class="chip bg-amber-100 text-amber-700 text-xs font-bold">{{ $aguardando }} aguardando</span>@endif
      <span id="badgePrecisa" class="chip bg-rose-100 text-rose-700 text-xs font-bold" style="{{ ($precisaHumano ?? 0) ? '' : 'display:none' }}">🔔 {{ $precisaHumano ?? 0 }}</span>
      </div>
    </div>
    <div class="flex gap-1 mb-2 text-xs">
      @foreach(['ativas'=>'Ativas','aguardando'=>'Aguardando','humano'=>'Comigo','fechadas'=>'Fechadas'] as $k=>$l)
        <a href="{{ route('atendimento.index',['status'=>$k]) }}" class="px-2.5 py-1 rounded-full font-semibold {{ $filtro===$k ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600' }}">{{ $l }}</a>
      @endforeach
    </div>
    <div id="alertaAtend" class="mb-2 rounded-xl border border-rose-300 bg-rose-50 p-2" style="{{ ($precisaHumano ?? 0) ? '' : 'display:none' }}">
      <div class="text-xs font-bold text-rose-700 mb-1">🔔 <span data-n>{{ $precisaHumano ?? 0 }}</span> cliente(s) esperando atendente</div>
      <div data-lista class="space-y-0.5"></div>
    </div>
    <div id="listaConversas" class="card flex-1 overflow-y-auto divide-y">
      @include('atendimento._lista', ['conversas' => $conversas, 'sel' => $sel])
    </div>
  </div>

  {{-- CONVERSA --}}
  <div class="lg:col-span-2 flex flex-col min-h-0 {{ $sel ? 'flex' : 'hidden lg:flex' }}">
    @if($sel)
    <div x-data="chat({{ $sel->id }}, {{ optional($sel->mensagens->last())->id ?? 0 }}, @js($vendaResumo))" class="flex flex-col h-full card overflow-hidden">
      {{-- header --}}
      <div class="flex items-center gap-3 p-3 border-b bg-slate-50">
        <a href="{{ route('atendimento.index') }}" class="lg:hidden text-slate-500">←</a>
        <div class="w-9 h-9 rounded-full bg-slate-200 flex items-center justify-center font-bold text-slate-500">{{ mb_strtoupper(mb_substr($sel->nomeExibicao(),0,1)) }}</div>
        <div class="min-w-0 flex-1">
          <div class="font-bold truncate">{{ $sel->nomeExibicao() }}</div>
          <div class="text-xs text-slate-500">{{ $sel->cliente_telefone }} · <span class="font-semibold text-{{ $sel->cor() }}-600">{{ $sel->statusLabel() }}</span>{{ $sel->atendente ? ' · '.$sel->atendente->name : '' }}@if($sel->funilLabel()) · <span class="chip bg-indigo-100 text-indigo-700 text-[10px] font-bold">{{ $sel->funilLabel() }}</span>@endif
          @if(!empty($sel->contexto['pref_poltrona'])) · <span class="chip bg-sky-100 text-sky-700 text-[10px] font-bold">🪑 Prefere {{ ucfirst($sel->contexto['pref_poltrona']) }}{{ !empty($sel->contexto['poltronas_agente']) ? ' · poltrona '.implode(', ', (array) $sel->contexto['poltronas_agente']) : '' }}</span>@endif</div>
        </div>
        @if($sel->status!=='humano')
        <form method="post" action="{{ route('atendimento.assumir',$sel) }}">@csrf<button class="btn btn-primary btn-sm" style="background:var(--cor1)">Assumir</button></form>
        @endif
        <form method="post" action="{{ route('atendimento.reiniciar',$sel) }}" onsubmit="return confirm('Reiniciar a sessão? O agente atende como novo.')">@csrf<button class="btn btn-ghost btn-sm" title="Agente esquece o historico e atende como novo">🔄 Reiniciar</button></form>
        <form method="post" action="{{ route('atendimento.fechar',$sel) }}" onsubmit="return confirm('Finalizar o pedido? A conversa é encerrada e, se o cliente voltar, começa do zero.')">@csrf<button class="btn btn-primary btn-sm" style="background:var(--cor2)">✅ Finalizar</button></form>
      </div>

      {{-- VENDA AO VIVO --}}
      <div x-show="venda && (venda.origem || venda.destino || (venda.poltronas&&venda.poltronas.length))" x-cloak class="px-3 py-2 border-b bg-cyan-50/70">
        <div class="text-[11px] font-bold text-cyan-800 mb-0.5">🧾 Venda em andamento <span class="opacity-60 font-normal" x-text="venda&&venda.funil?('· '+venda.funil):''"></span></div>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-slate-700">
          <span x-show="venda&&(venda.origem||venda.destino)" class="font-semibold" x-text="(venda.origem||'?')+' → '+(venda.destino||'?')"></span>
          <span x-show="venda&&venda.data">📅 <span x-text="venda.data"></span></span>
          <span x-show="venda&&venda.passageiros">👤 <span x-text="venda.passageiros+'x'"></span></span>
          <span x-show="venda&&venda.pref">🪑 <span x-text="venda.pref"></span></span>
          <span x-show="venda&&venda.poltronas&&venda.poltronas.length" class="font-bold text-cyan-700">💺 <span x-text="(venda.poltronas||[]).join(', ')"></span></span>
        </div>
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
function chat(id, ultimoId, vendaIni){
  return {
    ultimo: ultimoId,
    venda: vendaIni || null,
    init(){ this.rolar(); this.timer = setInterval(()=>this.puxar(), 4000); },
    rolar(){ this.$nextTick(()=>{ var m=this.$refs.msgs; if(m) m.scrollTop=m.scrollHeight; }); },
    async puxar(){
      try{
        var r = await fetch('/passagens/atendimento/'+id+'/mensagens?desde='+this.ultimo, {headers:{'X-Requested-With':'fetch'}});
        if(!r.ok) return;
        var d = await r.json();
        if(d.venda) this.venda = d.venda;
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
<script>
(function(){
  var last = null;
  var lastHash = null;
  var banner = document.getElementById('alertaAtend');
  var listaUrl = '{{ route('atendimento.lista') }}';
  var curFiltro = @json($filtro);
  var curSel = {{ $sel->id ?? 'null' }};
  async function refreshLista(){
    try{
      var r = await fetch(listaUrl+'?status='+encodeURIComponent(curFiltro)+'&sel='+(curSel||''), {headers:{'X-Requested-With':'fetch'}});
      if(!r.ok) return; var d = await r.json();
      var box = document.getElementById('listaConversas'); if(box && typeof d.html==='string') box.innerHTML = d.html;
      if(d.hash) lastHash = d.hash;
    }catch(e){}
  }
  function beep(){ try{ var a=new (window.AudioContext||window.webkitAudioContext)(); var o=a.createOscillator(); var g=a.createGain(); o.connect(g); g.connect(a.destination); o.type='sine'; o.frequency.value=880; g.gain.value=0.12; o.start(); setTimeout(function(){o.frequency.value=660;},150); setTimeout(function(){o.stop();a.close();},340);}catch(e){} }
  function notif(n){ try{ if(window.Notification && Notification.permission==='granted'){ new Notification('WhatsApp — novo atendimento', {body:n+' cliente(s) esperando atendente'}); } }catch(e){} }
  async function poll(){
    try{
      var r = await fetch('{{ route('atendimento.alertas') }}', {headers:{'X-Requested-With':'fetch'}});
      if(!r.ok) return;
      var d = await r.json(); var n = d.precisa||0;
      var b=document.getElementById('badgePrecisa'); if(b){ b.textContent='🔔 '+n; b.style.display = n>0?'':'none'; }
      if(banner){
        if(n>0){ banner.style.display=''; var cn=banner.querySelector('[data-n]'); if(cn) cn.textContent=n;
          var ul=banner.querySelector('[data-lista]'); ul.innerHTML='';
          (d.itens||[]).forEach(function(it){ var a=document.createElement('a'); a.href='/passagens/atendimento/'+it.id; a.className='block text-sm font-semibold text-rose-700 hover:underline'; a.textContent='🔔 '+it.nome+(it.motivo?(' — '+it.motivo):''); ul.appendChild(a); });
        } else banner.style.display='none';
      }
      if(last!==null && n>last){ beep(); notif(n); }
      last=n;
      if(d.hash){ if(lastHash===null){ lastHash=d.hash; } else if(d.hash!==lastHash){ refreshLista(); } }
    }catch(e){}
  }
  try{ if(window.Notification && Notification.permission==='default'){ Notification.requestPermission(); } }catch(e){}
  poll(); setInterval(poll, 5000);
})();
</script>
@endsection
