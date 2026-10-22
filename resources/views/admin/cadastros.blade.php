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

<div class="card p-4 space-y-2 md:col-span-2" x-data="tiposEditor(@js($tiposPassageiro->map(fn($t)=>[
     'id'=>$t->id,'nome'=>$t->nome,'cor'=>$t->cor ?: '#2563eb','modo'=>$t->modo ?: 'valor',
     'percentual'=>(int)$t->percentual,'tarifa_padrao'=>(float)$t->tarifa_padrao,'alimentacao_padrao'=>(float)$t->alimentacao_padrao,
     'eh_normal'=>(bool)$t->eh_normal,'paga_taxa'=>(bool)$t->paga_taxa,'pct_sobre'=>$t->pct_sobre ?: 'tarifa',
   ])->values()))">
  <h2 class="font-bold text-sm text-slate-500 uppercase">Tipos de passageiro</h2>
  <p class="text-xs text-slate-500">Escolha o modo: <b>Valor</b> (Tarifa + Alimentação por rota) ou <b>Porcentagem</b> (% do Normal, automático). Marque qual tipo é o <b>Normal</b> (base do cálculo — ex.: Adulto).</p>
  <form method="post" action="{{ route('admin.tipos-passageiro.salvar') }}" class="space-y-2">@csrf
    <template x-for="(t,i) in tipos" :key="i">
      <div class="rounded-xl border-l-4 border border-slate-200 p-3 flex flex-wrap items-end gap-3" :style="`border-left-color:${t.cor}`">
        <div class="min-w-[150px] flex-1"><label class="label !text-xs">Nome</label>
          <input x-model="t.nome" :name="`tipos[${i}][nome]`" class="input !min-h-[40px]" required maxlength="60" placeholder="Ex.: Criança meia"></div>
        <div><label class="label !text-xs">Cor</label>
          <input type="color" x-model="t.cor" :name="`tipos[${i}][cor]`" class="input !p-1 h-10 w-14"></div>
        <div><label class="label !text-xs">Modo <span x-show="t.eh_normal" class="text-slate-400">(base)</span></label>
          <select x-model="t.modo" :name="`tipos[${i}][modo]`" class="input !min-h-[40px] !w-48" :class="t.eh_normal ? 'pointer-events-none opacity-60' : ''" @mousedown="t.eh_normal && $event.preventDefault()">
            <option value="valor">Valor (Tarifa+Alim.)</option>
            <option value="porcentagem">Porcentagem do Normal</option>
          </select></div>
        <div x-show="t.modo==='porcentagem' && !t.eh_normal" class="flex items-end gap-2 flex-wrap">
          <div class="flex items-end gap-1"><div><label class="label !text-xs">% do Normal</label>
            <input type="number" min="0" max="100" x-model.number="t.percentual" :name="`tipos[${i}][percentual]`" class="input !min-h-[40px] !w-24 text-right"></div><span class="text-sm pb-2">%</span></div>
          <div><label class="label !text-xs">Desconto aplica em</label>
            <select x-model="t.pct_sobre" :name="`tipos[${i}][pct_sobre]`" class="input !min-h-[40px] !w-48">
              <option value="tarifa">Só transporte</option>
              <option value="tudo">Transporte + alimentação</option>
            </select></div>
        </div>
        <template x-if="t.modo==='valor' || t.eh_normal">
          <div class="flex items-end gap-2">
            <div><label class="label !text-xs">Tarifa Padrão</label>
              <input type="text" inputmode="numeric" :value="FBRE.fmtMoney(t.tarifa_padrao)" @input="t.tarifa_padrao=FBRE.parseMoney($event.target.value); $event.target.value=FBRE.fmtMoney(t.tarifa_padrao)" @focus="$event.target.value=''" @blur="$event.target.value=FBRE.fmtMoney(t.tarifa_padrao)" class="input !min-h-[40px] !w-28 text-right"><input type="hidden" :name="`tipos[${i}][tarifa_padrao]`" :value="t.tarifa_padrao"></div>
            <div><label class="label !text-xs">Aliment. Padrão</label>
              <input type="text" inputmode="numeric" :value="FBRE.fmtMoney(t.alimentacao_padrao)" @input="t.alimentacao_padrao=FBRE.parseMoney($event.target.value); $event.target.value=FBRE.fmtMoney(t.alimentacao_padrao)" @focus="$event.target.value=''" @blur="$event.target.value=FBRE.fmtMoney(t.alimentacao_padrao)" class="input !min-h-[40px] !w-28 text-right"><input type="hidden" :name="`tipos[${i}][alimentacao_padrao]`" :value="t.alimentacao_padrao"></div>
          </div>
        </template>
        <label class="flex items-center gap-1.5 text-sm pb-2 cursor-pointer" title="Base do cálculo das porcentagens">
          <input type="radio" name="__normal" :checked="t.eh_normal" @change="marcarNormal(i)" class="w-4 h-4"> Normal</label>
        <input type="hidden" :name="`tipos[${i}][eh_normal]`" :value="t.eh_normal ? 1 : ''">
        <input type="hidden" :name="`tipos[${i}][paga_taxa]`" :value="t.paga_taxa ? 1 : ''">
        <button type="button" @click="remover(i)" class="text-red-500 text-xl pb-1" title="Remover">×</button>
      </div>
    </template>
    <div class="flex items-center gap-2 pt-1">
      <button type="button" @click="adicionar()" class="btn btn-ghost btn-sm">+ Adicionar Tipo</button>
      <button class="btn btn-accent btn-sm">Salvar Tipos</button>
    </div>
  </form>
