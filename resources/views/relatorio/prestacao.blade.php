<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Prestação de contas</title>
<style>
  *{box-sizing:border-box}
  body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:18px;color:#111;font-size:12px}
  .acoes{margin-bottom:12px}
  .btn{display:inline-block;background:#059669;color:#fff;border:0;border-radius:8px;padding:8px 14px;font-weight:bold;cursor:pointer;text-decoration:none;font-size:13px}
  .btn.g{background:#fff;color:#333;border:1px solid #ccc}
  .top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;border-bottom:2px solid #111;padding-bottom:8px;margin-bottom:10px}
  .top h1{font-size:16px;margin:0}
  .muted{color:#555;font-size:11px}
  .tit{text-align:center;font-weight:bold;font-size:14px;margin:6px 0 10px;text-transform:uppercase;letter-spacing:.04em}
  table{width:100%;border-collapse:collapse;margin-top:4px}
  th,td{text-align:left;padding:5px 6px;border-bottom:1px solid #ddd;font-size:11px;vertical-align:top}
  th{background:#f3f3f3;font-size:9px;text-transform:uppercase;letter-spacing:.03em}
  td.r,th.r{text-align:right}
  tr:nth-child(even) td{background:#fafafa}
  .totais{margin-top:12px;border-top:2px solid #111;padding-top:8px;display:flex;flex-wrap:wrap;gap:18px;justify-content:flex-end}
  .tot{min-width:120px;text-align:right}
  .tot .l{font-size:10px;color:#555;text-transform:uppercase}
  .tot .v{font-size:16px;font-weight:bold}
  .tot.forte .v{color:#065f46}
  .ass{margin-top:40px;display:flex;gap:40px;justify-content:space-between}
  .ass .lin{flex:1;text-align:center;border-top:1px solid #111;padding-top:4px;font-size:11px;color:#333}
  @media print{ .acoes{display:none} body{padding:0 6px} }
</style>
</head>
<body>
  <div class="acoes">
    <button class="btn" onclick="window.print()">🖨 Imprimir / Salvar PDF</button>
    @if(!empty($voltar))<a class="btn g" href="{{ $voltar }}">← Voltar</a>@endif
  </div>

  <div class="top">
    <div style="display:flex;gap:10px;align-items:center">
      @if($empresa?->logo)<img src="/{{ $empresa->logo }}" style="height:44px;width:44px;object-fit:contain;border:1px solid #ddd;border-radius:6px;background:#fff">@endif
      <div>
        <h1>{{ strtoupper($empresa?->nomeExibicao() ?? 'FBRE') }}</h1>
        <div class="muted">{{ $subtitulo }}</div>
        <div class="muted">{{ $periodo }} · Emitido {{ now()->format('d/m/Y H:i') }}</div>
      </div>
    </div>
  </div>

  <div class="tit">{{ $titulo }}</div>

  <table>
    <thead>
      <tr>
        <th style="width:95px">Data</th>
        <th>Descrição</th>
        <th style="width:90px">Ref.</th>
        <th class="r" style="width:110px">Valor</th>
      </tr>
    </thead>
    <tbody>
      @forelse($linhas as $l)
      <tr>
        <td>{{ $l->data ? \Carbon\Carbon::parse($l->data)->format('d/m/Y H:i') : '—' }}</td>
        <td>{{ $l->descricao }}</td>
        <td class="muted">{{ $l->ref }}</td>
        <td class="r">R$ {{ number_format((float)$l->valor,2,',','.') }}</td>
      </tr>
      @empty
      <tr><td colspan="4" style="text-align:center;color:#777;padding:20px">Sem movimentos no período.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="totais">
    @foreach($totais as $t)
    <div class="tot {{ !empty($t['forte']) ? 'forte' : '' }}">
      <div class="l">{{ $t['label'] }}</div>
      <div class="v">R$ {{ number_format((float)$t['valor'],2,',','.') }}</div>
    </div>
    @endforeach
  </div>

  <div class="ass">
    <div class="lin">{{ $assinatura ?? 'Responsável' }}</div>
    <div class="lin">Conferido por</div>
  </div>

  @if(request('print'))<script>window.addEventListener('load',()=>window.print());</script>@endif
</body>
</html>
