@extends('layouts.app')
@section('titulo', $u->exists ? 'Editar usuário' : 'Novo usuário')
@section('conteudo')
<form method="post" action="{{ $u->exists ? route('admin.usuarios.update',$u) : route('admin.usuarios.store') }}" class="max-w-lg mx-auto card p-5 space-y-3">
@csrf @if($u->exists) @method('PUT') @endif
<h1 class="text-xl font-bold">{{ $u->exists ? 'Editar usuário' : 'Novo usuário' }}</h1>
<div><label class="label">Nome completo</label><input name="name" class="input" required value="{{ old('name',$u->name) }}" placeholder="Ex.: Maria da Silva"></div>
<div><label class="label">Login (usuário para entrar)</label><input name="login" class="input" required value="{{ old('login',$u->login) }}" placeholder="Ex.: maria" autocapitalize="none" pattern="[a-z0-9._-]+"></div>
<div><label class="label">Código das encomendas (prefixo do número)</label><input name="prefixo" class="input uppercase" required maxlength="8" value="{{ old('prefixo',$u->prefixo) }}" placeholder="Ex.: RE, AG, BAL1" style="text-transform:uppercase" pattern="[A-Za-z0-9]+"><p class="text-xs text-slate-400 mt-0.5">Cada operador precisa de um código único. O número da encomenda sai assim: <b>{{ $u->prefixo ?: 'RE' }}-0001</b>. É o número definitivo (vale offline e no papel).</p></div>
<div><label class="label">Senha {{ $u->exists?'(deixe vazio para manter)':'' }}</label><input name="password" type="password" class="input" {{ $u->exists?'':'required' }} autocomplete="new-password"></div>
<div><label class="label">Tipo de usuário</label>
  <select name="papel" class="input"><option value="">—</option>
    @foreach(\App\Models\User::PAPEIS as $k=>$l)<option value="{{ $k }}" @selected(old('papel',$u->papel)==$k)>{{ $l }}</option>@endforeach
  </select></div>
<div class="grid grid-cols-2 gap-2">
  <div><label class="label">Setor principal</label><input name="setor" list="setores" class="input" value="{{ old('setor',$u->setor) }}" placeholder="padrão no cadastro"><datalist id="setores">@foreach($setores as $s)<option value="{{ $s }}">@endforeach</datalist></div>
  <div><label class="label">Barco principal</label>
    <select name="embarcacao_principal_id" class="input"><option value="">—</option>
      @foreach($embarcacoes as $b)<option value="{{ $b->id }}" @selected(old('embarcacao_principal_id',$u->embarcacao_principal_id)==$b->id)>{{ $b->nome }}</option>@endforeach
    </select></div>
</div>
<p class="text-xs text-slate-400 -mt-1">Setor e barco principais já entram selecionados na tela de Nova encomenda desse operador.</p>
<label class="flex items-start gap-3 py-2 cursor-pointer rounded-xl bg-slate-50 px-3">
  <input type="checkbox" name="checkin_auto" value="1" class="w-5 h-5 rounded mt-0.5" @checked(old('checkin_auto', $u->exists ? $u->checkin_auto : true))>
  <span><span class="font-semibold text-sm">Check-in automático (passagem)</span><br><span class="text-xs text-slate-500">Operador do barco: passageiros já entram com check-in marcado. Desmarque para operador que só vende (check-in validado depois no embarque).</span></span>
