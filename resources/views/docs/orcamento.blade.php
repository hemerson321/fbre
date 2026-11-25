@php
  $moeda   = fn($v) => 'R$ '.number_format((float)$v,2,',','.');
  $emp     = $empresa->nomeExibicao();
  $cnpj    = $empresa->cnpj ? 'CNPJ '.$empresa->cnpj : '';
  $cidade  = trim(($empresa->cidade ?: '').($empresa->uf ? ' / '.$empresa->uf : ''));
  $fone    = $empresa->whatsapp ?: $empresa->telefone;
  $contato = collect([$cnpj, $cidade, $fone])->filter()->implode(' · ');
  $emit    = \Illuminate\Support\Carbon::now();
  $dias    = (int) ($empresa->config('orcamento_validade_dias', 7) ?: 7);
  $venc    = $dataViagem ? \Illuminate\Support\Carbon::parse($dataViagem) : $emit->copy()->addDays($dias);
  $saida   = $dataViagem ? \Illuminate\Support\Carbon::parse($dataViagem)->format('d/m/Y') : '—';
@endphp
<!doctype html><html lang="pt-br"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Orçamento {{ $num }}</title>
<style>
  @page{size:A4;margin:0}
  *{margin:0;padding:0;box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  body{font-family:Helvetica,Arial,sans-serif;color:#1f2937;font-size:12px;background:#eef1f5}
  .page{width:210mm;min-height:297mm;padding:24px;margin:0 auto;background:#eef1f5}
  .ticket{background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;box-shadow:0 8px 24px rgba(0,0,0,.06)}
  .thead{background:#fffaf0;border-bottom:2px dashed #f5a623;padding:18px 26px;display:flex;justify-content:space-between;align-items:center}
  .thead img{height:72px;width:auto}
  .doctype{text-align:right}
  .doctype .big{font-size:38px;font-weight:800;letter-spacing:1px;color:#13295e;line-height:.9}
  .doctype .num{font-size:14px;font-weight:800;color:#f5a623;letter-spacing:1px;margin-top:3px}
  .notch{height:0;position:relative}
  .notch:before,.notch:after{content:"";position:absolute;width:26px;height:26px;background:#eef1f5;border-radius:50%;top:-13px}
  .notch:before{left:-13px}.notch:after{right:-13px}
  .main{padding:22px 26px}
  .cli{margin-bottom:12px}
  .cli .l{font-size:8.5px;letter-spacing:1.2px;text-transform:uppercase;color:#9ca3af;font-weight:700}
  .cli .v{font-size:15px;font-weight:800;color:#13295e}
  .route{display:flex;align-items:center;gap:14px;margin-bottom:6px}
  .route .c{font-size:26px;font-weight:800;color:#13295e;font-family:'Courier New',monospace}
  .route .ar{flex:1;text-align:center;color:#f5a623;font-size:20px;font-weight:800;letter-spacing:2px}
  .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:16px 0 20px;border-top:1px solid #eef2f6;border-bottom:1px solid #eef2f6;padding:14px 0}
  .g .l{font-size:8.5px;letter-spacing:1.2px;text-transform:uppercase;color:#9ca3af;font-weight:700}
  .g .v{font-size:13px;font-weight:800;color:#13295e;margin-top:3px}
  table{width:100%;border-collapse:collapse;font-size:12px}
  th{text-align:left;font-size:9px;letter-spacing:1px;text-transform:uppercase;color:#6b7280;border-bottom:2px solid #e5e7eb;padding:7px 4px}
  th.num,td.num{text-align:right;font-family:'Courier New',monospace}
  td{padding:9px 4px;border-bottom:1px dotted #e5e7eb}
  .stub{background:#fff7ed;border:2px dashed #f5a623;border-radius:12px;margin:18px 26px 0;padding:14px 18px;display:flex;justify-content:space-between;align-items:center}
  .stub .t{font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#b45309;font-weight:800}
  .stub .v{font-size:26px;font-weight:800;color:#13295e;font-family:'Courier New',monospace}
  .valid{text-align:center;color:#94a3b8;font-size:10px;padding:16px 26px 20px;border-top:1px dashed #eef2f6;margin-top:10px}
  .valid b{color:#13295e}
</style></head><body>
<div id="folha" class="page">
 <div class="ticket">
  <div class="thead"><img src="{{ $logo }}" alt="{{ $emp }}"><div class="doctype"><div class="big">ORÇAMENTO</div><div class="num">Nº {{ $num }}</div></div></div>
  <div class="notch"></div>
  <div class="main">
   <div class="cli"><div class="l">Cliente</div><div class="v">{{ $cliente }}@if($doc)<span style="font-weight:600;color:#6b7280"> · {{ $doc }}</span>@endif</div></div>
   <div class="route"><div class="c">{{ $origem }}</div><div class="ar">») ») »)</div><div class="c">{{ $destino }}</div></div>
   <div class="grid">
    <div class="g"><div class="l">Embarcação</div><div class="v">{{ $emb ?: '—' }}</div></div>
    <div class="g"><div class="l">Saída</div><div class="v">{{ $saida }}</div></div>
    <div class="g"><div class="l">Passageiros</div><div class="v">{{ $pax }}</div></div>
    <div class="g"><div class="l">Emitido</div><div class="v">{{ $emit->format('d/m/Y') }}</div></div>
   </div>
   <table>
    <thead><tr><th>Descrição</th><th class="num">Qtd</th><th class="num">Valor unit.</th><th class="num">Total</th></tr></thead>
    <tbody>
     @forelse($itens as $it)
      <tr><td>{{ $it['d'] }}</td><td class="num">{{ $it['q'] }}</td><td class="num">{{ $moeda($it['u']) }}</td><td class="num">{{ $moeda($it['t']) }}</td></tr>
     @empty
      <tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:18px">Nenhum item no orçamento.</td></tr>
     @endforelse
    </tbody>
   </table>
  </div>
  <div class="stub"><div class="t">Total do orçamento</div><div class="v">{{ $moeda($total) }}</div></div>
  <div class="valid">Orçamento válido até <b>{{ $venc->format('d/m/Y') }}</b> · não garante reserva até confirmação do pagamento{{ $contato ? ' · '.$contato : '' }}</div>
 </div>
</div>
@include('docs.acoes', ['arquivo' => 'orcamento-'.$num.'.pdf', 'titulo' => 'Orçamento '.$num])
</body></html>
