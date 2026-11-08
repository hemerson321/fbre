<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lista por poltrona</title>
<style>
  *{box-sizing:border-box}
  body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:18px;color:#111;font-size:12px}
  .acoes{margin-bottom:12px}
  .btn{display:inline-block;background:#059669;color:#fff;border:0;border-radius:8px;padding:8px 14px;font-weight:bold;cursor:pointer;text-decoration:none;font-size:13px}
  .btn.g{background:#fff;color:#333;border:1px solid #ccc}
  .cab{border-bottom:2px solid #111;padding-bottom:6px;margin-bottom:8px;font-weight:bold;font-size:13px}
  table{width:100%;border-collapse:collapse}
  th,td{text-align:left;padding:5px 6px;border-bottom:1px solid #ddd;vertical-align:top;font-size:11px}
  th{background:#f5f5f5;font-size:9px;text-transform:uppercase;letter-spacing:.03em}
  td.c,th.c{text-align:center}
  .sub{font-size:9px;color:#777}
  .ok{color:#067d3b;font-weight:bold}
  .no{color:#b91c1c;font-weight:bold}
  @media print{ .acoes{display:none} body{padding:0 6px} }
</style>
</head>
@php
  $ord = $linhas->sortBy(function ($l) {
    $p = (string) ($l->poltrona ?: 'ZZZ');
    return preg_replace_callback('/\d+/', fn ($m) => str_pad($m[0], 3, '0', STR_PAD_LEFT), $p);
  })->values();
@endphp
<body>
  <div class="acoes">
    <button class="btn" onclick="window.print()">🖨 Imprimir / Salvar PDF</button>
    <a class="btn g" href="{{ url()->previous() }}">← Voltar</a>
  </div>

  <div class="cab">{{ strtoupper($cab['embarcacao']) }} — Data Viagem: {{ $cab['data'] ? $cab['data']->format('d/m/Y') : ($cab['periodo'] ?: '—') }} — {{ $cab['rota'] }}</div>

  <table>
    <thead>
      <tr>
        <th class="c" style="width:34px">Nr.</th>
        <th style="width:70px">Ticket</th>
        <th style="width:150px">Trecho</th>
        <th>Nome completo</th>
        <th class="c" style="width:60px">Poltrona</th>
        <th class="c" style="width:64px">Checkin?</th>
      </tr>
    </thead>
    <tbody>
      @forelse($ord as $i => $l)
      <tr>
        <td class="c">{{ $i + 1 }}</td>
        <td>{{ $l->ticket }}</td>
        <td>{{ $l->trecho }}</td>
        <td><b>{{ strtoupper($l->nome ?: '—') }}</b>@if($l->tipo && strtoupper($l->tipo) !== 'ADULTO')<div class="sub">{{ $l->tipo }}</div>@endif</td>
        <td class="c">{{ $l->poltrona ?: '—' }}</td>
        <td class="c">@if($l->embarcou || $l->checkin)<span class="ok">S</span>@else<span class="no">N</span>@endif</td>
      </tr>
      @empty
      <tr><td colspan="6" class="c" style="padding:22px;color:#777">Nenhum passageiro no filtro selecionado.</td></tr>
      @endforelse
    </tbody>
  </table>

  @if(request('print'))<script>window.addEventListener('load',()=>window.print());</script>@endif
</body>
</html>