</label>
<div class="rounded-xl bg-slate-50 p-3 space-y-1">
  <div class="label">Permissões</div>

  {{-- MÓDULOS e o que o operador pode mexer em cada um --}}
  <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Módulos e ações</div>
  <p class="text-xs text-slate-400 mb-2">Marque o módulo e, dentro dele, o que o operador pode fazer (cadastrar, dar baixa, imprimir).</p>
  <div class="space-y-1.5 mb-3">
    @foreach(\App\Support\Modulos::LISTA as $mk => [$mrot,$mic,$mcor,$mhex])
      @continue($mk === 'avulso')
      @php $algum = $u->exists && $u->temModulo($mk); @endphp
      <div x-data="{ open: {{ $algum ? 'true':'false' }} }" class="border border-slate-200 rounded-xl overflow-hidden">
        <button type="button" @click="open=!open" class="w-full flex items-center gap-2 px-3 py-2.5 text-left hover:bg-slate-50">
          <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $mhex }}"></span>
          <span class="text-base">{{ $mic }}</span>
          <span class="font-semibold text-sm flex-1">{{ $mrot }}</span>
          <svg class="w-4 h-4 opacity-50 transition" :class="open?'rotate-180':''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <div x-show="open" x-cloak class="px-3 pb-3 pt-1 flex flex-wrap gap-x-5 gap-y-2 border-t border-slate-100 bg-slate-50/60">
          @foreach(\App\Models\User::ACOES_MODULO[$mk] ?? [] as $ak => $al)
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input type="checkbox" name="mod_perms[{{ $mk }}][{{ $ak }}]" value="1" class="w-5 h-5 rounded"
                @checked(old('mod_perms.'.$mk.'.'.$ak, $u->exists ? $u->permite($mk,$ak) : false))>
              {{ $al }}
            </label>
          @endforeach
        </div>
      </div>
    @endforeach
  </div>
  {{-- CAIXA e RELATÓRIO do próprio operador (por isso fica junto dos módulos) --}}
  @php $temCaixa = $u->exists && ($u->perm_caixa || $u->perm_avulso || $u->perm_relatorio); @endphp
  <div x-data="{ open: {{ $temCaixa ? 'true':'false' }} }" class="border border-slate-200 rounded-xl overflow-hidden mb-3">
    <button type="button" @click="open=!open" class="w-full flex items-center gap-2 px-3 py-2.5 text-left hover:bg-slate-50">
      <span class="w-2.5 h-2.5 rounded-full" style="background:#475569"></span>
      <span class="text-base">💵</span>
      <span class="font-semibold text-sm flex-1">Caixa e relatório</span>
      <svg class="w-4 h-4 opacity-50 transition" :class="open?'rotate-180':''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
    </button>
    <div x-show="open" x-cloak class="px-3 pb-3 pt-1 space-y-1 border-t border-slate-100 bg-slate-50/60">
      @php $caixaPerms = [
        'perm_caixa'     => ['Ver o caixa', 'Vê o próprio caixa: entradas, saídas e totais do período'],
        'perm_avulso'    => ['Lançar entrada/saída', 'Registrar entrada e saída avulsa no caixa'],
        'perm_relatorio' => ['Meu relatório', 'Relatório só do que ele mesmo fez'],
      ]; @endphp
      @foreach($caixaPerms as $k=>[$l,$d])
        <label class="flex items-start gap-2 py-1 cursor-pointer">
          <input type="checkbox" name="{{ $k }}" value="1" class="w-5 h-5 rounded mt-0.5" @checked(old($k, $u->exists ? $u->$k : true))>
          <span><span class="font-semibold text-sm">{{ $l }}</span><br><span class="text-xs text-slate-500">{{ $d }}</span></span>
        </label>
      @endforeach
    </div>
  </div>

  {{-- ADMINISTRATIVO: só o que é geral (ver de todos) ou específico do admin --}}
  <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1 border-t pt-2">Ver de todos (geral)</div>
  <label class="flex items-start gap-3 py-1.5 cursor-pointer">
    <input type="checkbox" name="caixa_todos" value="1" class="w-5 h-5 rounded mt-0.5" @checked(old('caixa_todos', $u->caixa_todos))>
    <span><span class="font-semibold text-sm">Caixa de todos os operadores</span><br><span class="text-xs text-slate-500">Desmarcado = vê só o caixa dele. (precisa de "Ver o caixa")</span></span>
  </label>
  <label class="flex items-start gap-3 py-1.5 cursor-pointer">
    <input type="checkbox" name="relatorio_todos" value="1" class="w-5 h-5 rounded mt-0.5" @checked(old('relatorio_todos', $u->relatorio_todos))>
    <span><span class="font-semibold text-sm">Relatório de todos</span><br><span class="text-xs text-slate-500">Desmarcado = vê só o relatório dele.</span></span>
  </label>
  <label class="flex items-start gap-3 py-2 cursor-pointer">
    <input type="checkbox" name="perm_financeiro" value="1" class="w-5 h-5 rounded mt-0.5" @checked(old('perm_financeiro', $u->exists ? $u->perm_financeiro : false))>
    <span><span class="font-semibold text-sm">Financeiro</span><br><span class="text-xs text-slate-500">Só entra no Caixa e Relatório, mas vê TODOS os operadores e TODOS os módulos (filtra por módulo, operador e período). Não cadastra.</span></span>
  </label>

  <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1 mt-2 border-t pt-2">Administrativo</div>
  @php $admPerms = [
    'perm_retroativo' => ['Lançar em datas retroativas', 'Cadastrar em dias anteriores (feito na mão e lançado depois). Gerente já tem.'],
    'perm_parcial'    => ['Receber pagamento parcial', 'Pode deixar saldo (valor parcial). Sem isso, só pago total ou a receber. Gerente já tem.'],
    'perm_zerar'      => ['Zerar o sistema', 'Pode apagar TODAS as encomendas/caixa e recomeçar do zero (botão em Cadastros gerais). Use com cuidado.'],
  ]; @endphp
  @foreach($admPerms as $k=>[$l,$d])
    <label class="flex items-start gap-3 py-1.5 cursor-pointer">
      <input type="checkbox" name="{{ $k }}" value="1" class="w-5 h-5 rounded mt-0.5" @checked(old($k, $u->exists ? $u->$k : in_array($k,['perm_parcial'])))>
      <span><span class="font-semibold text-sm">{{ $l }}</span><br><span class="text-xs text-slate-500">{{ $d }}</span></span>
    </label>
  @endforeach
  <label class="flex items-start gap-3 py-2 mt-1 border-t cursor-pointer">
    <input type="checkbox" name="admin" value="1" class="w-5 h-5 rounded mt-0.5" @checked(old('admin', $u->admin))>
    <span><span class="font-semibold text-sm">Administrador da empresa</span><br><span class="text-xs text-slate-500">Tudo acima + usuários, barcos/locais/produtos e dados da empresa</span></span>
  </label>
  @if($u->exists)<label class="flex items-center gap-3 py-1 border-t pt-2"><input type="checkbox" name="ativo" value="1" class="w-5 h-5 rounded" @checked(old('ativo',$u->ativo))> Usuário ativo</label>@endif
