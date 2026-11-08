<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lista de Passageiros</title>
<style>
  *{box-sizing:border-box}
  body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:18px;color:#111;font-size:12px}
  .acoes{margin-bottom:12px}
  .btn{display:inline-block;background:#059669;color:#fff;border:0;border-radius:8px;padding:8px 14px;font-weight:bold;cursor:pointer;text-decoration:none;font-size:13px}
  .btn.g{background:#fff;color:#333;border:1px solid #ccc}
  .cab{text-align:center;margin-bottom:12px}
  .cab h1{font-size:18px;margin:0 0 2px;letter-spacing:.02em}
  .cab .l{font-size:12px;color:#333;margin:1px 0}
  .cab .esc{font-size:10px;color:#555;margin-top:3px}
  .cab .tit{font-weight:bold;margin-top:6px;border-top:1px solid #111;border-bottom:1px solid #111;padding:3px 0;font-size:13px}
  .grp{font-weight:bold;background:#eee;padding:4px 6px;margin-top:12px;border-left:3px solid #111;font-size:12px}
  table{width:100%;border-collapse:collapse;margin-top:2px}
  th,td{text-align:left;padding:4px 6px;border-bottom:1px solid #ddd;font-size:11px}
  th{background:#f5f5f5;font-size:9px;text-transform:uppercase;letter-spacing:.03em}
  .tot{font-size:10px;color:#444;padding:3px 6px;font-style:italic}
  @media print{ .acoes{display:none} body{padding:0 6px} .grp{-webkit-print-color-adjust:exact;print-color-adjust:exact} }
</style>
</head>
@php
  $dias = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
  $ordem = collect($cab['escalas'])->values()->flip();
  $grupos = $linhas->sortBy(fn ($l) => str_pad((string)($ordem[$l->destino] ?? 999), 3, '0', STR_PAD_LEFT).'_'.$l->nome)->groupBy('trecho');
@endphp
<body>
  <div class="acoes">
    <button class="btn" onclick="window.print()">🖨 Imprimir / Salvar PDF</button>
    <a class="btn g" href="{{ url()->previous() }}">← Voltar</a>
  </div>

  <div class="cab">
    <h1>{{ strtoupper($cab['embarcacao']) }}</h1>
    <div class="l">Rota: {{ $cab['rota'] }}</div>
    <div class="l">Data Viagem: {{ $cab['data'] ? $cab['data']->format('d/m/Y').' ('.$dias[(int)$cab['data']->dayOfWeek].')' : ($cab['periodo'] ?: '—') }}</div>
    @if(count($cab['escalas']))<div class="esc">Escalas: {{ implode(' | ', $cab['escalas']) }}</div>@endif
    <div class="tit">Lista de Passageiros</div>
  </div>

  @forelse($grupos as $trecho => $itens)
    <div class="grp">{{ $trecho }}</div>
    <table>
      <thead>
        <tr>
          <th style="width:70px">Ticket</th>
          <th>Nome</th>
          <th style="width:120px">RG</th>
          <th style="width:120px">Nascimento</th>
          <th style="width:100px">Nacionalidade</th>
        </tr>
      </thead>
      <tbody>
        @foreach($itens as $l)
        <tr>
          <td>{{ $l->ticket }}</td>
          <td><b>{{ strtoupper($l->nome ?: '—') }}</b></td>
          <td>{{ $l->documento ?: '—' }}</td>
          <td>{{ $l->nascimento ? $l->nascimento->format('d/m/Y').' ('.$l->nascimento->age.')' : '—' }}</td>
          <td>{{ strtoupper($l->nacionalidade) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    <div class="tot">Total passageiros: {{ $itens->count() }}</div>
  @empty
    <p style="text-align:center;color:#777;padding:24px">Nenhum passageiro no filtro selecionado.</p>
  @endforelse

  @if(request('print'))<script>window.addEventListener('load',()=>window.print());</script>@endif
</body>
</html>
