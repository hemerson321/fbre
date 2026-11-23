@php
  $avL = $avulsosLista ?? collect();
  $avE = (float) $avL->where('tipo_mov', 'entrada')->sum('valor');
  $avS = (float) $avL->where('tipo_mov', 'saida')->sum('valor');
@endphp
<div class="card overflow-hidden">
  <div class="p-3 border-b flex items-center justify-between gap-2 flex-wrap">
    <div class="font-bold text-sm text-slate-700">↕️ Entradas e saídas avulsas</div>
    <div class="flex items-center gap-2 flex-wrap text-[12px]">
      <span class="rounded-lg bg-emerald-50 text-emerald-700 px-2.5 py-1 font-semibold">Entradas <b>R$ {{ number_format($avE,2,',','.') }}</b></span>
      <span class="rounded-lg bg-rose-50 text-rose-700 px-2.5 py-1 font-semibold">Saídas <b>R$ {{ number_format($avS,2,',','.') }}</b></span>
      <span class="rounded-lg bg-slate-100 text-slate-700 px-2.5 py-1 font-semibold">Saldo <b>R$ {{ number_format($avE - $avS,2,',','.') }}</b></span>
    </div>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-[10px] uppercase tracking-wide text-slate-400 bg-slate-50"><tr>
        <th class="text-left p-2">Data</th><th class="text-left p-2">Tipo</th><th class="text-left p-2">Motivo</th>@if($focoPrest)<th class="text-left p-2">Transporte / viagem</th>@endif<th class="text-left p-2">Forma</th><th class="text-left p-2">Operador</th><th class="text-right p-2">Valor</th>
      </tr></thead>
      <tbody class="divide-y">
        @forelse($avL as $av)
        <tr>
          <td class="p-2 whitespace-nowrap text-slate-500">{{ optional($av->created_at)->format('d/m/Y H:i') }}</td>
          <td class="p-2"><span class="chip {{ $av->tipo_mov === 'saida' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $av->tipo_mov === 'saida' ? 'Saída' : 'Entrada' }}</span></td>
          <td class="p-2">{{ trim(($av->categoria ? $av->categoria.' · ' : '').($av->descricao ?? ''), ' ·') ?: '—' }}</td>
          @if($focoPrest)<td class="p-2 whitespace-nowrap text-slate-500">🚤 {{ optional($av->embarcacao)->nome ?? '—' }}{{ $av->data_viagem ? ' · '.\Illuminate\Support\Carbon::parse($av->data_viagem)->format('d/m/Y') : '' }}</td>@endif
          <td class="p-2 text-slate-500">{{ \App\Models\Pagamento::MEIOS[$av->meio] ?? ucfirst((string) $av->meio) }}</td>
          <td class="p-2 text-slate-500">{{ optional($av->user)->name ?? '—' }}</td>
          <td class="p-2 text-right font-bold whitespace-nowrap {{ $av->tipo_mov === 'saida' ? 'text-rose-600' : 'text-emerald-700' }}">{{ $av->tipo_mov === 'saida' ? '−' : '+' }} R$ {{ number_format($av->valor,2,',','.') }}</td>
        </tr>
        @empty
        <tr><td colspan="{{ $focoPrest ? 7 : 6 }}" class="p-5 text-center text-slate-400">Nenhuma entrada ou saída avulsa no filtro.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
