@extends('layouts.app')
@section('titulo','Simulação')
@section('conteudo')
<div class="max-w-2xl mx-auto space-y-4" x-data="{ aba:'admin' }">
  <div>
    <h1 class="text-xl font-bold">🧪 Simulação (demo)</h1>
    <p class="text-xs text-slate-500">Entre como um usuário de cada papel pra demonstrar o módulo de passagem ao cliente. Você volta pro seu usuário quando quiser.</p>
  </div>

  <a href="{{ route('simulacao.telas') }}" class="card p-3 flex items-center justify-between gap-2 bg-indigo-50 border-indigo-200 hover:bg-indigo-100 transition">
    <span class="text-sm font-semibold text-indigo-800">🖥️ Ver as 4 telas juntas (2 desktop + 2 celular)</span>
    <span class="text-indigo-700 font-bold">→</span>
  </a>

  {{-- abas --}}
  <div class="flex flex-wrap gap-2">
    @foreach(['admin'=>'🛡️ Administrador','operador'=>'💼 Operador','agente'=>'🤝 Agente','dono'=>'🚤 Dono do transporte'] as $k=>$l)
      <button type="button" @click="aba='{{ $k }}'" class="btn btn-sm" :class="aba==='{{ $k }}' ? 'text-white' : 'btn-ghost'" :style="aba==='{{ $k }}' ? 'background:var(--cor1)' : ''">{{ $l }}</button>
    @endforeach
  </div>

  @php
    $tabs = [
      'admin'    => ['🛡️ Administrador', 'Vê tudo: cadastros, caixa geral, acertos e relatórios.', $admins],
      'operador' => ['💼 Operador (balcão)', 'Vende passagens no guichê e dá baixa conforme a permissão.', $operadores],
      'agente'   => ['🤝 Agente externo', 'Vende passagens e acerta com a empresa / donos.', $agentes],
      'dono'     => ['🚤 Dono do transporte', 'Vê o "Meu transporte": vendas e acertos da lancha dele.', $donos],
    ];
  @endphp

  @foreach($tabs as $k=>$t)
  <div x-show="aba==='{{ $k }}'" x-cloak class="card p-4 space-y-3">
    <div>
      <div class="font-bold text-slate-700">{{ $t[0] }}</div>
      <div class="text-xs text-slate-500">{{ $t[1] }}</div>
    </div>
    @if($t[2]->isEmpty())
      <div class="text-sm text-slate-400">Nenhum usuário nesse papel ainda.</div>
    @else
    <form method="post" action="{{ route('simulacao.entrar') }}" class="flex flex-wrap items-end gap-2">
      @csrf
      <div class="flex-1 min-w-[200px]">
        <label class="label !text-xs">Usuário</label>
        <select name="user_id" class="input !min-h-[42px]" data-sim="{{ $k }}" required>
          <option value="">— escolher —</option>
          @foreach($t[2] as $us)<option value="{{ $us->id }}">{{ $us->name }}{{ $us->prefixo ? ' ('.$us->prefixo.')' : '' }}</option>@endforeach
        </select>
      </div>
      <button class="btn btn-accent !min-h-[42px] whitespace-nowrap">▶️ Entrar como</button>
    </form>
    @endif
  </div>
  @endforeach

  <p class="text-[11px] text-slate-400">Pra voltar ao seu usuário, use o aviso de simulação que aparece no topo da tela quando você entra como outro.</p>
</div>

<script>
(function(){
  document.querySelectorAll('select[data-sim]').forEach(function(sel){
    var key = 'sim_user_' + sel.getAttribute('data-sim');
    try { var v = localStorage.getItem(key); if(v){ sel.value = v; } } catch(e){}
    sel.addEventListener('change', function(){ try { localStorage.setItem(key, sel.value); } catch(e){} });
  });
})();
</script>
@endsection
