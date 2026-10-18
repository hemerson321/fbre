@extends('layouts.app')
@section('titulo','Pré-cadastros')
@section('conteudo')
<div class="max-w-4xl mx-auto space-y-4">

  <div class="flex items-center justify-between gap-2 flex-wrap">
    <div>
      <h1 class="text-xl font-bold">Fila de pré-cadastros</h1>
      <p class="text-xs text-slate-500">Clientes que já preencheram pelo QR. Chame pelo <b>código</b> ou pelo <b>nome</b> e finalize.</p>
    </div>
    <a href="{{ route('pre.qr') }}" class="btn btn-accent btn-sm">🏷️ QR e cartaz</a>
  </div>

  <form method="get" class="card p-3">
    <div class="relative">
      <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔎</span>
      <input name="busca" value="{{ $busca }}" class="input !pl-9" placeholder="Código ou nome do cliente..." autofocus>
    </div>
  </form>

  @if($pendentes->isEmpty())
    <div class="card p-10 text-center text-slate-400">
      <div class="text-4xl mb-2">🪧</div>
      Ninguém na fila de pré-cadastro {{ $busca ? 'com esse termo' : 'agora' }}.
      <div class="text-xs mt-2">O cliente escaneia o cartaz (QR) e aparece aqui.</div>
    </div>
  @else
    <div class="space-y-2">
      @foreach($pendentes as $p)
        <div class="card p-3 flex items-center gap-3">
          <div class="shrink-0 w-20 text-center">
            <div class="text-2xl font-black tracking-widest" style="color:var(--cor1)">{{ $p->codigo }}</div>
            <div class="text-[10px] text-slate-400">{{ $p->created_at->diffForHumans(null, true) }}</div>
          </div>
          <div class="min-w-0 flex-1 border-l pl-3">
            <div class="font-bold truncate">{{ $p->remetente_nome }} <span class="text-slate-400 font-normal text-sm">→ {{ $p->destinatario_nome }}</span></div>
            <div class="text-sm text-slate-600 truncate">{{ $p->origem }} → {{ $p->destino }}</div>
            @if($p->resumoItens())<div class="text-xs text-slate-400 truncate">{{ $p->resumoItens() }}</div>@endif
          </div>
          <div class="flex flex-col gap-1 shrink-0">
            <a href="{{ route('encomendas.create', ['pre' => $p->id]) }}" class="btn btn-primary btn-sm">Atender →</a>
            <form method="post" action="{{ route('pre.cancelar', $p) }}" onsubmit="return confirm('Remover este pré-cadastro da fila?')">@csrf
              <button class="w-full text-[11px] text-red-500 hover:underline">remover</button>
            </form>
          </div>
        </div>
      @endforeach
    </div>
  @endif
</div>
@endsection
