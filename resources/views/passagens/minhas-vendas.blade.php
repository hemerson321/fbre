@extends('layouts.app')
@section('titulo','Minhas vendas')
@section('conteudo')
<div class="space-y-4">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-bold">Minhas vendas <span class="text-slate-400 font-normal text-base">· {{ auth()->user()->name }}</span></h1>
    <div class="flex items-center gap-2 no-print">
      <a href="{{ route('passagens.minhas-vendas.exportar', request()->query()) }}" class="btn btn-ghost btn-sm">⬇️ Exportar CSV</a>
      <a href="{{ route('passagens.caixa-viagem', array_merge(request()->only(['inicio','fim']), ['user_id'=>auth()->id()])) }}" target="_blank" class="btn btn-ghost btn-sm">📄 Prestação (PDF)</a>
      <button onclick="window.print()" class="btn btn-ghost btn-sm">🖨 Imprimir</button>
    </div>
  </div>

  <x-periodo :periodo="$periodo" :inicio="$inicio" :fim="$fim">
    <div><label class="label">Forma</label>
      <select name="canal" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todas as formas</option>
        @foreach(\App\Models\Pagamento::CANAIS as $ck=>$cv)<option value="{{ $ck }}" @selected(($canal ?? '')===$ck)>{{ $cv }}</option>@endforeach</select></div>
  </x-periodo>

  <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
    <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">Passagens</div><div class="text-xl font-extrabold">{{ $totais['qtd'] }}</div><div class="text-xs text-slate-400">{{ $totais['passageiros'] }} passageiro(s)</div></div>
    <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">Total vendido</div><div class="text-xl font-extrabold">R$ {{ number_format($totais['total'],2,',','.') }}</div></div>
    <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">Recebido</div><div class="text-xl font-extrabold text-emerald-600">R$ {{ number_format($totais['recebido'],2,',','.') }}</div></div>
    <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">A receber</div><div class="text-xl font-extrabold text-amber-600">R$ {{ number_format($totais['devido'],2,',','.') }}</div></div>
  </div>

  @if($painelAgente ?? null)
  <div class="space-y-4">
    @include('acertos._painel', $painelAgente)
  </div>
  @endif

  @if($totais['qtd'] > $limite)
    <div class="card p-3 text-xs text-amber-700 bg-amber-50 border border-amber-200">Período grande: mostrando as <b>{{ $limite }}</b> vendas mais recentes de <b>{{ $totais['qtd'] }}</b>. Para a lista completa, use <b>⬇️ Exportar CSV</b>.</div>
  @endif
  <div class="card overflow-hidden">
    <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Vendas no período ({{ $totais['qtd'] }})</div>
    <div class="divide-y text-sm">
      @forelse($vendas as $p)
        @php
          $corV = null;
          if ($p->cancelada) $corV = '#fee2e2';
          elseif ($p->forma === 'faturado') $corV = '#dbeafe';
          elseif ($p->passageiros->contains(fn($x)=> mb_stripos((string)$x->tipo,'pcd')!==false || mb_stripos((string)$x->tipo,'defic')!==false)) $corV = '#fef9c3';
          elseif ($p->passageiros->contains(fn($x)=> mb_stripos((string)$x->tipo,'idoso')!==false)) $corV = '#dcfce7';
        @endphp
        <a href="{{ route('passagens.show',$p) }}" class="flex justify-between gap-3 p-3 hover:brightness-95"@if($corV) style="background:{{ $corV }}"@endif>
          <div class="min-w-0">
            <b>#{{ $p->numeroFormatado() }}</b> · {{ $p->origem }} → {{ $p->destino }}
            <div class="text-xs text-slate-400">{{ $p->created_at->format('d/m H:i') }} · {{ $p->passageiros->count() }} pax{{ $p->embarcacao ? ' · '.$p->embarcacao->nome : '' }}</div>
          </div>
          <div class="text-right whitespace-nowrap">
            <b>R$ {{ number_format((float)$p->total,2,',','.') }}</b>
            @if($p->devido > 0.009)<div class="text-xs text-amber-600">deve R$ {{ number_format((float)$p->devido,2,',','.') }}</div>@else<div class="text-xs text-emerald-600">pago</div>@endif
          </div>
        </a>
      @empty <div class="p-6 text-center text-slate-400">Nenhuma venda no período.</div> @endforelse
    </div>
  </div>
  <div class="text-[11px] text-slate-500 flex flex-wrap gap-2 px-1">Legenda:
    <span style="background:#dbeafe;padding:1px 7px;border-radius:4px">Agente/Município</span>
    <span style="background:#dcfce7;padding:1px 7px;border-radius:4px">Idoso</span>
    <span style="background:#fef9c3;padding:1px 7px;border-radius:4px">PCD</span>
    <span style="background:#fee2e2;color:#b91c1c;padding:1px 7px;border-radius:4px">Cancelado</span>
  </div>
</div>
@endsection
