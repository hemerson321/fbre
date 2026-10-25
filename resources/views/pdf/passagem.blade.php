<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><style>
  * { font-family: "DejaVu Sans", sans-serif; }
  body { color:#0f172a; font-size:12px; margin:0; }
  .cab { background:#0b2a4a; color:#fff; padding:14px 18px; }
  .cab .nome { font-size:18px; font-weight:bold; }
  .cab .sub { font-size:11px; opacity:.85; }
  .wrap { padding:16px 18px; }
  .tit { font-size:16px; font-weight:bold; color:#0b2a4a; margin:0 0 2px; }
  .num { font-size:20px; font-weight:bold; }
  table { width:100%; border-collapse:collapse; margin:6px 0; }
  td { padding:3px 0; vertical-align:top; }
  .r { text-align:right; }
  .b { font-weight:bold; }
  .linha { border-top:1px solid #e2e8f0; margin:8px 0; }
  .qrbox { text-align:center; margin-top:10px; }
  .qrbox img { width:150px; height:150px; }
  .muted { color:#64748b; font-size:11px; }
  .total { font-size:15px; font-weight:bold; color:#0b7a3b; }
</style></head><body>
  <div class="cab">
    <div class="nome">{{ $empresa?->nomeExibicao() ?? 'FBRE' }}</div>
    <div class="sub">Comprovante de passagem</div>
  </div>
  <div class="wrap">
    <div class="num">Nº {{ $p->numeroFormatado() }}</div>
    <div class="muted">Emitido em {{ $p->created_at?->format('d/m/Y H:i') }} · Operador: {{ $p->operador?->name }}</div>
    <div class="linha"></div>

    <table>
      <tr><td class="b">Trecho</td><td class="r">{{ $p->origem }} → {{ $p->destino }}</td></tr>
      @if($p->data_viagem)<tr><td class="b">Data</td><td class="r">{{ \Carbon\Carbon::parse($p->data_viagem)->format('d/m/Y') }}</td></tr>@endif
      @if($p->embarcacao)<tr><td class="b">Embarcação</td><td class="r">{{ $p->embarcacao->nome }}</td></tr>@endif
      <tr><td class="b">Comprador</td><td class="r">{{ $p->comprador_nome }}</td></tr>
    </table>

    <div class="linha"></div>
    <div class="b">Passageiros</div>
    <table>
      @foreach($p->passageiros as $pax)
      <tr>
        <td>{{ $pax->nome }} <span class="muted">({{ $pax->tipo }})</span>@if($pax->documento)<br><span class="muted">Doc: {{ $pax->documento }}</span>@endif</td>
        <td class="r">R$ {{ number_format($pax->valor_passagem + $pax->valor_taxa + $pax->valor_alimentacao, 2, ',', '.') }}</td>
      </tr>
      @endforeach
    </table>

    <div class="linha"></div>
    <table>
      <tr><td class="b total">TOTAL</td><td class="r total">R$ {{ number_format($p->total, 2, ',', '.') }}</td></tr>
      <tr><td>Pago</td><td class="r">R$ {{ number_format($p->pago, 2, ',', '.') }}</td></tr>
    </table>

    @if($qr)
    <div class="qrbox">
      <img src="{{ $qr }}">
      <div class="muted">Apresente este QR no embarque (check-in)</div>
    </div>
    @endif
  </div>
</body></html>
