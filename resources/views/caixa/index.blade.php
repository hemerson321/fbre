@extends('layouts.app')
@section('titulo','Caixa')
@section('conteudo')
<div class="space-y-4">
<div class="flex items-center justify-between"><h1 class="text-xl font-bold">{{ $verTodos ? 'Caixa geral' : 'Meu caixa' }}</h1>
  <button onclick="window.print()" class="btn btn-ghost btn-sm no-print">🖨 Imprimir</button></div>

@if($podeAvulso)
<div x-data="{open:false, tipo:'entrada'}" class="no-print">
  <div class="flex gap-2">
    <button type="button" @click="tipo='entrada';open=true" class="btn btn-sm text-white" style="background:#059669">➕ Entrada avulsa</button>
    <button type="button" @click="tipo='saida';open=true" class="btn btn-sm text-white" style="background:#e11d48">➖ Saída avulsa</button>
  </div>
  <div x-show="open" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-3" @click="open=false">
    <form method="post" action="{{ route('caixa.avulso') }}" class="bg-white rounded-2xl p-4 w-full max-w-sm space-y-3" @click.stop>
      @csrf
      <input type="hidden" name="tipo_mov" :value="tipo">
      <div class="font-bold text-lg" x-text="tipo==='entrada' ? '➕ Entrada avulsa' : '➖ Saída avulsa'"></div>
      <div><label class="label">Categoria</label>
        <select name="categoria" class="input" required>
          <option value="">— escolher —</option>
          @foreach($categorias as $c)<option value="{{ $c->nome }}" x-show="'{{ $c->tipo }}'==='ambos' || '{{ $c->tipo }}'===tipo">{{ $c->nome }}</option>@endforeach
        </select>
        @if(auth()->user()->isAdmin() || auth()->user()->isSuper())<p class="text-[11px] text-slate-400 mt-1">Criar/editar categorias em <a href="{{ route('admin.cadastros') }}" class="underline">Cadastros gerais</a>.</p>@endif
      </div>
      <div><label class="label">Valor</label><input name="valor" class="input" x-money></div>
      <div><label class="label">Forma</label>
        <select name="meio" class="input"><option value="dinheiro">Dinheiro</option><option value="pix">PIX</option><option value="debito">Débito</option><option value="credito">Crédito</option></select></div>
      <div><label class="label">Descrição (opcional)</label><input name="descricao" class="input" maxlength="200"></div>
      <div class="flex gap-2 pt-1">
        <button type="button" class="btn btn-ghost flex-1" @click="open=false">Cancelar</button>
        <button class="btn flex-1 text-white" :style="tipo==='entrada'?'background:#059669':'background:#e11d48'">Registrar</button>
      </div>
    </form>
  </div>
</div>
@endif

<x-periodo :periodo="$periodo" :inicio="$inicio" :fim="$fim">
  @if($verTodos)
  <div><label class="label">Operador</label>
    <select name="user_id" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todos</option>
      @foreach($usuarios as $us)<option value="{{ $us->id }}" @selected($userId==$us->id)>{{ $us->name }}</option>@endforeach</select></div>
  @endif
  <div><label class="label">Módulo</label>
    <select name="modulo" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todos os módulos</option>
      @foreach(\App\Support\Modulos::LISTA as $mk=>$mv)<option value="{{ $mk }}" @selected(($modulo ?? '')===$mk)>{{ $mv[1] }} {{ $mv[0] }}</option>@endforeach</select></div>
  <div><label class="label">Forma</label>
    <select name="canal" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todas as formas</option>
      @foreach(\App\Models\Pagamento::CANAIS as $ck=>$cv)<option value="{{ $ck }}" @selected(($canal ?? '')===$ck)>{{ $cv }}</option>@endforeach</select></div>
  @if($viagens->count())
  <div><label class="label">Viagem</label>
    <select name="viagem_id" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">— período —</option>
      @foreach($viagens as $v)<option value="{{ $v->id }}" @selected($viagemId==$v->id)>{{ $v->numeroFormatado() }} · {{ $v->nome }}</option>@endforeach</select></div>
  @endif
</x-periodo>
@if($viagemId)<div class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Mostrando o caixa da viagem selecionada (o período acima é ignorado).</div>@endif

