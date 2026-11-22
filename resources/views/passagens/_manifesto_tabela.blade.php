{{-- Tabela-manifesto reutilizável (Caixa por viagem, Lançamentos do Caixa, Minhas vendas).
     Espera: $linhas (coleção de objetos do App\Support\ManifestoCaixa::dados()).
     Opcional: $compacto (bool) esconde colunas menos usadas em telas estreitas. --}}
@php $compacto = $compacto ?? false; @endphp
<div class="manif">
<style>
  .manif{overflow-x:auto;-webkit-overflow-scrolling:touch}
  .manif table{width:100%;border-collapse:collapse;font-size:12px;color:#111}
  .manif th,.manif td{text-align:left;padding:4px 7px;border-bottom:1px solid #e5e7eb;vertical-align:top;white-space:nowrap}
  .manif th{background:#f1f5f9;font-size:10px;text-transform:uppercase;letter-spacing:.03em;color:#374151}
  .manif td.r,.manif th.r{text-align:right}
  .manif tr.canc{background:#fee2e2}
  .manif tr.canc td{color:#b91c1c}
  .manif tr.idoso{background:#dcfce7}
  .manif tr.pcd{background:#fef9c3}
  .manif tr.mun{background:#dbeafe}
  .manif .nome{font-weight:600;white-space:normal;min-width:150px}
  .manif .tag{font-size:9px;font-weight:800;padding:0 4px;border-radius:4px}
  .manif .vazio{text-align:center;color:#94a3b8;padding:20px}
  @media print{ .manif th{position:static} }
</style>
<table>
  <thead><tr>
    <th>Nr</th><th>Ticket</th><th>Trecho</th><th>Nome completo</th><th>Data compra</th>
    <th>Vendedor</th><th class="r">Valor</th><th>P</th><th>Status</th><th>Forma</th><th>Cliente</th><th>Agência</th><th>Lugar</th>
  </tr></thead>
  <tbody>
    @forelse($linhas as $l)
    <tr class="@if($l->cancelado)canc @elseif($l->pcd)pcd @elseif($l->idoso)idoso @elseif($l->municipio || !empty($l->agencia))mun @endif">
      <td>{{ $l->nr }}</td>
      <td>{{ $l->ticket }}</td>
      <td>{{ $l->trecho }}</td>
      <td class="nome">{{ strtoupper($l->nome ?: 'PASSAGEIRO') }}@if($l->cancelado) <span class="tag" style="background:#b91c1c;color:#fff">{{ $l->cancel_label ?? 'DEVOLVIDA' }}</span>@elseif($l->colo) <span class="tag" style="background:#e0f2fe;color:#075985">COLO</span>@endif</td>
      <td>{{ $l->data_compra?->format('d/m/Y') }}</td>
      <td>{{ $l->vendedor }}</td>
      <td class="r">R$ {{ number_format($l->valor,2,',','.') }}</td>
      <td style="font-weight:700">{{ $l->p }}</td>
      <td>@if($l->proprietario ?? false)<b style="color:#067d3b">{{ $l->status }}</b>@else{{ $l->status }}@endif</td>
      <td>{{ $l->forma }}</td>
      <td>{{ $l->cliente }}</td>
      <td>@if(!empty($l->agencia))<b style="color:#1d4ed8">🤝 {{ $l->agencia }}</b>@endif</td>
      <td>{{ $l->poltrona }}</td>
    </tr>
    @empty
    <tr><td colspan="13" class="vazio">Nenhuma venda no filtro selecionado.</td></tr>
    @endforelse
  </tbody>
</table>
</div>
