@extends('layouts.app')
@section('titulo','Simulação — 4 telas')
@section('conteudo')
<div class="space-y-3">
  <div class="flex items-center justify-between flex-wrap gap-2">
    <div>
      <h1 class="text-xl font-bold">🧪 Simulação — 4 telas</h1>
      <p class="text-xs text-slate-500">As 4 visões ao mesmo tempo: 2 em desktop e 2 em celular. Escolha os usuários na <a href="{{ route('simulacao') }}" class="underline">Simulação</a>.</p>
    </div>
    <div class="flex gap-2">
      <a href="{{ route('simulacao') }}" class="btn btn-ghost btn-sm">⚙️ Escolher usuários</a>
      <button type="button" onclick="recarregar()" class="btn btn-ghost btn-sm">🔄 Atualizar</button>
    </div>
  </div>

  <div class="text-[11px] font-bold uppercase text-slate-400">🖥️ Desktop</div>
  <div class="grid lg:grid-cols-2 gap-4">
    <div data-role="admin" data-label="🛡️ Administrador" class="sim-frame sim-desktop"></div>
    <div data-role="operador" data-label="💼 Operador" class="sim-frame sim-desktop"></div>
  </div>

  <div class="text-[11px] font-bold uppercase text-slate-400 pt-1">📱 Celular</div>
  <div class="grid grid-cols-2 gap-4 max-w-xl">
    <div data-role="agente" data-label="🤝 Agente" class="sim-frame sim-mobile"></div>
    <div data-role="dono" data-label="🚤 Dono" class="sim-frame sim-mobile"></div>
  </div>
</div>

<style>
.sim-wrap{ border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.08); }
.sim-head{ font-size:12px; font-weight:700; padding:6px 10px; background:#0b1a33; color:#fff; display:flex; justify-content:space-between; align-items:center; }
.sim-view{ overflow:hidden; background:#f1f5f9; }
.sim-view iframe{ border:0; background:#fff; display:block; }
.sim-desktop .sim-view{ height:430px; }
.sim-desktop iframe{ width:1280px; height:935px; transform:scale(.46); transform-origin:top left; }
.sim-mobile{ justify-self:center; }
.sim-mobile .sim-view{ width:252px; height:500px; }
.sim-mobile iframe{ width:390px; height:774px; transform:scale(.646); transform-origin:top left; }
.sim-empty{ display:flex; align-items:center; justify-content:center; height:100%; min-height:200px; color:#94a3b8; font-size:13px; text-align:center; padding:12px; }
</style>

<script>
function montar(){
  document.querySelectorAll('.sim-frame').forEach(function(f){
    var role=f.getAttribute('data-role'), label=f.getAttribute('data-label');
    var id=null; try{ id=localStorage.getItem('sim_user_'+role); }catch(e){}
    var tipo=f.classList.contains('sim-desktop')?'desktop':'celular';
    var head='<div class="sim-head"><span>'+label+'</span><span style="opacity:.6;font-weight:500">'+tipo+'</span></div>';
    if(id){
      f.innerHTML='<div class="sim-wrap">'+head+'<div class="sim-view"><iframe src="{{ url('/simulacao/tela') }}/'+encodeURIComponent(id)+'?t='+Date.now()+'" loading="lazy"></iframe></div></div>';
    } else {
      f.innerHTML='<div class="sim-wrap">'+head+'<div class="sim-view"><div class="sim-empty">Escolha o usuário de<br><b>'+label+'</b><br>na tela de Simulação.</div></div></div>';
    }
  });
}
function recarregar(){ montar(); }
montar();
</script>
@endsection
