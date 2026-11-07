@extends('layouts.app')
@section('titulo','Simulação — 4 telas')
@section('conteudo')
@php
  $frames = [
    ['admin',    '🛡️ Administrador', 'desktop', $admins],
    ['operador', '💼 Operador',       'desktop', $operadores],
    ['agente',   '🤝 Agente',         'mobile',  $agentes],
    ['dono',     '🚤 Dono',           'mobile',  $donos],
  ];
  $frameHtml = function ($f) {
    [$role,$label,$tipo,$lista] = $f;
    $opts = '<option value="">'.$label.' — escolher usuário</option>';
    foreach ($lista as $u) {
      $opts .= '<option value="'.$u->id.'">'.e($u->name).($u->prefixo ? ' ('.e($u->prefixo).')' : '').'</option>';
    }
    return '<div class="sim-frame sim-'.$tipo.'" data-role="'.$role.'"><div class="sim-wrap">'
      .'<div class="sim-head"><select class="sim-sel" data-sim="'.$role.'">'.$opts.'</select><span class="sim-tipo">'.$tipo.'</span></div>'
      .'<div class="sim-view" data-view></div></div></div>';
  };
@endphp
<div class="space-y-3" x-data="{ tab:'desktop' }">
  <div class="flex items-center justify-between flex-wrap gap-2">
    <div>
      <h1 class="text-xl font-bold">🧪 Simulação — 4 telas</h1>
      <p class="text-xs text-slate-500">Escolha o usuário no topo de cada quadro.</p>
    </div>
    <button type="button" onclick="recarregar()" class="btn btn-ghost btn-sm">🔄 Atualizar</button>
  </div>

  {{-- abas desktop / celular --}}
  <div class="flex gap-2">
    <button type="button" @click="tab='desktop'" class="btn btn-sm" :class="tab==='desktop' ? 'text-white' : 'btn-ghost'" :style="tab==='desktop' ? 'background:var(--cor1)' : ''">🖥️ Desktop</button>
    <button type="button" @click="tab='celular'" class="btn btn-sm" :class="tab==='celular' ? 'text-white' : 'btn-ghost'" :style="tab==='celular' ? 'background:var(--cor1)' : ''">📱 Celular</button>
  </div>

  <div x-show="tab==='desktop'" class="grid lg:grid-cols-2 gap-4">
    @foreach(array_filter($frames, fn($f)=>$f[2]==='desktop') as $f){!! $frameHtml($f) !!}@endforeach
  </div>

  <div x-show="tab==='celular'" x-cloak class="grid grid-cols-2 gap-4 max-w-xl">
    @foreach(array_filter($frames, fn($f)=>$f[2]==='mobile') as $f){!! $frameHtml($f) !!}@endforeach
  </div>
</div>

<style>
.sim-wrap{ border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.08); }
.sim-head{ padding:6px 8px; background:#0b1a33; display:flex; justify-content:space-between; align-items:center; gap:8px; }
.sim-sel{ flex:1; min-width:0; background:#fff; color:#0b1a33; border:0; border-radius:8px; padding:6px 8px; font-size:12px; font-weight:700; }
.sim-tipo{ color:#fff; opacity:.6; font-size:11px; text-transform:uppercase; }
.sim-view{ overflow:hidden; background:#f1f5f9; }
.sim-view iframe{ border:0; background:#fff; display:block; }
.sim-desktop .sim-view{ height:520px; }
.sim-desktop iframe{ width:1280px; height:1080px; transform:scale(.481); transform-origin:top left; }
.sim-mobile{ justify-self:center; }
.sim-mobile .sim-view{ width:300px; height:600px; }
.sim-mobile iframe{ width:390px; height:780px; transform:scale(.769); transform-origin:top left; }
.sim-empty{ display:flex; align-items:center; justify-content:center; height:100%; min-height:200px; color:#94a3b8; font-size:13px; text-align:center; padding:12px; }
</style>

<script>
function setFrame(frame){
  var sel=frame.querySelector('select[data-sim]');
  var view=frame.querySelector('[data-view]');
  var id=sel.value;
  if(id){ view.innerHTML='<iframe src="{{ url('/simulacao/tela') }}/'+encodeURIComponent(id)+'?t='+Date.now()+'"></iframe>'; }
  else { view.innerHTML='<div class="sim-empty">Escolha o usuário no topo.</div>'; }
}
function recarregar(){ document.querySelectorAll('.sim-frame').forEach(setFrame); }
document.querySelectorAll('.sim-frame').forEach(function(frame){
  var sel=frame.querySelector('select[data-sim]');
  var key='sim_user_'+frame.getAttribute('data-role');
  try{ var v=localStorage.getItem(key); if(v){ sel.value=v; } }catch(e){}
  sel.addEventListener('change', function(){ try{ localStorage.setItem(key, sel.value); }catch(e){} setFrame(frame); });
  setFrame(frame);
});
</script>
@endsection