</div>

@push('scripts')
<script>
function tiposEditor(tipos){
  return {
    tipos: (tipos&&tipos.length) ? tipos : [{id:null,nome:'Adulto',cor:'#2563eb',modo:'valor',percentual:100,tarifa_padrao:0,alimentacao_padrao:0,eh_normal:true,paga_taxa:true,pct_sobre:'tarifa'}],
    adicionar(){ this.tipos.push({id:null,nome:'',cor:'#7c3aed',modo:'porcentagem',percentual:50,tarifa_padrao:0,alimentacao_padrao:0,eh_normal:false,paga_taxa:true,pct_sobre:'tarifa'}); },
    remover(i){ const era=this.tipos[i].eh_normal; this.tipos.splice(i,1); if(era && this.tipos.length) this.tipos[0].eh_normal=true; },
    marcarNormal(i){ this.tipos.forEach((t,k)=>{ t.eh_normal = (k===i); if(k===i) t.modo='valor'; }); },
  };
}
</script>
@endpush

<div class="card p-4 space-y-2 md:col-span-2" x-data="taxasEditor(@js(($taxasEmbarque ?? collect())->map(fn($t)=>['origem'=>$t->origem,'destino'=>$t->destino,'taxa'=>(float)$t->taxa])->values()))">
  <h2 class="font-bold text-sm text-slate-500 uppercase">💲 Taxa de embarque</h2>
  <p class="text-xs text-slate-500">Taxa por trecho (Embarque → Destino), independente do tipo e da rota. Vale nos dois sentidos.</p>
  <form method="post" action="{{ route('admin.taxas-embarque.salvar') }}" class="space-y-2">@csrf
    <div class="grid grid-cols-12 gap-2 text-[11px] font-bold text-slate-500 uppercase px-1">
      <div class="col-span-5">Embarque</div><div class="col-span-5">Destino</div><div class="col-span-2">Taxa (R$)</div>
    </div>
    <template x-for="(t,i) in taxas" :key="i">
      <div class="grid grid-cols-12 gap-2 items-center">
        <input x-model="t.origem" :name="`taxas[${i}][origem]`" list="locais" class="input !min-h-[38px] col-span-5" placeholder="Embarque">
        <input x-model="t.destino" :name="`taxas[${i}][destino]`" list="locais" class="input !min-h-[38px] col-span-4" placeholder="Destino">
        <input type="text" inputmode="numeric" :value="FBRE.fmtMoney(t.taxa)" @input="t.taxa=FBRE.parseMoney($event.target.value); $event.target.value=FBRE.fmtMoney(t.taxa)" @focus="$event.target.value=''" @blur="$event.target.value=FBRE.fmtMoney(t.taxa)" class="input !min-h-[38px] col-span-2 text-right"><input type="hidden" :name="`taxas[${i}][taxa]`" :value="t.taxa">
        <button type="button" @click="taxas.splice(i,1)" class="col-span-1 text-red-500 text-xl">×</button>
      </div>
    </template>
    <div class="flex items-center gap-2 pt-1">
      <button type="button" @click="taxas.push({origem:'',destino:'',taxa:0})" class="btn btn-ghost btn-sm">+ Adicionar</button>
      <button class="btn btn-accent btn-sm">Salvar</button>
    </div>
  </form>
</div>

@push('scripts')
<script>
function taxasEditor(taxas){ return { taxas: (taxas&&taxas.length)?taxas:[{origem:'',destino:'',taxa:0}] }; }
</script>
@endpush

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
