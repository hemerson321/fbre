@extends('layouts.app')
@section('titulo','Meu transporte')
@section('conteudo')
@php $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'); @endphp
<div class="max-w-4xl mx-auto space-y-4">

  {{-- CABECALHO --}}
  <div class="card p-4 flex flex-col sm:flex-row sm:items-center gap-3">
    <div class="flex items-center gap-3 min-w-0 flex-1">
      @if($emb->logo)
        <img src="/{{ $emb->logo }}" class="h-12 w-12 object-contain rounded-lg border bg-white shrink-0" alt="logo">
      @else
        <div class="h-12 w-12 rounded-lg border bg-slate-50 grid place-items-center text-2xl shrink-0">🚤</div>
      @endif
      <div class="min-w-0">
        <h1 class="text-lg font-bold text-slate-800 truncate">{{ $emb->nome }}</h1>
        <p class="text-xs text-slate-500 truncate">Meu transporte · acertos e vendas</p>
      </div>
    </div>
    <a href="{{ route('meu-transporte.relatorio', $emb) }}?pdf=1{{ $dataViagem ? '&data_viagem='.$dataViagem : '' }}{{ $viagemId ? '&viagem_id='.$viagemId : '' }}" target="_blank" class="btn btn-ghost btn-sm w-full sm:w-auto justify-center whitespace-nowrap shrink-0">📄 Prestação (PDF)</a>
  </div>

  {{-- FILTRO: transporte / data de viagem / viagem --}}
  <form method="get" class="card p-3 flex flex-wrap items-end gap-2 text-sm">
    <div class="w-full text-[11px] text-slate-400 font-semibold uppercase">Filtrar por viagem (opcional) — por padrão mostra tudo</div>
    @if($meus->count() > 1)
      <div class="flex-1 min-w-[160px]">
        <label class="label !text-xs">🚤 Transporte</label>
        <select name="transporte_id" class="input !min-h-[38px]" onchange="this.form.submit()">
          @foreach($meus as $m)<option value="{{ $m->id }}" @selected((int)$transporteId===(int)$m->id)>{{ $m->nome }}</option>@endforeach
        </select>
      </div>
    @else
      <input type="hidden" name="transporte_id" value="{{ $transporteId }}">
    @endif
    @if($datasViagem->count())
      <div class="flex-1 min-w-[150px]">
        <label class="label !text-xs">📅 Data da viagem</label>
        <select name="data_viagem" class="input !min-h-[38px]" onchange="this.form.submit()">
          <option value="">Todas as datas</option>
          @foreach($datasViagem as $dv)@php $dvs = \Illuminate\Support\Carbon::parse($dv)->toDateString(); @endphp<option value="{{ $dvs }}" @selected((string)($dataViagem ?? '')===$dvs)>{{ \Illuminate\Support\Carbon::parse($dv)->format('d/m/Y') }}</option>@endforeach
        </select>
      </div>
    @endif
    @if($temViagens && $viagens->count())
      <div class="flex-1 min-w-[150px]">
        <label class="label !text-xs">🧭 Viagem</label>
        <select name="viagem_id" class="input !min-h-[38px]" onchange="this.form.submit()">
          <option value="">Todas as viagens</option>
          @foreach($viagens as $v)<option value="{{ $v->id }}" @selected((int)($viagemId ?? 0)===(int)$v->id)>{{ optional($v->rota)->nome ?? ('Viagem #'.$v->id) }}{{ $v->data_partida ? ' · '.\Illuminate\Support\Carbon::parse($v->data_partida)->format('d/m H:i') : '' }}</option>@endforeach
        </select>
      </div>
    @endif
    @if($dataViagem || $viagemId)<a href="{{ route('meu-transporte', ['transporte_id'=>$transporteId]) }}" class="btn btn-ghost btn-sm">limpar</a>@endif
  </form>

  {{-- ABAS --}}
  @php $qsBase = array_filter(['transporte_id'=>$transporteId, 'data_viagem'=>$dataViagem, 'viagem_id'=>$viagemId]); @endphp
  <div class="flex gap-2">
    <a href="{{ route('meu-transporte', $qsBase + ['aba'=>'acertos']) }}" class="btn btn-sm {{ $aba==='acertos' ? 'text-white' : 'btn-ghost' }}" @if($aba==='acertos') style="background:var(--cor1)" @endif>💰 Acertos</a>
    <a href="{{ route('meu-transporte', $qsBase + ['aba'=>'vendas']) }}" class="btn btn-sm {{ $aba==='vendas' ? 'text-white' : 'btn-ghost' }}" @if($aba==='vendas') style="background:var(--cor1)" @endif>📊 Relatório de vendas</a>
  </div>

  {{-- ABA: ACERTOS --}}
  @if($aba==='acertos')
  {{-- RESUMO DO PERIODO --}}
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
    <div class="card p-4">
      <div class="text-[11px] text-slate-500 uppercase font-semibold">✅ Já recebido</div>
      <div class="text-2xl font-extrabold text-emerald-600">{{ $brl($recebido) }}</div>
      <div class="text-[11px] text-slate-400">{{ $recebidoQtd }} baixada(s)</div>
    </div>
    <div class="card p-4">
      <div class="text-[11px] text-slate-500 uppercase font-semibold">⏳ A receber</div>
      <div class="text-2xl font-extrabold text-amber-600">{{ $brl($aReceber) }}</div>
      <div class="text-[11px] text-slate-400">ainda em aberto</div>
    </div>
  </div>

  {{-- A RECEBER (PENDENTE) --}}
  <div class="card p-4 space-y-3">
    <div class="flex items-center justify-between">
      <h2 class="font-bold text-slate-700">A receber</h2>
      <div class="text-right">
        <div class="text-2xl font-extrabold" style="color:var(--cor1)">{{ $brl($aReceber) }}</div>
        <div class="text-[11px] text-slate-400">em aberto {{ ($dataViagem || $viagemId) ? '(filtro aplicado)' : '(todas as datas)' }}</div>
      </div>
    </div>

    {{-- BALCAO: a empresa deve ao proprietário --}}
    <div class="rounded-xl border border-slate-200 p-3">
      <div class="flex items-center justify-between gap-2 flex-wrap">
        <div>
          <div class="font-semibold text-slate-700">🏬 Balcão <span class="text-[11px] text-slate-400 font-normal">· a empresa te paga</span></div>
          <div class="text-[11px] text-slate-400">{{ $balcaoQtd }} passagem(ns) · bruto {{ $brl($balcaoBruto) }} · comissão empresa {{ $pct }}% ({{ $brl($balcaoBruto - $balcaoDono) }})</div>
        </div>
        <div class="text-right shrink-0">
          <div class="text-lg font-bold text-slate-800">{{ $brl($balcaoDono) }}</div>
          <div class="text-[11px] text-slate-400">a empresa acerta</div>
        </div>
      </div>
    </div>

    {{-- AGENTES: pagam direto ao proprietário --}}
    <div class="rounded-xl border border-slate-200 p-3 space-y-2">
      <div class="font-semibold text-slate-700">🤝 Agentes <span class="text-[11px] text-slate-400 font-normal">· pagam direto a você</span></div>
      @forelse($agentesList as $ag)
        <div class="flex items-center justify-between gap-2 flex-wrap border-t first:border-t-0 pt-2 first:pt-0">
          <div class="min-w-0">
            <div class="font-medium text-slate-700 truncate">{{ $ag->nome }}</div>
            @php $agPct = $ag->bruto > 0 ? round(($ag->bruto - $ag->dono) / $ag->bruto * 100) : 0; @endphp
            <div class="text-[11px] text-slate-400">{{ $ag->qtd }} passagem(ns) · bruto {{ $brl($ag->bruto) }} · comissão do agente {{ $agPct }}% ({{ $brl($ag->bruto - $ag->dono) }})</div>
          </div>
          <div class="flex items-center gap-2">
            <div class="text-lg font-bold text-slate-800">{{ $brl($ag->dono) }}</div>
            <form method="post" action="{{ route('meu-transporte.baixa-agente', [$emb, $ag->id]) }}" onsubmit="return confirm('Confirmar que recebeu {{ $brl($ag->dono) }} do agente {{ $ag->nome }}? Vai marcar {{ $ag->qtd }} passagem(ns) como acertadas.')">
              @csrf
              <input type="hidden" name="data_viagem" value="{{ $dataViagem }}">
              <input type="hidden" name="viagem_id" value="{{ $viagemId }}">
              <button class="btn btn-accent btn-sm whitespace-nowrap">Dar baixa</button>
            </form>
          </div>
        </div>
      @empty
        <div class="text-xs text-slate-400">Nenhum agente com venda em aberto.</div>
      @endforelse
    </div>

    @if($saldoDevedor > 0)
    <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
      <div class="flex items-center justify-between gap-2 flex-wrap">
        <div>
          <div class="font-semibold text-rose-700">↩ Débito com a empresa</div>
          <div class="text-[11px] text-rose-500">devolução de passagem que já tinha sido acertada — será descontado no seu próximo acerto</div>
        </div>
        <div class="text-lg font-bold text-rose-700">− {{ $brl($saldoDevedor) }}</div>
      </div>
    </div>
    @endif

    <p class="text-[11px] text-slate-400">O balcão é pago pela empresa (sai do caixa dela). O agente paga direto a você — a baixa aqui só confirma o recebimento, não mexe no caixa da empresa.</p>
  </div>
  @endif

  {{-- ABA: RELATORIO DE VENDAS --}}
  @if($aba==='vendas')
  <div class="card overflow-hidden">
    <div class="p-3 border-b font-bold text-sm text-slate-500 uppercase">Vendas da lancha ({{ $vendas->count() }})</div>
    <div class="divide-y text-sm">
      @forelse($vendas as $p)
      @php
        $ehAg  = (bool) $p->revendedor_id;
        $donoP = $ehAg ? (float) ($p->acerto_empresa_valor ?? 0) : (float) $p->total * (100 - $pct) / 100;
        $comP  = (float) $p->total - $donoP;
      @endphp
      <div class="flex items-center justify-between gap-3 p-3">
        <div class="min-w-0">
          <div class="font-semibold truncate">#{{ $p->numeroFormatado() }} · {{ $p->origem }} → {{ $p->destino }}</div>
          <div class="text-xs text-slate-400 truncate">{{ $ehAg ? '🤝 '.($p->revendedor?->name ?? 'agente') : '🏬 balcão' }} · vendeu: {{ $p->operador?->name ?? '—' }}@if($p->data_viagem) · 📅 {{ \Illuminate\Support\Carbon::parse($p->data_viagem)->format('d/m/Y') }}@endif</div>
          <div class="text-[11px] mt-1">
            <span class="text-slate-400">bruto {{ $brl($p->total) }}</span>
            <span class="text-rose-500 font-medium"> · comissão {{ $ehAg ? 'do agente' : 'empresa '.$pct.'%' }} {{ $brl($comP) }}</span>
          </div>
        </div>
        <div class="text-right shrink-0">
          <div class="text-[10px] text-slate-400 uppercase font-semibold leading-none">seu</div>
          <div class="font-extrabold text-base" style="color:var(--cor1)">{{ $brl($donoP) }}</div>
          @if($p->acerto_dono)<span class="text-[11px] text-emerald-600 font-semibold">✓ acertado</span>@else<span class="text-[11px] text-amber-600 font-semibold">em aberto</span>@endif
        </div>
      </div>
      @empty
      <div class="p-6 text-center text-slate-400">Nenhuma venda no filtro.</div>
      @endforelse
    </div>
  </div>
  @endif

  <div class="card overflow-hidden">
    <div class="p-3 border-b bg-slate-50 flex items-center justify-between flex-wrap gap-2">
      <div class="font-bold text-sm text-slate-700">🧾 Prestações de contas da agência</div>
      <div class="text-[11px] text-slate-400">o que a agência já fechou e pagou desta lancha — mais recentes primeiro</div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-[10px] uppercase tracking-wide text-slate-400 bg-slate-50"><tr>
          <th class="text-left p-2">Nº</th><th class="text-left p-2">Viagem</th><th class="text-left p-2">Feita em</th><th class="text-right p-2">Passagens</th><th class="text-right p-2">Total</th><th class="text-right p-2">Pago</th><th class="text-right p-2">Saldo</th><th class="text-left p-2">Forma</th><th class="text-center p-2">Status</th>
        </tr></thead>
        <tbody class="divide-y">
          @forelse(($prestacoesDono ?? collect()) as $pr)
          <tr>
            <td class="p-2 font-semibold whitespace-nowrap">#{{ $pr->numero }}</td>
            <td class="p-2 whitespace-nowrap">🗓 {{ $pr->viagens_txt ?? '—' }}</td>
            <td class="p-2 text-slate-500 whitespace-nowrap">{{ optional($pr->fechada_em)->format('d/m/Y H:i') }}</td>
            <td class="p-2 text-right">{{ $pr->qtd }}</td>
            <td class="p-2 text-right whitespace-nowrap">R$ {{ number_format($pr->total_devido,2,',','.') }}</td>
            <td class="p-2 text-right whitespace-nowrap text-emerald-700">R$ {{ number_format($pr->valor_pago,2,',','.') }}</td>
            <td class="p-2 text-right whitespace-nowrap {{ $pr->saldo > 0 ? 'text-rose-600 font-semibold' : 'text-slate-400' }}">R$ {{ number_format($pr->saldo,2,',','.') }}</td>
            <td class="p-2 capitalize text-slate-500">{{ $pr->forma }}</td>
            <td class="p-2 text-center"><span class="chip {{ $pr->status==='acertada' ? 'bg-emerald-100 text-emerald-700' : ($pr->status==='parcial' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">{{ $pr->statusLabel() }}</span></td>
          </tr>
          @empty
          <tr><td colspan="9" class="p-5 text-center text-slate-400">Nenhuma prestação fechada ainda.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@include('partials.pulse')
@endsection
