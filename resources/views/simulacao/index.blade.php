@extends('layouts.app')
@section('titulo','Simulação')
@section('conteudo')
<div class="max-w-xl mx-auto space-y-4">
  <div>
    <h1 class="text-xl font-bold">🧪 Simulação (demo)</h1>
    <p class="text-xs text-slate-500">Escolha um usuário para cada papel. Depois clique em <b>Ir para a simulação</b> pra ver as 4 telas ao mesmo tempo (2 desktop + 2 celular).</p>
  </div>

  <div class="card p-4 space-y-3">
    @php
      $papeis = [
        'admin'    => ['🛡️ Administrador', $admins],
        'operador' => ['💼 Operador (balcão)', $operadores],
        'agente'   => ['🤝 Agente externo', $agentes],
        'dono'     => ['🚤 Dono do transporte', $donos],
      ];
    @endphp
    @foreach($papeis as $k=>$p)
      <div>
        <label class="label !text-xs">{{ $p[0] }}</label>
        <select class="input !min-h-[44px]" data-sim="{{ $k }}">
          <option value="">— escolher usuário —</option>
          @foreach($p[1] as $us)<option value="{{ $us->id }}">{{ $us->name }}{{ $us->prefixo ? ' ('.$us->prefixo.')' : '' }}</option>@endforeach
        </select>
        @if($p[1]->isEmpty())<div class="text-[11px] text-slate-400 mt-1">Nenhum usuário nesse papel ainda.</div>@endif
      </div>
    @endforeach
  </div>

  <a href="{{ route('simulacao.telas') }}" class="btn btn-accent w-full !min-h-[48px] text-base font-bold">▶️ Ir para a simulação (4 telas)</a>
</div>

<script>
(function(){
  document.querySelectorAll('select[data-sim]').forEach(function(sel){
    var key='sim_user_'+sel.getAttribute('data-sim');
    try{ var v=localStorage.getItem(key); if(v){ sel.value=v; } }catch(e){}
    sel.addEventListener('change', function(){ try{ localStorage.setItem(key, sel.value); }catch(e){} });
  });
})();
</script>
@endsection
