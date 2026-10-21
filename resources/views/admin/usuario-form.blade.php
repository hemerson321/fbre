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
<div class="rounded-xl bg-slate-50 p-3 space-y-1">
  <div class="label">Permissões</div>

  {{-- MÓDULOS que o operador pode cadastrar/vender --}}
  <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Módulos liberados</div>
  <p class="text-xs text-slate-400 mb-2">Marque o que este operador pode cadastrar. (Encomendas usa a permissão "Cadastrar encomendas" abaixo.)</p>
  <div class="grid grid-cols-2 gap-x-4 gap-y-1 mb-3">
    @php $mods = [
      'perm_passagem' => 'Passagem',
      'perm_frete' => 'Frete',
      'perm_camarote' => 'Camarote',
      'perm_lanchonete' => 'Lanchonete',
      'perm_wifi' => 'WiFi',
      'perm_avulso' => 'Avulso (entrada/saída)',
      'perm_recibo' => 'Recibo',
    ]; @endphp
    @foreach($mods as $k=>$l)
      <label class="flex items-center gap-2 py-1 cursor-pointer text-sm">
        <input type="checkbox" name="{{ $k }}" value="1" class="w-5 h-5 rounded" @checked(old($k, $u->exists ? $u->$k : false))>
        <span class="font-medium">{{ $l }}</span>
      </label>
    @endforeach
  </div>
  <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1 border-t pt-2">Permissões gerais</div>
  @php $perms = [
    'perm_cadastra' => ['Cadastrar encomendas', 'Tela "Nova encomenda" e impressão do comprovante no cadastro'],
    'perm_filtro'   => ['Ver encomendas / filtro', 'Lista com filtros, totais, protocolo e listagem'],
    'perm_baixa'    => ['Dar baixa', 'Registrar pagamento e confirmar entrega'],
    'perm_imprimir' => ['Reimprimir', 'Comprovantes e etiquetas de encomendas já salvas'],
    'perm_caixa'    => ['Ver caixa geral', 'Totais por operador e meio de pagamento de toda a empresa'],
    'perm_relatorio'=> ['Meu relatório', 'Relatório só do que o próprio usuário fez'],
    'perm_retroativo'=> ['Lançar em datas retroativas', 'Cadastrar em dias anteriores (feito na mão e lançado depois). Gerente já tem.'],
    'perm_parcial'  => ['Receber pagamento parcial', 'Pode deixar saldo (valor parcial). Sem isso, só pago total ou a receber. Gerente já tem.'],
    'perm_zerar'    => ['Zerar o sistema', 'Pode apagar TODAS as encomendas/caixa e recomeçar do zero (botão em Cadastros gerais). Use com cuidado.'],
  ]; @endphp
  @foreach($perms as $k=>[$l,$d])
    <label class="flex items-start gap-3 py-1.5 cursor-pointer">
      <input type="checkbox" name="{{ $k }}" value="1" class="w-5 h-5 rounded mt-0.5" @checked(old($k, $u->exists ? $u->$k : in_array($k,['perm_cadastra','perm_filtro','perm_imprimir','perm_relatorio','perm_parcial'])))>
      <span><span class="font-semibold text-sm">{{ $l }}</span><br><span class="text-xs text-slate-500">{{ $d }}</span></span>
    </label>
  @endforeach
  <label class="flex items-start gap-3 py-1.5 cursor-pointer">
    <input type="checkbox" name="caixa_todos" value="1" class="w-5 h-5 rounded mt-0.5" @checked(old('caixa_todos', $u->caixa_todos))>
    <span><span class="font-semibold text-sm">Caixa de todos os operadores</span><br><span class="text-xs text-slate-500">Desmarcado = vê só o caixa dele. (precisa de "Ver caixa geral")</span></span>
  </label>
  <label class="flex items-start gap-3 py-1.5 cursor-pointer">
    <input type="checkbox" name="relatorio_todos" value="1" class="w-5 h-5 rounded mt-0.5" @checked(old('relatorio_todos', $u->relatorio_todos))>
    <span><span class="font-semibold text-sm">Relatório de todos</span><br><span class="text-xs text-slate-500">Desmarcado = vê só o relatório dele.</span></span>
  </label>
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
