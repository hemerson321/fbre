@extends('layouts.app')
@section('titulo','Meus filiados')
@section('conteudo')
<div class="max-w-2xl mx-auto space-y-4">
  <div><h1 class="text-xl font-bold">Meus filiados</h1>
    <p class="text-xs text-slate-500">Seus vendedores. Eles vendem passagem por você; aqui você faz o acerto com cada um.</p></div>

  {{-- novo filiado --}}
  <form method="post" action="{{ route('filiados.salvar') }}" class="card p-4 space-y-3" x-data="{modo:'cheio'}">@csrf
    <div class="font-semibold text-sm">Novo filiado</div>
    <div class="grid sm:grid-cols-2 gap-3">
      <div><label class="label">Nome</label><input name="name" class="input" required></div>
      <div><label class="label">Login</label><input name="login" class="input" required autocapitalize="none" pattern="[a-z0-9._-]+"></div>
      <div><label class="label">Senha</label><input name="password" type="password" class="input" required minlength="4"></div>
      <div><label class="label">Código (prefixo do nº)</label><input name="prefixo" class="input uppercase" required maxlength="8" placeholder="Ex.: FIL1" style="text-transform:uppercase" pattern="[A-Za-z0-9]+"></div>
      <div><label class="label">Acerto</label>
        <select name="modo_acerto" x-model="modo" class="input"><option value="cheio">Valor cheio</option><option value="comissao">Comissão (%)</option></select></div>
      <div x-show="modo==='comissao'" x-cloak><label class="label">Comissão %</label><input name="comissao_pct" type="number" min="0" max="100" class="input text-right" value="0"></div>
    </div>
    <button class="btn btn-accent">Criar filiado</button>
  </form>

  {{-- lista + acerto --}}
  @forelse($linhas as $l)
    <div class="card p-4 flex items-center gap-3 {{ $l['fil']->ativo?'':'opacity-60' }}">
      <div class="flex-1">
        <div class="font-semibold">{{ $l['fil']->name }} <span class="text-xs text-slate-400">({{ $l['fil']->login }})</span></div>
        <div class="text-xs text-slate-500">
          <span class="font-bold {{ $l['total']>0 ? 'text-red-600':'text-emerald-600' }}">Pendente: R$ {{ number_format($l['total'],2,',','.') }}</span>
          <span class="ml-1">({{ $l['qtd'] }} passagens)</span>
          · {{ ($l['fil']->modo_acerto==='comissao') ? ('comissão '.$l['fil']->comissao_pct.'%') : 'valor cheio' }}
        </div>
      </div>
      @if($l['total']>0)
        <form method="post" action="{{ route('filiados.baixa',$l['fil']) }}" onsubmit="return confirm('Dar baixa no acerto de {{ $l['fil']->name }}?')">@csrf
          <button class="btn btn-accent btn-sm">Dar baixa</button>
        </form>
      @else
        <span class="text-emerald-600 text-sm font-semibold">✓ em dia</span>
      @endif
    </div>
  @empty
    <div class="card p-6 text-center text-slate-400 text-sm">Nenhum filiado ainda. Crie o primeiro acima.</div>
  @endforelse
</div>
@endsection
