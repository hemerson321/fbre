@extends('layouts.app')
@section('titulo','Cadastros')
@section('conteudo')
<div class="max-w-5xl mx-auto space-y-4">
<h1 class="text-xl font-bold">Barcos, setores e produtos</h1>
<p class="text-xs text-slate-500 -mt-2">As localidades de origem/destino vêm das paradas das linhas (abaixo) — não há lista separada.</p>
@if(session('token'))<div class="card p-4 bg-amber-50 border-amber-200"><b>Token da estação (copie agora, não será mostrado de novo):</b><code class="block mt-1 break-all text-sm">{{ session('token') }}</code></div>@endif
<div class="grid md:grid-cols-2 gap-4">
@foreach([['embarcacoes','Transportes / embarcações',$embarcacoes],['setores','Setores',$setores],['produtos','Produtos (catálogo)',$produtos]] as [$tipo,$titulo,$lista])
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
  <h2 class="font-bold text-sm text-slate-500 uppercase">Estações de impressão direta (Agente de Impressão)</h2>
  <p class="text-xs text-slate-500">Cada computador com impressora térmica recebe um token. Instale o Agente de Impressão no PC e cole o token.</p>
  <form method="post" action="{{ route('admin.estacoes.store') }}" class="flex gap-2">@csrf<input name="nome" class="input !min-h-[42px]" placeholder="Ex.: Balcão 1" required><button class="btn btn-accent btn-sm">Gerar token</button></form>
  <div class="divide-y text-sm">
    @forelse($estacoes as $e)
      <div class="py-2" x-data="{ mostra:false }">
        <div class="flex items-center gap-2">
          <form method="post" action="{{ route('admin.estacoes.renomear',$e) }}" class="flex items-center gap-2 flex-1">@csrf
            <input name="nome" value="{{ $e->nome }}" class="input !min-h-[38px] !py-1 flex-1" maxlength="60" required>
            <select name="colunas" class="input !min-h-[38px] !py-1 !w-24" title="Largura do papel da impressora">
              <option value="48" @selected(($e->colunas ?? 48)==48)>80mm</option>
              <option value="32" @selected(($e->colunas ?? 48)==32)>58mm</option>
            </select>
            <button class="btn btn-ghost btn-sm" title="Salvar">Salvar</button>
          </form>
          <form method="post" action="{{ route('admin.cadastros.destroy',['estacoes',$e->id]) }}" onsubmit="return confirm('Excluir esta estação? O token vai parar de funcionar neste PC.')">@csrf @method('DELETE')<button class="text-red-500 text-xs px-2">excluir</button></form>
        </div>
        <div class="flex items-center gap-2 mt-1 text-xs flex-wrap">
          <span class="text-slate-400">{{ $e->visto_em ? 'visto '.$e->visto_em->diffForHumans() : 'nunca conectou' }}</span>
          <span class="text-slate-300">·</span>
          <span class="text-slate-500">Token:</span>
          <code class="bg-slate-100 px-1.5 py-0.5 rounded break-all" x-text="mostra ? @js($e->token) : '••••••••••••••••'"></code>
          <button type="button" class="text-blue-600 hover:underline" @click="mostra=!mostra" x-text="mostra ? 'ocultar' : 'mostrar'"></button>
          <button type="button" class="text-blue-600 hover:underline" @click="navigator.clipboard.writeText(@js($e->token)); $el.textContent='copiado!'; setTimeout(()=>$el.textContent='copiar',1500)">copiar</button>
        </div>
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

@if(auth()->user()->podeZerar())
<div class="card p-4 md:col-span-2 border border-red-200 bg-red-50" x-data="{ open:false, txt:'' }">
  <h2 class="font-bold text-sm text-red-600 uppercase">Zona de perigo — Começar do zero</h2>
  <p class="text-xs text-red-500 mt-1">Apaga <b>todas as encomendas, pagamentos, caixa, impressões e pré-cadastros</b>, e a numeração volta para <b>000001</b>. Os cadastros (barcos, setores, produtos, rotas, usuários, estações) são mantidos. <b>Não dá para desfazer.</b></p>
  <button type="button" class="btn btn-sm mt-2" style="background:#dc2626;color:#fff" @click="open=true; txt=''">Zerar dados do sistema</button>

  <div x-show="open" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl p-5 max-w-sm w-full space-y-3" @click.outside="open=false">
      <h3 class="font-bold text-red-600">Zerar o sistema?</h3>
      <p class="text-sm text-slate-600">Isso apaga todas as encomendas e o caixa, sem volta. Para confirmar, digite <b>ZERAR</b> abaixo.</p>
      <input x-model="txt" class="input !min-h-[42px]" placeholder="Digite ZERAR" autocomplete="off">
      <div class="flex justify-end gap-2 pt-1">
        <button type="button" class="btn btn-ghost btn-sm" @click="open=false">Cancelar</button>
        <form method="post" action="{{ route('admin.zerar') }}">@csrf
          <input type="hidden" name="confirmacao" :value="txt">
          <button class="btn btn-sm" style="background:#dc2626;color:#fff" :disabled="txt!=='ZERAR'" :class="txt!=='ZERAR' ? 'opacity-40' : ''">Zerar agora</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endif
</div></div>
@endsection
