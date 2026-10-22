@extends('layouts.app')
@section('titulo','Cadastros')
@section('conteudo')
<div class="max-w-5xl mx-auto space-y-4">
<h1 class="text-xl font-bold">Barcos, setores e produtos</h1>
<p class="text-xs text-slate-500 -mt-2">As localidades de origem/destino vêm das paradas das linhas (abaixo) — não há lista separada.</p>
@if(session('token'))<div class="card p-4 bg-amber-50 border-amber-200"><b>Token da estação (copie agora, não será mostrado de novo):</b><code class="block mt-1 break-all text-sm">{{ session('token') }}</code></div>@endif
<div class="grid md:grid-cols-2 gap-4">

  {{-- TRANSPORTES / EMBARCAÇÕES com config de poltrona --}}
  <div class="card p-4 space-y-2 md:col-span-2">
    <h2 class="font-bold text-sm text-slate-500 uppercase">Transportes / embarcações</h2>
    <form method="post" action="{{ route('admin.cadastros.store','embarcacoes') }}" class="flex gap-2">@csrf
      <input name="nome" class="input !min-h-[42px]" placeholder="Nome do barco/lancha" required>
      <button class="btn btn-accent btn-sm">+ Adicionar</button>
    </form>
    <div class="space-y-2">
      @forelse($embarcacoes as $e)
        <form method="post" action="{{ route('admin.embarcacoes.salvar',$e) }}" class="rounded-xl border border-slate-200 p-3 {{ $e->ativo?'':'opacity-50' }}" x-data="{pol: {{ $e->usa_poltrona?'true':'false' }} }">@csrf
          <div class="flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[160px]"><label class="label">Nome</label><input name="nome" class="input !min-h-[40px]" value="{{ $e->nome }}" required></div>
            <label class="flex items-center gap-2 text-sm font-medium pb-2 cursor-pointer">
              <input type="checkbox" name="usa_poltrona" value="1" class="w-5 h-5 rounded" x-model="pol" @checked($e->usa_poltrona)> 🪑 Usa poltrona
            </label>
            <div x-show="pol" x-cloak><label class="label">Qtd poltronas</label><input name="poltronas_qtd" type="number" min="0" max="500" class="input !min-h-[40px] !w-28 text-center" value="{{ $e->poltronas_qtd ?: 0 }}"></div>
            <div x-show="pol" x-cloak><label class="label">Assentos/fileira</label>
              <select name="poltronas_colunas" class="input !min-h-[40px] !w-32">
                @foreach([2=>'2 (1+1)',3=>'3 (2+1)',4=>'4 (2+2)',5=>'5 (3+2)',6=>'6 (3+3)'] as $cn=>$cl)<option value="{{ $cn }}" @selected(($e->poltronas_colunas ?: 4)==$cn)>{{ $cl }}</option>@endforeach
              </select>
            </div>
            <button class="btn btn-primary btn-sm">Salvar</button>
            <a href="#" onclick="event.preventDefault(); if(confirm('Remover embarcação?')) this.closest('form').nextElementSibling.submit();" class="text-red-500 text-xs pb-2">remover</a>
          </div>
        </form>
        <form method="post" action="{{ route('admin.cadastros.destroy',['embarcacoes',$e->id]) }}" class="hidden">@csrf @method('DELETE')</form>
      @empty <div class="text-slate-400 text-sm">Nenhuma embarcação.</div> @endforelse
    </div>
  </div>
