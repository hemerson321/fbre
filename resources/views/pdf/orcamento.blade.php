@php
  $moeda = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
@endphp
<!doctype html>
<html lang="pt-br"><head><meta charset="utf-8">
<style>
  @page { margin: 26px 30px; }
  body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; font-size: 11px; }
  table { width: 100%; border-collapse: collapse; }
  .topo td { vertical-align: middle; padding-bottom: 12px; border-bottom: 2px dashed #f5a623; }
  .logo { height: 62px; }
  .emp { font-size: 15px; font-weight: bold; color: #13295e; }
  .titulo { text-align: right; font-size: 28px; font-weight: bold; color: #13295e; letter-spacing: 1px; }
  .num { text-align: right; font-size: 12px; font-weight: bold; color: #f5a623; }
  .rot { font-size: 8px; letter-spacing: 1px; text-transform: uppercase; color: #9ca3af; font-weight: bold; }
  .cli { font-size: 14px; font-weight: bold; color: #13295e; }
  .rota { font-size: 20px; font-weight: bold; color: #13295e; }
  .seta { color: #f5a623; }
  .info td { padding: 9px 4px; border-top: 1px solid #eef2f6; border-bottom: 1px solid #eef2f6; }
  .info .v { font-size: 12px; font-weight: bold; color: #13295e; padding-top: 2px; }
  .itens th { text-align: left; font-size: 8px; letter-spacing: 1px; text-transform: uppercase; color: #6b7280; border-bottom: 2px solid #e5e7eb; padding: 6px 4px; }
  .itens td { padding: 8px 4px; border-bottom: 1px dotted #d1d5db; }
  .d { text-align: right; }
  .total td { background: #fff7ed; border: 2px dashed #f5a623; padding: 12px 16px; }
  .total .t { font-size: 10px; letter-spacing: 1px; text-transform: uppercase; color: #b45309; font-weight: bold; }
  .total .v { text-align: right; font-size: 22px; font-weight: bold; color: #13295e; }
  .rodape { text-align: center; color: #94a3b8; font-size: 9px; margin-top: 16px; }
  .rodape b { color: #13295e; }
</style></head><body>
<table class="topo"><tr>
  <td>@if($logo)<img class="logo" src="{{ $logo }}">@else<span class="emp">{{ $emp }}</span>@endif</td>
  <td><div class="titulo">ORÇAMENTO</div><div class="num">Nº {{ $num }}</div></td>
</tr></table>

<div style="margin-top:16px"><div class="rot">Cliente</div><div class="cli">{{ $cliente }}@if($doc) <span style="font-weight:normal;color:#6b7280;font-size:11px">· {{ $doc }}</span>@endif</div></div>

<div style="margin:14px 0 12px" class="rota">{{ $origem }} <span class="seta">&rarr;</span> {{ $destino }}</div>

<table class="info"><tr>
  <td><div class="rot">Embarcação</div><div class="v">{{ $emb ?: '—' }}</div></td>
  <td><div class="rot">Saída</div><div class="v">{{ $saida }}</div></td>
  <td><div class="rot">Passageiros</div><div class="v">{{ $pax }}</div></td>
  <td><div class="rot">Emitido</div><div class="v">{{ $emitido }}</div></td>
</tr></table>

<table class="itens" style="margin-top:16px">
  <thead><tr><th>Descrição</th><th class="d">Qtd</th><th class="d">Valor unit.</th><th class="d">Total</th></tr></thead>
  <tbody>
  @foreach($itens as $it)
    <tr><td>{{ $it['d'] }}</td><td class="d">{{ $it['q'] }}</td><td class="d">{{ $moeda($it['u']) }}</td><td class="d">{{ $moeda($it['t']) }}</td></tr>
  @endforeach
  </tbody>
</table>

<table class="total" style="margin-top:18px"><tr><td class="t" style="border-right:0">Total do orçamento</td><td class="v" style="border-left:0">{{ $moeda($total) }}</td></tr></table>

<div class="rodape">Orçamento válido até <b>{{ $validade }}</b> · não garante reserva até a confirmação do pagamento<br>{{ $contato }}</div>
</body></html>
