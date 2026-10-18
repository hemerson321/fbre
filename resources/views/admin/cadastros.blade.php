@extends('layouts.app')
@section('titulo','Cadastros')
@section('conteudo')
<div class="max-w-5xl mx-auto space-y-4">
<h1 class="text-xl font-bold">Barcos, locais, setores e produtos</h1>
@if(session('token'))<div class="card p-4 bg-amber-50 border-amber-200"><b>Token da estação (copie agora, não será mostrado de novo):</b><code class="block mt-1 break-all text-sm">{{ session('token') }}</code></div>@endif
<div class="grid md:grid-cols-2 gap-4">
@foreach([['embarcacoes','Transportes / embarcações',$embarcacoes],['localidades','Localidades (origem/destino)',$localidades],['setores','Setores',$setores],['produtos','Produtos (catálogo)',$produtos]] as [$tipo,$titulo,$lista])
  <div class="card p-4 space-y-2">
    <h2 class="font-bold text-sm text-slate-500 uppercase">{{ $titulo }}</h2>
    <form method="post" action="{{ route('admin.cadastros.store',$tipo) }}" class="flex gap-2">@csrf
      <input name="nome" class="input !min-h-[42px]" placeholder="Nome" required>
      @if($tipo==='produtos')<input name="valor_padrao" type="number" step="0.01" min="0" class="input !min-h-[42px] !w-28 text-right" placeholder="Valor">@endif
      <button class="btn btn-accent btn-sm">+</button></form>
    <div class="divide-y text-sm">
      @forelse($lista as $r)
        <div class="flex items-center justify-between py-1.5 {{ isset($r->ativo) && !$r->ativo ? 'opacity-40 line-through' : '' }}">
          <span>{{ $r->nome }} @if($tipo==='produtos')<span class="text-slate-400">R$ {{ number_format($r->valor_padrao,2,',','.') }}</span>@endif</span>
          @if(!isset($r->ativo) || $r->ativo)
          <form method="post" action="{{ route('admin.cadastros.destroy',[$tipo,$r->id]) }}" onsubmit="return confirm('Remover?')">@csrf @method('DELETE')<button class="text-red-500 text-xs">remover</button></form>
          @endif
        </div>
      @empty <div class="py-3 text-slate-400">Nenhum.</div> @endforelse
    </div>
  </div>
@endforeach

<div class="card p-4 space-y-2 md:col-span-2">
  <h2 class="font-bold text-sm text-slate-500 uppercase">Estações de impressão direta (FBRE Print Agent)</h2>
  <p class="text-xs text-slate-500">Cada computador com impressora térmica recebe um token. Instale o FBRE Print Agent no PC e cole o token.</p>
  <form method="post" action="{{ route('admin.estacoes.store') }}" class="flex gap-2">@csrf<input name="nome" class="input !min-h-[42px]" placeholder="Ex.: Balcão 1" required><button class="btn btn-accent btn-sm">Gerar token</button></form>
  <div class="divide-y text-sm">
    @forelse($estacoes as $e)
      <div class="flex items-center justify-between py-1.5 {{ $e->ativo?'':'opacity-40' }}">
        <span>{{ $e->nome }} <span class="text-xs text-slate-400">{{ $e->visto_em ? 'visto '.$e->visto_em->diffForHumans() : 'nunca conectou' }}</span></span>
        @if($e->ativo)<form method="post" action="{{ route('admin.cadastros.destroy',['estacoes',$e->id]) }}">@csrf @method('DELETE')<button class="text-red-500 text-xs">desativar</button></form>@endif
      </div>
    @empty <div class="py-3 text-slate-400">Nenhuma estação.</div> @endforelse
  </div>
</div>

@if(\App\Support\Tenant::empresa()->temRecurso('viagens'))
<div class="card p-4 md:col-span-2" x-data="{ aberta:null }">
  <div class="flex items-center justify-between mb-2">
    <div>
      <h2 class="font-bold text-sm text-slate-500 uppercase">Rotas e calendário</h2>
      <p class="text-xs text-slate-500">A rota guarda as paradas (cidade + dia da semana + hora). As viagens são criadas sozinhas a partir disso. Serve também para fretes e passagens no futuro.</p>
    </div>
    <button class="btn btn-accent btn-sm" @click="aberta = aberta==='nova' ? null : 'nova'">➕ Nova rota</button>
  </div>

  <div x-show="aberta==='nova'" x-cloak class="rounded-xl bg-slate-50 border border-slate-200 p-3 mb-3">
    @include('admin._rota_form', ['rota' => null, 'localidades' => $localidadesNomes])
  </div>

  <div class="space-y-2">
  @forelse($rotas as $r)
    <div class="rounded-xl border border-slate-200 p-3 {{ $r->ativo?'':'opacity-60' }}">
      <div class="flex items-start justify-between gap-2">
        <div>
          <div class="font-bold">{{ $r->nome }} @if(!$r->ativo)<span class="chip st-cinza">inativa</span>@endif</div>
          <div class="text-xs text-slate-600 mt-0.5">{{ $r->resumoCalendario() }}</div>
          @if($r->embarcacao)<div class="text-[11px] text-slate-400">Embarcação: {{ $r->embarcacao->nome }}</div>@endif
        </div>
        <div class="flex gap-2 shrink-0">
          <button class="btn btn-ghost btn-sm" @click="aberta = aberta==={{ $r->id }} ? null : {{ $r->id }}">Editar</button>
          <form method="post" action="{{ route('admin.rotas.toggle',$r) }}">@csrf<button class="btn btn-sm {{ $r->ativo?'btn-ghost':'btn-primary' }}">{{ $r->ativo?'Desativar':'Ativar' }}</button></form>
        </div>
      </div>
      <div x-show="aberta==={{ $r->id }}" x-cloak class="mt-3 pt-3 border-t">
        @include('admin._rota_form', ['rota' => $r, 'localidades' => $localidadesNomes])
      </div>
    </div>
  @empty
    <div class="py-4 text-center text-slate-400 text-sm">Nenhuma rota. Crie a primeira (ex.: Manaus seg → Coari ter → Tefé sex).</div>
  @endforelse
  </div>
</div>
@endif
</div></div>
@endsection
