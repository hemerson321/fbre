@extends('layouts.app')
@section('titulo', $u->exists ? 'Editar usuário' : 'Novo usuário')
@section('conteudo')
<form method="post" action="{{ $u->exists ? route('admin.usuarios.update',$u) : route('admin.usuarios.store') }}" class="max-w-lg mx-auto card p-5 space-y-3">
@csrf @if($u->exists) @method('PUT') @endif
<h1 class="text-xl font-bold">{{ $u->exists ? 'Editar usuário' : 'Novo usuário' }}</h1>
<div><label class="label">Nome completo</label><input name="name" class="input" required value="{{ old('name',$u->name) }}" placeholder="Ex.: Maria da Silva"></div>
<div><label class="label">Login (usuário para entrar)</label><input name="login" class="input" required value="{{ old('login',$u->login) }}" placeholder="Ex.: maria" autocapitalize="none" pattern="[a-z0-9._-]+"></div>
<div><label class="label">Senha {{ $u->exists?'(deixe vazio para manter)':'' }}</label><input name="password" type="password" class="input" {{ $u->exists?'':'required' }} autocomplete="new-password"></div>
<div><label class="label">Setor</label><input name="setor" list="setores" class="input" value="{{ old('setor',$u->setor) }}"><datalist id="setores">@foreach($setores as $s)<option value="{{ $s }}">@endforeach</datalist></div>
<div class="rounded-xl bg-slate-50 p-3 space-y-1">
  <div class="label">Permissões</div>
  @php $perms = [
    'perm_cadastra' => ['Cadastrar encomendas', 'Tela "Nova encomenda" e impressão do comprovante no cadastro'],
    'perm_filtro'   => ['Ver encomendas / filtro', 'Lista com filtros, totais, protocolo e listagem'],
    'perm_baixa'    => ['Dar baixa', 'Registrar pagamento e confirmar entrega'],
    'perm_imprimir' => ['Reimprimir', 'Comprovantes e etiquetas de encomendas já salvas'],
    'perm_caixa'    => ['Ver caixa geral', 'Totais por operador e meio de pagamento de toda a empresa'],
    'perm_relatorio'=> ['Meu relatório', 'Relatório só do que o próprio usuário fez'],
  ]; @endphp
  @foreach($perms as $k=>[$l,$d])
    <label class="flex items-start gap-3 py-1.5 cursor-pointer">
      <input type="checkbox" name="{{ $k }}" value="1" class="w-5 h-5 rounded mt-0.5" @checked(old($k, $u->exists ? $u->$k : in_array($k,['perm_cadastra','perm_filtro','perm_imprimir','perm_relatorio'])))>
      <span><span class="font-semibold text-sm">{{ $l }}</span><br><span class="text-xs text-slate-500">{{ $d }}</span></span>
    </label>
  @endforeach
  <label class="flex items-start gap-3 py-2 mt-1 border-t cursor-pointer">
    <input type="checkbox" name="admin" value="1" class="w-5 h-5 rounded mt-0.5" @checked(old('admin', $u->admin))>
    <span><span class="font-semibold text-sm">Administrador da empresa</span><br><span class="text-xs text-slate-500">Tudo acima + usuários, barcos/locais/produtos e dados da empresa</span></span>
  </label>
  @if($u->exists)<label class="flex items-center gap-3 py-1 border-t pt-2"><input type="checkbox" name="ativo" value="1" class="w-5 h-5 rounded" @checked(old('ativo',$u->ativo))> Usuário ativo</label>@endif
</div>
<div class="flex gap-2"><a href="{{ route('admin.usuarios.index') }}" class="btn btn-ghost flex-1">Cancelar</a><button class="btn btn-accent flex-1">Salvar</button></div>
</form>
@endsection
