@extends('layouts.app')
@section('titulo','WhatsApp')
@section('conteudo')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<div class="max-w-2xl mx-auto space-y-4">
  <div>
    <h1 class="text-xl font-bold">Conexões de WhatsApp</h1>
    <p class="text-xs text-slate-500">Cadastre uma ou mais conexões (oficial ou não oficial). Depois, em <b>Minha empresa</b>, escolha qual usar em cada serviço (encomenda, passagem...). Reutilizável por todos os módulos.</p>
  </div>

  @if(session('erro'))<div class="rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ session('erro') }}</div>@endif

  {{-- SERVIDOR EVOLUTION (configurar uma vez) --}}
  <form method="post" action="{{ route('admin.whatsapp.servidor') }}" class="card p-4 space-y-3">@csrf
    <div class="font-semibold text-sm">Servidor Evolution <span class="text-xs font-normal text-slate-400">(configure uma vez)</span></div>
    <p class="text-xs text-slate-500">Com isso, cada nova conexão cria a instância sozinha no seu Evolution — você só dá um nome.</p>
    <div class="grid sm:grid-cols-2 gap-3">
      <div class="sm:col-span-2"><label class="label">URL do servidor</label><input name="evo_url" class="input" value="{{ $empresa->config('evo_url') }}" placeholder="https://evolution.seudominio.com"></div>
      <div class="sm:col-span-2"><label class="label">Chave global (API Key global do Evolution)</label><input name="evo_key" type="password" class="input font-mono text-sm" placeholder="{{ $empresa->config('evo_key') ? '•••• deixe em branco para manter' : 'AUTHENTICATION_API_KEY do Evolution' }}" autocomplete="off"></div>
    </div>
    <button class="btn btn-primary btn-sm">Salvar servidor</button>
  </form>

  {{-- NOVA CONEXÃO --}}
  <form method="post" action="{{ route('admin.whatsapp.salvar') }}" class="card p-4 space-y-3" x-data="{tipo:'nao_oficial'}">@csrf
    <div class="font-semibold text-sm">Nova conexão</div>
    <div class="grid sm:grid-cols-2 gap-3">
      <div><label class="label">Nome</label><input name="nome" class="input" required placeholder="Ex.: Evolution principal"></div>
      <div><label class="label">Tipo</label>
        <select name="tipo" x-model="tipo" class="input">
          @foreach(\App\Models\WhatsappConexao::TIPOS as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
        </select></div>
    </div>
    {{-- não oficial: instância criada automaticamente --}}
    <div x-show="tipo==='nao_oficial'" x-data="{adv:false}" class="space-y-2">
      <p class="text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">✅ A instância é criada automaticamente no seu Servidor Evolution. Você só precisa do <b>nome</b> acima.</p>
      <button type="button" class="text-xs text-slate-500 underline" @click="adv=!adv" x-text="adv?'ocultar avançado':'opções avançadas (manual)'"></button>
      <div x-show="adv" x-cloak class="grid sm:grid-cols-2 gap-3">
        <div class="sm:col-span-2"><label class="label">URL base (sobrepõe o servidor)</label><input name="base_url" class="input" placeholder="em branco = usa o servidor"></div>
        <div><label class="label">Instância (em branco = gera)</label><input name="instancia" class="input" placeholder="auto"></div>
        <div><label class="label">API Key (em branco = chave global)</label><input name="api_key" class="input" placeholder="auto"></div>
      </div>
    </div>
    {{-- oficial --}}
    <div x-show="tipo==='oficial'" x-cloak class="grid sm:grid-cols-2 gap-3">
      <div><label class="label">Phone Number ID</label><input name="phone_number_id" class="input" placeholder="ex.: 123456789012345"></div>
      <div><label class="label">WhatsApp Business ID</label><input name="business_id" class="input" placeholder="opcional"></div>
      <div class="sm:col-span-2"><label class="label">Token (access token)</label><input name="token" class="input" placeholder="EAAG..."></div>
      <div><label class="label">Versão da API</label><input name="api_version" class="input" value="v21.0"></div>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="ativo" value="1" checked class="w-5 h-5 rounded"> Ativa</label>
    <button class="btn btn-accent">Salvar conexão</button>
  </form>

  {{-- LISTA --}}
  @forelse($conexoes as $c)
    <form method="post" action="{{ route('admin.whatsapp.salvar',$c) }}" class="card p-4 space-y-3 {{ $c->ativo?'':'opacity-60' }}" x-data="{tipo:'{{ $c->tipo }}'}">@csrf
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full {{ $c->ativo?'bg-emerald-500':'bg-slate-300' }}"></span>
        <span class="font-semibold text-sm flex-1">{{ $c->nome }}</span>
        <span class="text-xs text-slate-500">{{ \App\Models\WhatsappConexao::TIPOS[$c->tipo] ?? $c->tipo }}</span>
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        <div><label class="label">Nome</label><input name="nome" class="input" value="{{ $c->nome }}" required></div>
        <div><label class="label">Tipo</label>
          <select name="tipo" x-model="tipo" class="input">
            @foreach(\App\Models\WhatsappConexao::TIPOS as $k=>$l)<option value="{{ $k }}" @selected($c->tipo===$k)>{{ $l }}</option>@endforeach
          </select></div>
      </div>
      <div x-show="tipo==='nao_oficial'" class="grid sm:grid-cols-2 gap-3">
        <div class="sm:col-span-2"><label class="label">URL base</label><input name="base_url" class="input" value="{{ $c->base_url }}"></div>
        <div><label class="label">Instância</label><input name="instancia" class="input" value="{{ $c->instancia }}"></div>
        <div><label class="label">API Key</label><input name="api_key" class="input" value="{{ $c->api_key }}"></div>
      </div>
      <div x-show="tipo==='oficial'" x-cloak class="grid sm:grid-cols-2 gap-3">
        <div><label class="label">Phone Number ID</label><input name="phone_number_id" class="input" value="{{ $c->phone_number_id }}"></div>
        <div><label class="label">Business ID</label><input name="business_id" class="input" value="{{ $c->business_id }}"></div>
        <div class="sm:col-span-2"><label class="label">Token</label><input name="token" class="input" value="{{ $c->token }}"></div>
        <div><label class="label">Versão da API</label><input name="api_version" class="input" value="{{ $c->api_version }}"></div>
      </div>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="ativo" value="1" @checked($c->ativo) class="w-5 h-5 rounded"> Ativa</label>
      <div class="flex flex-wrap gap-2 items-center">
        <button class="btn btn-primary btn-sm">Salvar</button>
        <button type="button" onclick="if(confirm('Remover esta conexão?')) document.getElementById('del-wa-{{ $c->id }}').submit()" class="btn btn-ghost btn-sm text-red-500">Remover</button>
      </div>
      {{-- teste rápido --}}
      <div class="border-t pt-3 flex flex-wrap items-end gap-2">
        <div class="flex-1 min-w-[160px]"><label class="label !text-xs">Testar envio (telefone com DDD)</label>
          <input form="teste-{{ $c->id }}" name="telefone" class="input !min-h-[38px]" placeholder="92 99999-9999"></div>
        <button form="teste-{{ $c->id }}" class="btn btn-ghost btn-sm">Enviar teste</button>
      </div>
      {{-- Gerenciar instância Evolution (criar/QR/status) pelo sistema --}}
      <div x-show="tipo==='nao_oficial'" x-data="evo({{ $c->id }})" x-init="verStatus()" class="border-t pt-3 space-y-2">
        <div class="flex items-center gap-2 text-sm">
          <span class="text-slate-500">Status:</span>
          <span class="font-bold" :class="conectado?'text-emerald-600':'text-slate-400'" x-text="estadoTxt"></span>
          <button type="button" class="text-xs underline text-slate-400" @click="verStatus()">atualizar</button>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('criar-{{ $c->id }}').submit()">Criar instância</button>
          <button type="button" class="btn btn-primary btn-sm" style="background:var(--cor1)" @click="abrirQr()">Conectar (QR)</button>
          <button type="button" class="btn btn-ghost btn-sm text-red-500" onclick="if(confirm('Desconectar este WhatsApp?'))document.getElementById('desc-{{ $c->id }}').submit()">Desconectar</button>
        </div>
        <div x-show="qrAberto" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="fecharQr()">
          <div class="bg-white rounded-2xl p-5 max-w-xs w-full text-center space-y-3">
            <div class="font-bold">Conectar WhatsApp</div>
            <p class="text-xs text-slate-500">No celular: WhatsApp → Aparelhos conectados → Conectar um aparelho, e aponte pro QR.</p>
            <template x-if="qr"><img :src="qr" class="w-56 h-56 mx-auto rounded-lg border"></template>
            <div x-show="!qr && temCode" class="flex justify-center"><div x-ref="qrdiv"></div></div>
            <template x-if="!qr && !temCode"><div class="text-sm text-slate-400 py-16" x-text="qrMsg"></div></template>
            <div class="text-sm font-semibold" :class="conectado?'text-emerald-600':'text-amber-600'" x-text="conectado?'Conectado ✅':'Aguardando leitura…'"></div>
            <button type="button" class="btn btn-ghost btn-sm w-full" @click="fecharQr()">Fechar</button>
          </div>
        </div>
      </div>
    </form>
    <form id="teste-{{ $c->id }}" method="post" action="{{ route('admin.whatsapp.testar',$c) }}" class="hidden">@csrf</form>
    <form id="criar-{{ $c->id }}" method="post" action="{{ route('admin.whatsapp.criar',$c) }}" class="hidden">@csrf</form>
    <form id="desc-{{ $c->id }}" method="post" action="{{ route('admin.whatsapp.desconectar',$c) }}" class="hidden">@csrf</form>
    <form id="del-wa-{{ $c->id }}" method="post" action="{{ route('admin.whatsapp.excluir',$c) }}" class="hidden">@csrf @method('DELETE')</form>
  @empty
    <div class="text-sm text-slate-400 text-center py-4">Nenhuma conexão ainda. Cadastre a primeira acima.</div>
  @endforelse
</div>

<script>
function evo(id){
  return {
    conectado:false, estadoTxt:'—', qrAberto:false, qr:null, temCode:false, qrMsg:'Gerando QR…', timer:null,
    async verStatus(){
      try{ var r=await fetch('/admin/whatsapp/'+id+'/status',{headers:{'X-Requested-With':'fetch'}}); var d=await r.json();
        if(d.ok){ this.conectado=d.conectado; this.estadoTxt=d.conectado?'Conectado':(d.estado||'desconectado'); }
        else { this.estadoTxt='—'; }
      }catch(e){}
    },
    async abrirQr(){
      this.qrAberto=true; this.qr=null; this.temCode=false; this.qrMsg='Gerando QR…';
      try{ var r=await fetch('/admin/whatsapp/'+id+'/conectar',{headers:{'X-Requested-With':'fetch'}}); var d=await r.json();
        if(d.ok && d.base64){ this.qr = (''+d.base64).indexOf('data:')===0 ? d.base64 : ('data:image/png;base64,'+d.base64); }
        else if(d.ok && d.code){ this.temCode=true; var self=this; this.$nextTick(function(){ try{ self.$refs.qrdiv.innerHTML=''; new QRCode(self.$refs.qrdiv,{text:d.code,width:224,height:224,correctLevel:QRCode.CorrectLevel.L}); }catch(e){ self.temCode=false; self.qrMsg='Não consegui desenhar o QR.'; } }); }
        else if(d.ok){ this.qrMsg = d.bruto ? ('Evolution sem QR: '+d.bruto) : 'Já conectado ou sem QR.'; this.verStatus(); }
        else { this.qrMsg = 'Erro: ' + (d.erro || 'falhou. Confira a URL base e se a API Key é a chave global do Evolution.'); }
      }catch(e){ this.qrMsg='Erro de conexão com o servidor.'; }
      if(this.timer) clearInterval(this.timer);
      this.timer=setInterval(()=>this.checar(), 3000);
    },
    async checar(){ await this.verStatus(); if(this.conectado){ if(this.timer) clearInterval(this.timer); var self=this; setTimeout(function(){ self.fecharQr(); }, 1500); } },
    fecharQr(){ this.qrAberto=false; if(this.timer) clearInterval(this.timer); }
  }
}
</script>
@endsection
