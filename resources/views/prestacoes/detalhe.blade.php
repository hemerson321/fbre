@extends('layouts.app')
@section('titulo','Prestação #'.$prestacao->numero)
@section('conteudo')
<div class="mx-auto max-w-3xl space-y-3">
  <div class="flex items-center justify-between gap-2 flex-wrap">
    <h1 class="text-lg font-extrabold text-slate-800">📋 Prestação #{{ $prestacao->numero }}</h1>
    <a href="{{ route('caixa.index') }}" class="btn btn-ghost btn-sm">← Voltar ao caixa</a>
  </div>

  <div class="card p-4">
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
      <div><div class="text-[11px] uppercase text-slate-400">Transporte</div><div class="font-semibold">🚤 {{ optional($prestacao->embarcacao)->nome ?? '—' }}</div></div>
      <div><div class="text-[11px] uppercase text-slate-400">Fechada em</div><div class="font-semibold">{{ optional($prestacao->fechada_em)->format('d/m/Y H:i') }}</div></div>
      <div><div class="text-[11px] uppercase text-slate-400">Operador</div><div class="font-semibold">{{ optional($prestacao->fechadaPor)->name ?? '—' }}</div></div>
      <div><div class="text-[11px] uppercase text-slate-400">Passagens</div><div class="font-semibold">{{ $prestacao->qtd }}</div></div>
      <div><div class="text-[11px] uppercase text-slate-400">Forma</div><div class="font-semibold capitalize">{{ $prestacao->forma }}</div></div>
      <div><div class="text-[11px] uppercase text-slate-400">Status</div><div><span class="chip {{ $prestacao->status==='acertada' ? 'bg-emerald-100 text-emerald-700' : ($prestacao->status==='parcial' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">{{ $prestacao->statusLabel() }}</span></div></div>
    </div>
    <div class="mt-3 border-t pt-3 max-w-sm ml-auto text-sm space-y-1">
      <div class="flex justify-between"><span>Total vendas (bruto)</span><b>R$ {{ number_format($prestacao->total_bruto,2,',','.') }}</b></div>
      <div class="flex justify-between text-rose-600"><span>Comissão da empresa ({{ (int)$prestacao->comissao_pct }}%)</span><b>− R$ {{ number_format($prestacao->comissao,2,',','.') }}</b></div>
      <div class="flex justify-between border-t pt-1"><span class="font-semibold">Total devido ao dono</span><b>R$ {{ number_format($prestacao->total_devido,2,',','.') }}</b></div>
      <div class="flex justify-between text-emerald-700"><span>Pago</span><b>R$ {{ number_format($prestacao->valor_pago,2,',','.') }}</b></div>
      <div class="flex justify-between {{ $prestacao->saldo > 0 ? 'text-rose-600' : 'text-slate-400' }}"><span class="font-semibold">Saldo pendente</span><b>R$ {{ number_format($prestacao->saldo,2,',','.') }}</b></div>
    </div>
  </div>

  <div class="card overflow-hidden">
    <div class="p-3 border-b bg-slate-50 font-bold text-sm text-slate-600">🎫 Passagens do ciclo</div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-[10px] uppercase text-slate-400 bg-slate-50"><tr>
          <th class="text-left p-2">Nº</th><th class="text-left p-2">Comprador</th><th class="text-left p-2">Trecho</th><th class="text-left p-2">Viagem</th><th class="text-right p-2">Valor</th>
        </tr></thead>
        <tbody class="divide-y">
          @forelse($passagens as $p)
          <tr>
            <td class="p-2 whitespace-nowrap">{{ $p->numero }}</td>
            <td class="p-2 truncate">{{ $p->comprador_nome ?? '—' }}</td>
            <td class="p-2 whitespace-nowrap text-slate-500">{{ $p->origem }} → {{ $p->destino }}</td>
            <td class="p-2 whitespace-nowrap text-slate-500">{{ $p->data_viagem ? \Illuminate\Support\Carbon::parse($p->data_viagem)->format('d/m/Y') : '—' }}</td>
            <td class="p-2 text-right whitespace-nowrap">R$ {{ number_format($p->total,2,',','.') }}</td>
          </tr>
          @empty
          <tr><td colspan="5" class="p-3 text-center text-slate-400">Sem passagens.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card overflow-hidden">
    <div class="p-3 border-b bg-slate-50 font-bold text-sm text-slate-600">💳 Lançamentos (comprovantes)</div>
    <div class="divide-y text-sm">
      @forelse($pagamentos as $pg)
      <div class="flex items-center justify-between gap-2 p-3 {{ $pg->estornado ? 'opacity-50 line-through' : '' }}">
        <div>
          <div class="font-medium">{{ $pg->categoria }}</div>
          <div class="text-[11px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($pg->created_at)->format('d/m/Y H:i') }} · {{ $pg->meio }} · {{ $pg->tipo_mov }}</div>
        </div>
        <div class="font-bold {{ $pg->tipo_mov==='saida' ? 'text-rose-600' : 'text-emerald-700' }}">{{ $pg->tipo_mov==='saida' ? '−' : '+' }} R$ {{ number_format($pg->valor,2,',','.') }}</div>
      </div>
      @empty
      <div class="p-3 text-center text-slate-400">Sem lançamentos.</div>
      @endforelse
    </div>
  </div>
</div>
@endsection