@foreach([['setores','Setores',$setores],['produtos','Produtos (catálogo)',$produtos]] as [$tipo,$titulo,$lista])
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
  <h2 class="font-bold text-sm text-slate-500 uppercase">Tipos de passageiro (passagem)</h2>
  <p class="text-xs text-slate-500">Ex.: Adulto 100%, Criança meia 50%, Idoso 50%, PCD. Cada tipo é por <b>porcentagem</b> da tarifa OU <b>valor fixo</b>.</p>
  <form method="post" action="{{ route('admin.tipos-passageiro.salvar') }}" class="flex flex-wrap gap-2 items-end border-b pb-3" x-data="{modo:'porcentagem'}">@csrf
    <input name="nome" class="input !min-h-[40px] flex-1 min-w-[140px]" placeholder="Nome (ex.: Criança meia)" required maxlength="60">
    <select name="modo" x-model="modo" class="input !min-h-[40px] !w-40"><option value="porcentagem">Porcentagem</option><option value="valor">Valor fixo</option></select>
    <div x-show="modo==='porcentagem'" class="flex items-center gap-1"><input name="percentual" type="number" min="0" max="100" value="100" class="input !min-h-[40px] !w-24 text-right"><span class="text-xs">%</span></div>
    <div x-show="modo==='valor'" x-cloak><input name="valor_fixo" type="number" step="0.01" min="0" class="input !min-h-[40px] !w-28 text-right" placeholder="R$"></div>
    <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" name="paga_taxa" value="1" checked class="w-5 h-5 rounded"> paga taxa</label>
    <button class="btn btn-accent btn-sm">+ Adicionar</button>
  </form>
  <div class="space-y-2">
    @forelse($tiposPassageiro as $t)
      <form method="post" action="{{ route('admin.tipos-passageiro.salvar',$t) }}" class="flex flex-wrap items-end gap-2 {{ $t->ativo?'':'opacity-50' }}" x-data="{modo:'{{ $t->modo }}'}">@csrf
        <div class="flex-1 min-w-[140px]"><input name="nome" class="input !min-h-[38px]" value="{{ $t->nome }}" required></div>
        <select name="modo" x-model="modo" class="input !min-h-[38px] !w-36"><option value="porcentagem" @selected($t->modo==='porcentagem')>Porcentagem</option><option value="valor" @selected($t->modo==='valor')>Valor fixo</option></select>
        <div x-show="modo==='porcentagem'" class="flex items-center gap-1"><input name="percentual" type="number" min="0" max="100" value="{{ $t->percentual }}" class="input !min-h-[38px] !w-20 text-right"><span class="text-xs">%</span></div>
        <div x-show="modo==='valor'" x-cloak><input name="valor_fixo" type="number" step="0.01" min="0" value="{{ number_format($t->valor_fixo,2,'.','') }}" class="input !min-h-[38px] !w-24 text-right"></div>
        <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" name="paga_taxa" value="1" @checked($t->paga_taxa) class="w-5 h-5 rounded"> taxa</label>
        <button class="btn btn-primary btn-sm">Salvar</button>
        <a href="#" onclick="event.preventDefault(); if(confirm('Remover tipo?')) this.closest('form').nextElementSibling.submit();" class="text-red-500 text-xs pb-2">remover</a>
      </form>
      <form method="post" action="{{ route('admin.cadastros.destroy',['tipos_passageiro',$t->id]) }}" class="hidden">@csrf @method('DELETE')</form>
    @empty <div class="text-slate-400 text-sm">Nenhum tipo ainda.</div> @endforelse
  </div>
</div>

<div class="card p-4 space-y-2 md:col-span-2">
  <h2 class="font-bold text-sm text-slate-500 uppercase">Categorias do caixa (entrada/saída avulsa)</h2>
  <p class="text-xs text-slate-500">O operador escolhe uma destas ao lançar entrada/saída no caixa. Só o admin cria aqui.</p>
  <form method="post" action="{{ route('admin.cadastros.store','categorias') }}" class="flex flex-wrap gap-2 items-end">@csrf
    <input name="nome" class="input !min-h-[42px] flex-1 min-w-[160px]" placeholder="Nome da categoria (ex.: Combustível)" required maxlength="60">
    <select name="tipo" class="input !min-h-[42px] !w-44">
      <option value="ambos">Entrada e saída</option>
      <option value="entrada">Só entrada</option>
      <option value="saida">Só saída</option>
    </select>
    <button class="btn btn-accent btn-sm">+ Adicionar</button>
  </form>
  <div class="flex flex-wrap gap-2 pt-1">
    @forelse($categorias as $c)
      <span class="chip bg-slate-100 text-slate-700 flex items-center gap-2">
        {{ $c->nome }}
        <span class="text-[10px] uppercase font-bold {{ $c->tipo==='entrada'?'text-emerald-600':($c->tipo==='saida'?'text-red-600':'text-slate-400') }}">{{ \App\Models\AvulsoCategoria::TIPOS[$c->tipo] ?? $c->tipo }}</span>
        <form method="post" action="{{ route('admin.cadastros.destroy',['categorias',$c->id]) }}" onsubmit="return confirm('Remover categoria?')">@csrf @method('DELETE')<button class="text-red-500 text-xs">×</button></form>
      </span>
    @empty <span class="text-slate-400 text-sm">Nenhuma categoria ainda.</span> @endforelse
  </div>
</div>

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
