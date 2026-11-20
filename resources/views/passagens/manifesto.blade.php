<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lista de passageiros</title>
<style>
  *{box-sizing:border-box}
  body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:16px;color:#111;font-size:12px}
  .top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;border-bottom:2px solid #111;padding-bottom:8px;margin-bottom:10px}
  .top h1{font-size:16px;margin:0}
  .muted{color:#555;font-size:11px}
  table{width:100%;border-collapse:collapse}
  th,td{text-align:left;padding:5px 6px;border-bottom:1px solid #ddd;vertical-align:top}
  th{background:#f1f1f1;font-size:10px;text-transform:uppercase;letter-spacing:.03em}
  td.c,th.c{text-align:center}
  tr:nth-child(even) td{background:transparent}
  .chip{display:inline-block;border:1px solid #bbb;border-radius:10px;padding:0 6px;font-size:10px}
  .ok{color:#067d3b;font-weight:bold}
  .no{color:#999}
  .acoes{margin-bottom:10px}
  .btn{display:inline-block;background:#059669;color:#fff;border:0;border-radius:8px;padding:8px 14px;font-weight:bold;cursor:pointer;text-decoration:none;font-size:13px}
  .btn.g{background:#fff;color:#333;border:1px solid #ccc}
  @media print{ .acoes{display:none} body{padding:0} }
  tr.canc td{background:#fee2e2 !important;color:#b91c1c}
  tr.idoso td{background:#dcfce7 !important}
  tr.pcd td{background:#fef9c3 !important}
  tr.mun td{background:#dbeafe !important}
  td.r,th.r{text-align:right;white-space:nowrap}
  .tag{font-size:9px;font-weight:800;padding:0 4px;border-radius:4px}
</style>
</head>
<body>
  <div class="acoes">
    <button class="btn" onclick="window.print()">🖨 Imprimir / Salvar PDF</button>
  </div>

  <div class="top">
    <div>
      <h1>{{ strtoupper($empresa?->nomeExibicao() ?? 'FBRE') }}</h1>
      <div class="muted">Lista de passageiros / manifesto de embarque</div>
      <div class="muted">
        @if(($f['inicio'] ?? '') || ($f['fim'] ?? ''))Período: {{ $f['inicio'] ?? '…' }} a {{ $f['fim'] ?? '…' }} · @endif
        @if($f['origem'] ?? '')Origem: {{ $f['origem'] }} · @endif
        @if($f['destino'] ?? '')Destino: {{ $f['destino'] }} · @endif
        Emitido {{ now()->format('d/m/Y H:i') }}
      </div>
    </div>
    <div style="text-align:right">
      <div style="font-size:22px;font-weight:bold">{{ $linhas->count() }}</div>
      <div class="muted">passageiros</div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Nr</th><th>Ticket</th><th>Trecho</th><th>Nome completo</th><th style="width:70px">Data compra</th>
        <th>Vendedor</th><th class="r">Valor</th><th class="c">P</th><th>Status</th><th>Forma</th><th>Cliente</th><th>Agência</th><th class="c">Lugar</th><th class="c" style="width:70px">Embarque</th>
      </tr>
    </thead>
    <tbody>
      @forelse($linhasPrest as $l)
      <tr class="@if($l->cancelado)canc @elseif($l->pcd)pcd @elseif($l->idoso)idoso @elseif($l->municipio)mun @endif">
        <td>{{ $l->nr }}</td>
        <td>{{ $l->ticket }}</td>
        <td>{{ $l->trecho }}</td>
        <td><b>{{ strtoupper($l->nome ?: 'PASSAGEIRO') }}</b>@if($l->cancelado) <span class="tag" style="background:#b91c1c;color:#fff">DEVOLVIDA</span>@elseif($l->colo) <span class="tag" style="background:#e0f2fe;color:#075985">COLO</span>@endif</td>
        <td>{{ $l->data_compra?->format('d/m/Y') }}</td>
        <td>{{ $l->vendedor }}</td>
        <td class="r">R$ {{ number_format($l->valor,2,',','.') }}</td>
        <td class="c" style="font-weight:700">{{ $l->p }}</td>
        <td>{{ $l->status }}</td>
        <td>{{ $l->forma }}</td>
        <td>{{ $l->cliente }}</td>
        <td>{{ $l->agencia }}</td>
        <td class="c">{{ $l->poltrona }}</td>
        <td class="c">@if($l->embarcou)<span class="ok">✓ embarcou</span>@elseif($l->checkin)<span class="muted">check-in</span>@else<span class="no">—</span>@endif</td>
      </tr>
      @empty
      <tr><td colspan="14" class="c muted" style="padding:20px">Nenhum passageiro no filtro selecionado.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div style="margin-top:12px;font-size:10px;color:#555">Legenda:
    <span style="background:#dbeafe;padding:1px 7px;border-radius:4px">Agente/Município</span>
    <span style="background:#dcfce7;padding:1px 7px;border-radius:4px">Idoso</span>
    <span style="background:#fef9c3;padding:1px 7px;border-radius:4px">PCD</span>
    <span style="background:#fee2e2;color:#b91c1c;padding:1px 7px;border-radius:4px">Cancelado/Devolvido</span>
  </div>

  @if(request('print'))<script>window.addEventListener('load',()=>window.print());</script>@endif
</body>
</html>
