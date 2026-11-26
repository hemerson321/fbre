@extends('layouts.app')
@section('titulo','Entradas e saídas')
@section('conteudo')
@php
  $cor = ['cancelamento' => ['#fee2e2', '#991b1b'], 'devolucao' => ['#ffedd5', '#9a3412'], 'avulso' => ['#e0e7ff', '#3730a3'], 'transferencia' => ['#cffafe', '#155e75']];
  $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
@endphp
<div class="space-y-4" x-data="{ open:false, id:null, base:@js(url('/admin/movimentos')), f:{ tipo_mov:'saida', valor:'', meio:'dinheiro', categoria:'', descricao:'' },
    editar(l){ this.id=l.id; this.f.tipo_mov=l.mov; this.f.valor=l.valor; this.f.meio=l.meio_raw||'dinheiro'; this.f.categoria=l.categoria||''; this.f.descricao=l.descricao||''; this.open=true; } }">
  <div class="flex items-center justify-between gap-2 flex-wrap">
    <div>
      <h1 class="text-xl font-extrabold">↕️ Entradas e saídas</h1>
      <p class="text-sm text-slate-500">Cancelamentos, devoluções, transferências e avulsos — {{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }}</p>
    </div>
    <button type="button" onclick="window.print()" class="btn btn-sm no-print">🖨 Imprimir</button>
  </div>

  <form method="get" class="card p-3 grid grid-cols-2 lg:grid-cols-7 gap-2 items-end no-print">
    <div><label class="label">De</label><input type="date" name="inicio" value="{{ $inicio->toDateString() }}" class="input !min-h-[40px]"></div>
    <div><label class="label">Até</label><input type="date" name="fim" value="{{ $fim->toDateString() }}" class="input !min-h-[40px]"></div>
    <div><label class="label">Tipo</label><select name="tipo" class="input !min-h-[40px]"><option value="">Todos</option>@foreach($tipos as $k => $n)<option value="{{ $k }}" @selected($tipo === $k)>{{ $n }}</option>@endforeach</select></div>
    <div><label class="label">Movimento</label><select name="mov" class="input !min-h-[40px]"><option value="">Entradas e saídas</option><option value="entrada" @selected($mov === 'entrada')>Só entradas</option><option value="saida" @selected($mov === 'saida')>Só saídas</option></select></div>
    <div><label class="label">Transporte</label><select name="transporte_id" class="input !min-h-[40px]"><option value="">Todos</option>@foreach($transportes as $t)<option value="{{ $t->id }}" @selected((int) $embId === (int) $t->id)>{{ $t->nome }}</option>@endforeach</select></div>
    <div><label class="label">Operador</label><select name="user_id" class="input !min-h-[40px]"><option value="">Todos</option>@foreach($usuarios as $u)<option value="{{ $u->id }}" @selected((int) $userId === (int) $u->id)>{{ $u->name }}</option>@endforeach</select></div>
    <div><button class="btn btn-primary w-full">Filtrar</button></div>
  </form>

  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    @foreach($resumo as $k => $r)
    <div class="card p-3" style="border-left:4px solid {{ $cor[$k][1] }}">
      <div class="text-[11px] font-bold uppercase tracking-wide" style="color:{{ $cor[$k][1] }}">{{ $r['nome'] }} · {{ $r['qtd'] }}</div>
      <div class="text-sm mt-1"><span class="text-emerald-700 font-bold">+ {{ $brl($r['entradas']) }}</span></div>
      <div class="text-sm"><span class="text-rose-600 font-bold">− {{ $brl($r['saidas']) }}</span></div>
    </div>
    @endforeach
  </div>

  <div class="card p-3 flex items-center justify-end gap-4 flex-wrap text-sm">
    <span>Entradas <b class="text-emerald-700">{{ $brl($totEntradas) }}</b></span>
    <span>Saídas <b class="text-rose-600">{{ $brl($totSaidas) }}</b></span>
    <span>Saldo <b class="{{ $totEntradas - $totSaidas < 0 ? 'text-rose-600' : 'text-slate-800' }}">{{ $brl($totEntradas - $totSaidas) }}</b></span>
  </div>

  <div class="card overflow-x-auto">
    <table class="w-full text-sm">
      <thead><tr class="text-left text-[11px] uppercase tracking-wide text-slate-400 border-b">
        <th class="p-2.5">Data</th><th class="p-2.5">Tipo</th><th class="p-2.5">O que é</th><th class="p-2.5">Bilhete / passageiro</th><th class="p-2.5">Transporte</th><th class="p-2.5">Operador</th><th class="p-2.5">Forma</th><th class="p-2.5 text-right">Valor</th><th class="p-2.5 text-right no-print">Ações</th>
      </tr></thead>
      <tbody class="divide-y">
        @forelse($linhas as $l)
        <tr>
          <td class="p-2.5 whitespace-nowrap">{{ $l->data?->format('d/m/Y H:i') }}</td>
          <td class="p-2.5"><span class="rounded-full px-2 py-0.5 text-[11px] font-bold whitespace-nowrap" style="background:{{ $cor[$l->tipo][0] }};color:{{ $cor[$l->tipo][1] }}">{{ $tipos[$l->tipo] }}</span></td>
          <td class="p-2.5"><div class="font-semibold">{{ $l->oque }}</div>@if($l->descricao && $l->descricao !== $l->oque)<div class="text-[11px] text-slate-500">{{ $l->descricao }}</div>@endif</td>
          <td class="p-2.5">{{ $l->bilhete ?: '—' }}@if($l->passageiro)<div class="text-[11px] text-slate-500">{{ $l->passageiro }}</div>@endif</td>
          <td class="p-2.5">{{ $l->transporte ?: '—' }}</td>
          <td class="p-2.5">{{ $l->operador ?: '—' }}</td>
          <td class="p-2.5">{{ $l->forma }}</td>
          <td class="p-2.5 text-right font-bold whitespace-nowrap {{ $l->mov === 'saida' ? 'text-rose-600' : 'text-emerald-700' }}">{{ $l->mov === 'saida' ? '−' : '+' }} {{ $brl($l->valor) }}</td>
          <td class="p-2.5 text-right whitespace-nowrap no-print">
            @if($l->editavel)
            <button type="button" class="btn btn-sm" title="Editar" @click="editar(@js(['id'=>$l->id,'mov'=>$l->mov,'valor'=>$l->valor,'meio_raw'=>$l->meio_raw,'categoria'=>$l->categoria,'descricao'=>$l->descricao]))">✏️</button>
            @endif
            <form method="post" action="{{ route('admin.movimentos.estornar', $l->id) }}" class="inline" onsubmit="return confirm(@js($l->editavel ? 'Excluir este lançamento avulso?' : 'Estornar este lançamento? Isso reverte só o valor no caixa e NÃO descancela a passagem. Use só pra corrigir um lançamento errado.'))">@csrf
              <button class="btn btn-sm" style="color:#b91c1c" title="{{ $l->editavel ? 'Excluir' : 'Estornar' }}">🗑</button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="9" class="p-8 text-center text-slate-400">Nenhum lançamento no período com esses filtros.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- modal: editar lançamento avulso --}}
  <div x-show="open" x-cloak class="fixed inset-0 z-[60] overflow-y-auto no-print" @keydown.escape.window="open=false">
    <div class="min-h-full flex items-start justify-center p-4" style="background:rgba(15,23,42,.45)" @click.self="open=false">
      <form method="post" :action="base + '/' + id + '/editar'" class="card w-full max-w-md my-8 p-4 space-y-2">
        @csrf
        <div class="flex items-center justify-between"><div class="font-bold text-sm text-slate-700">✏️ Editar avulso</div><button type="button" @click="open=false" class="btn btn-sm">✖</button></div>
        <div class="grid grid-cols-2 gap-2">
          <button type="button" @click="f.tipo_mov='entrada'" :class="f.tipo_mov==='entrada' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-300'" class="rounded-xl border py-2 text-sm font-bold">+ Entrada</button>
          <button type="button" @click="f.tipo_mov='saida'" :class="f.tipo_mov==='saida' ? 'bg-rose-600 text-white border-rose-600' : 'bg-white text-slate-600 border-slate-300'" class="rounded-xl border py-2 text-sm font-bold">− Saída</button>
        </div>
        <input type="hidden" name="tipo_mov" :value="f.tipo_mov">
        <div><label class="label">Valor</label><input type="number" step="0.01" min="0.01" name="valor" x-model="f.valor" class="input" required></div>
        <div><label class="label">Forma</label>
          <select name="meio" x-model="f.meio" class="input">
            <option value="dinheiro">Dinheiro</option><option value="pix">Pix</option><option value="debito">Débito</option><option value="credito">Crédito</option>
          </select>
        </div>
        <div><label class="label">O que é (categoria)</label><input name="categoria" x-model="f.categoria" class="input" placeholder="ex.: Adiantamento"></div>
        <div><label class="label">Descrição (opcional)</label><input name="descricao" x-model="f.descricao" class="input"></div>
        <div class="flex gap-2 pt-1"><button class="btn btn-primary btn-sm">Salvar</button><button type="button" @click="open=false" class="btn btn-sm text-slate-500">Cancelar</button></div>
      </form>
    </div>
  </div>
</div>
@endsection
