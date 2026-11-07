@extends('layouts.app')
@section('titulo','Simulação')
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
      .'<div class="sim-head"><select class="sim-sel" data-sim="'.$role.'">'.$opts.'</select><span class="sim-tipo">'.($tipo==='desktop'?'🖥️ desktop':'📱 celular').'</span></div>'
      .'<div class="sim-view" data-view></div></div></div>';
  };
@endphp
<div class="space-y-3" x-data="{ tab:'admin' }">
  <div class="flex items-center justify-between flex-wrap gap-2">
    <h1 class="text-xl font-bold">Simulação</h1>
    <button type="button" onclick="recarregar()" class="btn btn-ghost btn-sm">🔄 Atualizar</button>
  </div>

  {{-- 1 aba por tela --}}
  <div class="flex flex-wrap gap-2">
    @foreach($frames as $f)
      <button type="button" @click="tab='{{ $f[0] }}'" class="btn btn-sm" :class="tab==='{{ $f[0] }}' ? 'text-white' : 'btn-ghost'" :style="tab==='{{ $f[0] }}' ? 'background:var(--cor1)' : ''">{{ $f[1] }}</button>
    @endforeach
  </div>

  @foreach($frames as $f)
    <div x-show="tab==='{{ $f[0] }}'" x-cloak>{!! $frameHtml($f) !!}</div>
  @endforeach
</div>

<style>
.sim-wrap{ border:1px solid #e2e8f0; border-radius:16px; overflow:hidden; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.08); }
.sim-head{ padding:6px 8px; background:#0b1a33; display:flex; justify-content:space-between; align-items:center; gap:8px; }
.sim-sel{ flex:1; min-width:0; background:#fff; color:#0b1a33; border:0; border-radius:8px; padding:8px 10px; font-size:13px; font-weight:700; }
.sim-tipo{ color:#fff; opacity:.7; font-size:11px; white-space:nowrap; }
.sim-view{ overflow:hidden; background:#f1f5f9; }
.sim-view iframe{ border:0; background:#fff; display:block; }
.sim-desktop .sim-view{ width:794px; max-width:100%; height:620px; }
.sim-desktop iframe{ width:1280px; height:1000px; transform:scale(.62); transform-origin:top left; }
.sim-mobile{ max-width:380px; }
.sim-mobile .sim-view{ width:360px; height:690px; }
.sim-mobile iframe{ width:390px; height:748px; transform:scale(.923); transform-origin:top left; }
.sim-empty{ display:flex; align-items:center; justify-content:center; height:100%; min-height:300px; color:#94a3b8; font-size:13px; text-align:center; padding:12px; }
</style>

<script>
function setFrame(frame){
  var sel=frame.querySelector('select[data-sim]');
  var view=frame.querySelector('[data-view]');
  var id=sel.value;
  if(id){ view.innerHTML='<iframe src="{{ url('/') }}?preview_as='+encodeURIComponent(id)+'&t='+Date.now()+'"></iframe>'; }
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
