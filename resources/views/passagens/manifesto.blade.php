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
  tr:nth-child(even) td{background:#fafafa}
  .chip{display:inline-block;border:1px solid #bbb;border-radius:10px;padding:0 6px;font-size:10px}
  .ok{color:#067d3b;font-weight:bold}
  .no{color:#999}
  .acoes{margin-bottom:10px}
  .btn{display:inline-block;background:#059669;color:#fff;border:0;border-radius:8px;padding:8px 14px;font-weight:bold;cursor:pointer;text-decoration:none;font-size:13px}
  .btn.g{background:#fff;color:#333;border:1px solid #ccc}
  @media print{ .acoes{display:none} body{padding:0} }
</style>
</head>
<body>
  <div class="acoes">
    <button class="btn" onclick="window.print()">🖨 Imprimir / Salvar PDF</button>
    <a class="btn g" href="{{ url()->previous() }}">← Voltar</a>
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
        <th style="width:26px" class="c">#</th>
        <th>Passageiro</th>
        <th>Documento</th>
        <th>Trecho</th>
        <th style="width:70px">Data</th>
        <th>Tipo / Camarote</th>
        <th class="c" style="width:48px">Polt.</th>
        <th class="c" style="width:70px">Embarque</th>
      </tr>
    </thead>
    <tbody>
      @forelse($linhas as $i => $l)
      <tr>
        <td class="c">{{ $i + 1 }}</td>
        <td><b>{{ $l->nome ?: '—' }}</b> <span class="muted">#{{ $l->numero }}</span></td>
        <td>{{ $l->documento ?: '—' }}</td>
        <td>{{ $l->origem }} → {{ $l->destino }}</td>
        <td>{{ $l->data ? $l->data->format('d/m/Y') : '—' }}</td>
        <td>{{ $l->tipo }}</td>
        <td class="c">{{ $l->poltrona ?: '—' }}</td>
        <td class="c">@if($l->embarcou)<span class="ok">✓ embarcou</span>@elseif($l->checkin)<span class="muted">check-in</span>@else<span class="no">—</span>@endif</td>
      </tr>
      @empty
      <tr><td colspan="8" class="c muted" style="padding:20px">Nenhum passageiro no filtro selecionado.</td></tr>
      @endforelse
    </tbody>
  </table>

  @if(request('print'))<script>window.addEventListener('load',()=>window.print());</script>@endif
</body>
</html>