</div>

{{-- ESCOPO: o que o operador pode usar no cadastro --}}
@php
  $cidadesSel = old('loc_permitidas', $u->loc_permitidas ?? []);
  $setoresSel = old('setores_permitidos', $u->setores_permitidos ?? []);
  $transpSel  = array_map('strval', old('transportes_permitidos', $u->transportes_permitidos ?? []));
@endphp
<div class="rounded-xl bg-slate-50 p-3 space-y-3">
  <div><div class="label !mb-0">O que ele pode usar no cadastro</div>
    <p class="text-xs text-slate-500">Marque para limitar. Nada marcado = tudo liberado.</p></div>

  <div>
    <div class="text-xs font-bold text-slate-600 uppercase mb-1">Cidades (origem/destino)</div>
    @forelse($cidades as $c)
      <label class="inline-flex items-center gap-1.5 mr-3 mb-1 text-sm"><input type="checkbox" name="loc_permitidas[]" value="{{ $c }}" class="w-4 h-4 rounded" @checked(in_array($c,$cidadesSel))> {{ $c }}</label>
    @empty <span class="text-xs text-slate-400">Cadastre linhas para ter cidades.</span> @endforelse
  </div>

  <div>
    <div class="text-xs font-bold text-slate-600 uppercase mb-1">Setores</div>
    @forelse($setores as $s)
      <label class="inline-flex items-center gap-1.5 mr-3 mb-1 text-sm"><input type="checkbox" name="setores_permitidos[]" value="{{ $s }}" class="w-4 h-4 rounded" @checked(in_array($s,$setoresSel))> {{ $s }}</label>
    @empty <span class="text-xs text-slate-400">Nenhum setor.</span> @endforelse
  </div>

  <div>
    <div class="text-xs font-bold text-slate-600 uppercase mb-1">Transportes</div>
    @forelse($embarcacoes as $b)
      <label class="inline-flex items-center gap-1.5 mr-3 mb-1 text-sm"><input type="checkbox" name="transportes_permitidos[]" value="{{ $b->id }}" class="w-4 h-4 rounded" @checked(in_array((string)$b->id,$transpSel))> {{ $b->nome }}</label>
    @empty <span class="text-xs text-slate-400">Nenhum transporte.</span> @endforelse
  </div>
</div>

<div class="flex gap-2"><a href="{{ route('admin.usuarios.index') }}" class="btn btn-ghost flex-1">Cancelar</a><button class="btn btn-accent flex-1">Salvar</button></div>
</form>
@endsection
