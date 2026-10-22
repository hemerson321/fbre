@extends('layouts.app')
@section('titulo','Acertos (revendedores)')
@section('conteudo')
<div class="max-w-2xl mx-auto space-y-3">
  <div><h1 class="text-xl font-bold">Acertos com revendedores</h1>
    <p class="text-xs text-slate-500">Quanto cada revendedor deve repassar das passagens vendidas. "Dar baixa" marca como acertado.</p></div>

  @forelse($linhas as $l)
    <div class="card p-4 flex items-center gap-3">
      <div class="flex-1">
        <div class="font-semibold">{{ $l['rev']->name }}</div>
        <div class="text-xs text-slate-500">
          <span class="font-bold {{ $l['total']>0 ? 'text-red-600':'text-emerald-600' }}">Pendente: R$ {{ number_format($l['total'],2,',','.') }}</span>
          <span class="ml-1">({{ $l['qtd'] }} passagens)</span>
          · já acertado R$ {{ number_format($l['acertado'],2,',','.') }}
        </div>
      </div>
      @if($l['total']>0)
        <form method="post" action="{{ route('admin.acertos.baixa',$l['rev']) }}" onsubmit="return confirm('Dar baixa no acerto de {{ $l['rev']->name }}? Marca {{ $l['qtd'] }} passagens como acertadas.')">@csrf
          <button class="btn btn-accent btn-sm">Dar baixa</button>
        </form>
      @else
        <span class="text-emerald-600 text-sm font-semibold">✓ em dia</span>
      @endif
    </div>
  @empty
    <div class="card p-6 text-center text-slate-400 text-sm">Nenhum revendedor cadastrado ainda. Marque um usuário como revendedor em Usuários.</div>
  @endforelse
</div>
@endsection