<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-2">
  <div class="card p-3 text-white" style="background:var(--cor1)"><div class="text-[11px] uppercase opacity-70">Caixa (líquido)</div><div class="text-2xl font-extrabold">R$ {{ number_format($total,2,',','.') }}</div><div class="text-[10px] opacity-70">entradas {{ number_format($entradas,2,',','.') }} · saídas {{ number_format($saidas,2,',','.') }}</div></div>
  @foreach(['dinheiro','pix_manual','pix_auto','debito','credito'] as $m)
    <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">{{ $meios[$m] }}</div><div class="text-lg font-extrabold">R$ {{ number_format($porMeio[$m]->valor ?? 0,2,',','.') }}</div><div class="text-xs text-slate-400">{{ $porMeio[$m]->qtd ?? 0 }} lançamentos</div></div>
  @endforeach
  <div class="card p-3 text-xs space-y-1">
    <div class="flex justify-between"><span class="text-slate-500">Saídas avulsas</span><b class="text-red-600">R$ {{ number_format($saidas,2,',','.') }}</b></div>
    <div class="flex justify-between"><span class="text-slate-500">Descontos</span><b>R$ {{ number_format($descontos,2,',','.') }}</b></div>
    <div class="flex justify-between"><span class="text-slate-500">Cadastros</span><b>{{ $cadastros }}</b></div>
    <div class="flex justify-between"><span class="text-slate-500">Entregas</span><b>{{ $entregas }}</b></div>
  </div>
</div>

<div class="card overflow-hidden">
  <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Por operador</div>
  <table class="w-full text-sm"><thead class="bg-slate-50 text-[11px] uppercase text-slate-500"><tr><th class="p-3 text-left">Operador</th>@foreach(['dinheiro','pix_manual','pix_auto','debito','credito'] as $m)<th class="p-3 text-right">{{ $meios[$m] }}</th>@endforeach<th class="p-3 text-right">Total</th></tr></thead>
  <tbody class="divide-y">
  @forelse($porOperador as $uid=>$d)
    <tr><td class="p-3 font-semibold">{{ $usuarios[$uid]->name ?? '—' }}</td>@foreach(['dinheiro','pix_manual','pix_auto','debito','credito'] as $m)<td class="p-3 text-right">{{ number_format($d['meios'][$m] ?? 0,2,',','.') }}</td>@endforeach<td class="p-3 text-right font-bold">{{ number_format($d['total'],2,',','.') }}</td></tr>
  @empty <tr><td colspan="7" class="p-6 text-center text-slate-400">Sem movimento no período.</td></tr> @endforelse
  </tbody></table>
</div>

<div class="card overflow-hidden">
  <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Lançamentos</div>
  <div class="divide-y">
  @if(isset($porModulo) && $porModulo->count())
  <div class="px-3 pt-3 flex flex-wrap gap-2">
    @foreach($porModulo as $mod=>$row)
      <span class="chip" style="background:{{ \App\Support\Modulos::hex($mod) }}20;color:{{ \App\Support\Modulos::hex($mod) }}">{{ \App\Support\Modulos::icone($mod) }} {{ \App\Support\Modulos::rotulo($mod) }}: R$ {{ number_format($row->valor,2,',','.') }} ({{ $row->qtd }})</span>
    @endforeach
  </div>
  @endif
  @foreach($movimentos as $m)
    @php $rf = $m->resumoFonte(); @endphp
    <a href="{{ $rf['url'] ?? '#' }}" class="flex items-center gap-3 p-3 hover:bg-slate-50 text-sm">
      <div class="w-14 text-xs text-slate-400">{{ $m->created_at->format('d/m') }}<br>{{ $m->created_at->format('H:i') }}</div>
      <div class="flex-1 min-w-0"><span class="chip" style="background:{{ \App\Support\Modulos::hex($m->modulo ?: 'encomenda') }}20;color:{{ \App\Support\Modulos::hex($m->modulo ?: 'encomenda') }}">{{ \App\Support\Modulos::icone($m->modulo ?: 'encomenda') }}</span> <b>#{{ $rf['numero'] }}</b> {{ $rf['nome'] }}<div class="text-xs text-slate-400">{{ $m->user?->name }} · {{ $m->momento==='cadastro'?'no cadastro':'na baixa' }}</div></div>
      <span class="chip bg-slate-100 text-slate-600">{{ $meios[$m->canal] ?? \App\Models\Pagamento::MEIOS[$m->meio] ?? $m->meio }}</span>
      <div class="font-bold whitespace-nowrap">R$ {{ number_format($m->valor,2,',','.') }}</div>
    </a>
  @endforeach
  </div>
  <div class="p-3">{{ $movimentos->links() }}</div>
</div>
</div>
@endsection
