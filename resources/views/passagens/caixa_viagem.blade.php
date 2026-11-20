<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><title>Caixa por viagem</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  *{box-sizing:border-box}
  body{font-family:-apple-system,"Segoe UI",Arial,sans-serif;margin:0;padding:16px;color:#111;font-size:12px}
  h1{font-size:17px;margin:0 0 2px}
  .sub{color:#555;font-size:12px;margin-bottom:12px}
  table{width:100%;border-collapse:collapse}
  th,td{text-align:left;padding:3px 6px;border-bottom:1px solid #e5e7eb;vertical-align:top}
  th{background:#f1f5f9;font-size:10px;text-transform:uppercase;letter-spacing:.03em;color:#374151;position:sticky;top:0}
  td.r,th.r{text-align:right;white-space:nowrap}
  tr.canc{background:#fee2e2}
  tr.canc td{color:#b91c1c}
  tr.idoso{background:#dcfce7}
  tr.pcd{background:#fef9c3}
  tr.mun{background:#dbeafe}
  .nome{font-weight:600}
  .tag{font-size:9px;font-weight:800;padding:0 4px;border-radius:4px}
  .totais{margin-top:16px;display:flex;gap:34px;flex-wrap:wrap}
  .bloco{min-width:240px}
  .bloco h2{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#374151;border-bottom:2px solid #111;padding-bottom:3px;margin:0 0 6px}
  .lin{display:flex;justify-content:space-between;gap:16px;padding:2px 0}
  .lin.g{font-weight:900;font-size:15px;border-top:2px solid #111;margin-top:5px;padding-top:6px}
  .leg{margin-top:12px;font-size:10px;color:#555}
  .leg span{display:inline-block;padding:1px 7px;border-radius:4px;margin-right:6px}
  .bar{margin-bottom:12px;display:flex;gap:8px}
  .btn{display:inline-block;padding:7px 14px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;cursor:pointer;font-weight:700;text-decoration:none;color:#111}
  .btn.p{background:#0b1a33;color:#fff;border-color:#0b1a33}
  @media print{ body{padding:0} .noprint{display:none} th{position:static} }
</style></head>
<body>
<div class="bar noprint">
  <a href="javascript:window.print()" class="btn p">🖨 Imprimir</a>
  <a href="{{ route('passagens.index') }}" class="btn">← Voltar</a>
</div>
@php $e = \App\Support\Tenant::empresa(); @endphp
<h1>💰 Caixa por viagem{{ $embs->count()===1 ? ' · '.strtoupper($embs->first()) : '' }}</h1>
<div class="sub">
  @if($datas->count())Data da viagem: <b>{{ $datas->map(fn($d)=>\Illuminate\Support\Carbon::parse($d)->format('d/m/Y'))->implode(', ') }}</b> · @endif
  {{ $linhas->count() }} linha(s) · Emitido {{ now()->format('d/m/Y H:i') }}@if($e) · {{ $e->nomeExibicao() }}@endif
</div>

@include('passagens._manifesto_tabela', ['linhas' => $linhas])

<div class="totais">
  <div class="bloco">
    <h2>Contagem</h2>
    <div class="lin"><span>Total de passageiros</span><b>{{ $nPax }}</b></div>
    <div class="lin"><span>Idosos</span><b>{{ $nIdosos }}</b></div>
    <div class="lin"><span>Bebês de colo</span><b>{{ $nColos }}</b></div>
    <div class="lin"><span>Devolvidas</span><b>{{ $nDevolvidas }}</b></div>
  </div>
  <div class="bloco">
    <h2>Caixa da viagem</h2>
    @foreach($totForma as $meio => $val)
    <div class="lin"><span>{{ \App\Models\Pagamento::MEIOS[$meio] ?? ucfirst($meio) }}</span><b>R$ {{ number_format($val,2,',','.') }}</b></div>
    @endforeach
    @if($totDesconto > 0.009)<div class="lin" style="color:#b45309"><span>Desconto concedido</span><b>- R$ {{ number_format($totDesconto,2,',','.') }}</b></div>@endif
    <div class="lin g"><span>Total recebido</span><span>R$ {{ number_format($totRecebido,2,',','.') }}</span></div>
    @if($totDevolvido > 0.009)<div class="lin" style="color:#b91c1c"><span>Devolvido (fora do caixa)</span><b>- R$ {{ number_format($totDevolvido,2,',','.') }}</b></div>@endif
  </div>
</div>

<div class="leg">
  Legenda:
  <span style="background:#dbeafe">Agente / Município</span>
  <span style="background:#dcfce7">Idoso</span>
  <span style="background:#fef9c3">PCD</span>
  <span style="background:#fee2e2;color:#b91c1c">Cancelado / Devolvido</span>
</div>

@if($auto ?? false)<script>window.addEventListener('load',function(){setTimeout(function(){window.print();},300);});</script>@endif
</body></html>
