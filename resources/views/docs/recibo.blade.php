@php
  $moeda = fn($v) => 'R$ '.number_format((float)$v,2,',','.');
  $emp    = $empresa->nomeExibicao();
  $sub    = $empresa->config('ramo', 'Transporte Fluvial de Passageiros');
  $cnpj   = $empresa->cnpj ? 'CNPJ '.$empresa->cnpj : '';
  $cidade = trim(($empresa->cidade ?: '').($empresa->uf ? ' / '.$empresa->uf : ''));
  $fone   = $empresa->whatsapp ?: $empresa->telefone;
  $contato= collect([$cnpj, $cidade, $fone])->filter()->implode(' · ');
  $num    = $p->numeroFormatado();
  $cliente= $p->comprador_nome ?: (optional($p->passageiros->first())->nome ?: 'Consumidor');
  $itens  = $p->passageiros->where('cancelado', false)->values();
  $paxQtd = (int) ($p->qtd_passageiros ?: $itens->count());
  $emb    = $p->embarcacao?->nome ?: '—';
  $saida  = $p->data_viagem ? \Illuminate\Support\Carbon::parse($p->data_viagem)->format('d/m/Y') : '—';
  $forma  = \App\Models\Passagem::FORMAS[$p->forma] ?? '—';
  $pagoVal = (float) $p->pago;
  $devido  = (float) $p->devido;
  $quitado = $devido <= 0.009;
  $dataExt = \Illuminate\Support\Carbon::now()->locale('pt_BR')->isoFormat('D [de] MMMM [de] YYYY');
@endphp
<!doctype html><html lang="pt-br"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recibo {{ $num }}</title>
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
  .route{display:flex;align-items:center;gap:14px;margin-bottom:10px}
  .route .c{font-size:24px;font-weight:800;color:#13295e;font-family:'Courier New',monospace}
  .route .ar{flex:1;text-align:center;color:#f5a623;font-size:19px;font-weight:800;letter-spacing:2px}
  .stmt{background:#f6f9ff;border:1px solid #e3ebf7;border-radius:10px;padding:12px 16px;font-size:12.5px;line-height:1.5;color:#334155;margin-bottom:16px}
  .stmt b{color:#13295e}
  .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:0 0 16px;border-top:1px solid #eef2f6;border-bottom:1px solid #eef2f6;padding:14px 0}
  .g .l{font-size:8.5px;letter-spacing:1.2px;text-transform:uppercase;color:#9ca3af;font-weight:700}
  .g .v{font-size:13px;font-weight:800;color:#13295e;margin-top:3px}
  table{width:100%;border-collapse:collapse;font-size:12px}
  th{text-align:left;font-size:9px;letter-spacing:1px;text-transform:uppercase;color:#6b7280;border-bottom:2px solid #e5e7eb;padding:7px 4px}
  th.num,td.num{text-align:right;font-family:'Courier New',monospace}
  td{padding:9px 4px;border-bottom:1px dotted #e5e7eb}
  .stub{background:#fff7ed;border:2px dashed #f5a623;border-radius:12px;margin:18px 26px 0;padding:14px 18px;display:flex;justify-content:space-between;align-items:center}
  .stub .t{font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#b45309;font-weight:800}
  .stub .v{font-size:26px;font-weight:800;color:#13295e;font-family:'Courier New',monospace}
  .saldo{margin:8px 26px 0;text-align:right;font-size:11px;color:#b45309;font-weight:700}
  .sign{display:flex;justify-content:space-between;align-items:flex-end;padding:24px 26px 6px}
  .pago{border:3px solid #16a34a;color:#16a34a;border-radius:10px;padding:6px 18px;font-size:24px;font-weight:800;letter-spacing:4px;transform:rotate(-7deg);opacity:.9}
  .signbox{text-align:center}
  .signbox .line{width:230px;border-top:1.5px solid #94a3b8;margin:0 auto 6px}
  .signbox .nm{font-size:12px;font-weight:800;color:#13295e}
  .signbox .r{font-size:10px;color:#9ca3af}
  .place{font-size:11px;color:#64748b;text-align:right;line-height:1.5}
  .valid{text-align:center;color:#94a3b8;font-size:10px;padding:16px 26px 20px;border-top:1px dashed #eef2f6;margin-top:10px}
  .valid b{color:#13295e}
</style></head><body>
<div id="folha" class="page">
 <div class="ticket">
  <div class="thead"><img src="{{ $logo }}" alt="{{ $emp }}"><div class="doctype"><div class="big">RECIBO</div><div class="num">Nº {{ $num }}</div></div></div>
  <div class="notch"></div>
  <div class="main">
   <div class="cli"><div class="l">Recebemos de</div><div class="v">{{ $cliente }}@if($p->comprador_documento)<span style="font-weight:600;color:#6b7280"> · {{ $p->comprador_documento }}</span>@endif</div></div>
   <div class="route"><div class="c">{{ $p->origem }}</div><div class="ar">») ») »)</div><div class="c">{{ $p->destino }}</div></div>
   <div class="stmt">Recebemos a importância de <b>{{ $moeda($pagoVal) }}</b>, referente às passagens discriminadas abaixo.@if($quitado) <b>Pagamento confirmado.</b>@endif</div>
   <div class="grid">
    <div class="g"><div class="l">Embarcação</div><div class="v">{{ $emb }}</div></div>
    <div class="g"><div class="l">Saída</div><div class="v">{{ $saida }}</div></div>
    <div class="g"><div class="l">Passageiros</div><div class="v">{{ $paxQtd }}</div></div>
    <div class="g"><div class="l">Pagamento</div><div class="v">{{ $forma }}</div></div>
   </div>
   <table>
    <thead><tr><th>Passageiro</th><th>Poltrona</th><th class="num">Valor</th></tr></thead>
    <tbody>
     @forelse($itens as $pax)
      <tr><td>{{ $pax->nome ?: '—' }}</td><td>{{ $pax->poltrona ?: '—' }}</td><td class="num">{{ $moeda($pax->valor_integral ?? 0) }}</td></tr>
     @empty
      <tr><td colspan="2">Passagem {{ $p->origem }} → {{ $p->destino }}</td><td class="num">{{ $moeda($p->total) }}</td></tr>
     @endforelse
    </tbody>
   </table>
  </div>
  <div class="stub"><div class="t">Total pago</div><div class="v">{{ $moeda($pagoVal) }}</div></div>
  @unless($quitado)<div class="saldo">Saldo a pagar: {{ $moeda($devido) }}</div>@endunless
  <div class="sign">
   @if($quitado)<div class="pago">PAGO</div>@else<div></div>@endif
   <div class="signbox"><div class="line"></div><div class="nm">{{ $emp }}</div><div class="r">Assinatura / carimbo</div></div>
   <div class="place">{{ $cidade ?: 'Manaus / AM' }}<br>{{ $dataExt }}</div>
  </div>
  <div class="valid">Recibo referente ao pagamento das passagens acima@if($contato) · {{ $contato }}@endif</div>
 </div>
</div>
@include('docs.acoes', ['arquivo' => 'recibo-'.$num.'.pdf', 'titulo' => 'Recibo '.$num])
</body></html>
