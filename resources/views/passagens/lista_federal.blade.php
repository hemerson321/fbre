<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lista de Passageiros (Passenger List)</title>
<style>
  *{box-sizing:border-box}
  body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:18px;color:#111;font-size:12px}
  .acoes{margin-bottom:12px}
  .btn{display:inline-block;background:#059669;color:#fff;border:0;border-radius:8px;padding:8px 14px;font-weight:bold;cursor:pointer;text-decoration:none;font-size:13px}
  .btn.g{background:#fff;color:#333;border:1px solid #ccc}
  .cab{text-align:center;margin-bottom:10px}
  .cab h1{font-size:15px;margin:0 0 4px}
  .cab .l{font-size:11px;color:#222}
  table{width:100%;border-collapse:collapse;margin-top:4px}
  th,td{text-align:left;padding:5px 6px;border:1px solid #bbb;vertical-align:top;font-size:11px}
  th{background:#f2f2f2;font-size:10px}
  th .en{display:block;font-weight:normal;font-style:italic;color:#555;font-size:9px}
  td.c,th.c{text-align:center}
  .addr{font-size:9px;color:#666;margin-top:2px}
  @media print{ .acoes{display:none} body{padding:0 6px} }
</style>
</head>
@php $ord = $linhas->sortBy('nome')->values(); @endphp
<body>
  <div class="acoes">
    <button class="btn" onclick="window.print()">🖨 Imprimir / Salvar PDF</button>
  </div>

  <div class="cab">
    <h1>Lista de Passageiros <i style="font-weight:normal">(Passenger List)</i></h1>
    <div class="l">Nº de Passageiros <i>(Number of Passenger)</i>: <b>{{ $ord->count() }}</b> · <b>{{ strtoupper($cab['embarcacao']) }}</b> · Data viagem: <b>{{ $cab['data'] ? $cab['data']->format('d/m/Y') : ($cab['periodo'] ?: '—') }}</b> · Destino: <b>{{ $cab['destino'] }}</b></div>
  </div>

  <table>
    <thead>
      <tr>
        <th class="c" style="width:34px">Nr.</th>
        <th>Nome completo <span class="en">(Full name)</span></th>
        <th style="width:120px">Data de nascimento <span class="en">(Date of birth)</span></th>
        <th class="c" style="width:90px">Sexo (M / F) <span class="en">(Gender)</span></th>
      </tr>
    </thead>
    <tbody>
      @forelse($ord as $i => $l)
      <tr>
        <td class="c">{{ $i + 1 }}</td>
        <td>
          <b>{{ strtoupper($l->nome ?: '—') }}</b>
          <div class="addr">@if($l->telefone)Tel.: {{ $l->telefone }}@else , - - - CEP.: Tel.:@endif</div>
        </td>
        <td>{{ $l->nascimento ? $l->nascimento->format('d/m/Y') : '—' }}</td>
        <td class="c">{{ $l->sexo ?: '—' }}</td>
      </tr>
      @empty
      <tr><td colspan="4" class="c" style="padding:22px;color:#777">Nenhum passageiro no filtro selecionado.</td></tr>
      @endforelse
    </tbody>
  </table>

  @if(request('print'))<script>window.addEventListener('load',()=>window.print());</script>@endif
</body>
</html>
