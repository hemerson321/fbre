<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Prestação de contas - {{ $embarcacao->nome }}</title>
@php $moeda = fn($v) => number_format((float)$v,2,',','.'); @endphp
<style>
  *{box-sizing:border-box}
  body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:18px;color:#111;font-size:12px}
  .top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;border-bottom:2px solid #111;padding-bottom:8px;margin-bottom:10px}
  .top h1{font-size:17px;margin:0}
  .muted{color:#555;font-size:11px}
  .logo{height:46px;width:46px;object-fit:cover;border-radius:8px}
  .resumo{display:flex;flex-wrap:wrap;gap:18px;margin:10px 0 14px;padding:10px 12px;border:1px solid #ddd;border-radius:8px;background:#f7f9fc}
  .resumo .b{font-size:11px;color:#555;text-transform:uppercase}
  .resumo .v{font-size:15px;font-weight:bold}
  .pagar{color:#b45309}
  h2{font-size:13px;margin:16px 0 6px;padding:5px 8px;background:#0b1a33;color:#fff;border-radius:6px;display:flex;justify-content:space-between}
  table{width:100%;border-collapse:collapse;margin-bottom:4px}
  th,td{text-align:left;padding:5px 6px;border-bottom:1px solid #e3e3e3;vertical-align:top}
  th{background:#f1f1f1;font-size:10px;text-transform:uppercase;letter-spacing:.03em}
  td.r,th.r{text-align:right}
  tr:nth-child(even) td{background:#fafafa}
  tfoot td{font-weight:bold;border-top:2px solid #111;background:#fff!important}
  .dono{color:#067d3b;font-weight:bold}
  .obs{font-size:10px;color:#666;margin-top:14px;border-top:1px solid #ddd;padding-top:8px}
  .acoes{margin-bottom:12px}
  .btn{display:inline-block;background:#059669;color:#fff;border:0;border-radius:8px;padding:8px 14px;font-weight:bold;cursor:pointer;text-decoration:none;font-size:13px}
  .btn.g{background:#fff;color:#333;border:1px solid #ccc}
  @media print{ .acoes{display:none} body{padding:0} }
</style>
</head>
<body>
  @php
    $u = auth()->user();
    $qs = array_filter(['transporte_id' => $embarcacao->id, 'data_viagem' => $dataViagem ?? null, 'viagem_id' => $viagemId ?? null]);
    $voltar = ($u && ($u->isAdmin() || $u->isSuper()))
      ? route('admin.acertos.dono-relatorio', $embarcacao).(($dataViagem ?? null) ? '?data_viagem='.$dataViagem : ($viagemId ? '?viagem_id='.$viagemId : ''))
      : route('meu-transporte', $qs);
  @endphp
  <div class="acoes">
    <button class="btn" onclick="window.print()">🖨 Imprimir / Salvar PDF</button>
    <a class="btn g" href="{{ $voltar }}">← Voltar</a>
  </div>

  <div class="top">
    <div>
      <h1>Prestação de contas — {{ $embarcacao->nome }}</h1>
      <div class="muted">
        Dono: <b>{{ $embarcacao->dono_nome ?: '—' }}</b> · Comissão da empresa: {{ $pct }}%<br>
        Emitido em {{ now()->format('d/m/Y H:i') }}@if($empresa) · {{ $empresa->nomeExibicao() }}@endif
      </div>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
      @if($embarcacao->logo)<img class="logo" src="/{{ $embarcacao->logo }}" alt="">@endif
      @if($empresa?->logo)<img class="logo" src="/{{ $empresa->logo }}" alt="">@endif
    </div>
  </div>

  <div class="resumo">
    <div><div class="b">Total vendido</div><div class="v">R$ {{ $moeda($bruto) }}</div></div>
    <div><div class="b">Comissão da empresa ({{ $pct }}%)</div><div class="v">R$ {{ $moeda($comissao) }}</div></div>
    <div><div class="b">A pagar ao dono</div><div class="v pagar">R$ {{ $moeda($dono) }}</div></div>
  </div>

  {{-- BALCÃO --}}
  <h2><span>🏬 Vendas de balcão (empresa) — {{ $balcao->count() }}</span><span>Dono: R$ {{ $moeda($donoBalcao) }}</span></h2>
  <table>
    <thead><tr><th>Nº</th><th>Passagem</th><th>Trecho</th><th>Data</th><th>Operador</th><th class="r">Valor</th><th class="r">Com. empresa</th><th class="r">Dono</th></tr></thead>
    <tbody>
      @forelse($balcao as $i => $p)
      <tr>
        <td>{{ $i+1 }}</td>
        <td>#{{ $p->numeroFormatado() }}</td>
        <td>{{ $p->origem }} → {{ $p->destino }}</td>
        <td>{{ $p->created_at->format('d/m/Y') }}</td>
        <td>{{ $p->operador?->name ?: '—' }}</td>
        <td class="r">R$ {{ $moeda($p->total) }}</td>
        <td class="r">R$ {{ $moeda($p->total - $p->dono_valor) }}</td>
        <td class="r dono">R$ {{ $moeda($p->dono_valor) }}</td>
      </tr>
      @empty
      <tr><td colspan="8" style="text-align:center;color:#999">Sem vendas de balcão.</td></tr>
      @endforelse
    </tbody>
    @if($balcao->isNotEmpty())
    <tfoot><tr><td colspan="5">Subtotal balcão</td><td class="r">R$ {{ $moeda($brutoBalcao) }}</td><td class="r">R$ {{ $moeda($brutoBalcao - $donoBalcao) }}</td><td class="r dono">R$ {{ $moeda($donoBalcao) }}</td></tr></tfoot>
    @endif
  </table>

  {{-- AGENTES --}}
  <h2><span>🤝 Vendas de agentes (repasse) — {{ $agentes->count() }}</span><span>Dono: R$ {{ $moeda($donoAgentes) }}</span></h2>
  <table>
    <thead><tr><th>Nº</th><th>Passagem</th><th>Trecho</th><th>Data</th><th>Agente</th><th class="r">Valor</th><th class="r">Com. agente</th><th class="r">Dono</th></tr></thead>
    <tbody>
      @forelse($agentes as $i => $p)
      <tr>
        <td>{{ $i+1 }}</td>
        <td>#{{ $p->numeroFormatado() }}</td>
        <td>{{ $p->origem }} → {{ $p->destino }}</td>
        <td>{{ $p->created_at->format('d/m/Y') }}</td>
        <td>{{ $p->operador?->name ?: '—' }}</td>
        <td class="r">R$ {{ $moeda($p->total) }}</td>
        <td class="r">R$ {{ $moeda($p->total - $p->dono_valor) }}</td>
        <td class="r dono">R$ {{ $moeda($p->dono_valor) }}</td>
      </tr>
      @empty
      <tr><td colspan="8" style="text-align:center;color:#999">Sem vendas de agentes.</td></tr>
      @endforelse
    </tbody>
    @if($agentes->isNotEmpty())
    <tfoot><tr><td colspan="5">Subtotal agentes</td><td class="r">R$ {{ $moeda($brutoAgentes) }}</td><td class="r">R$ {{ $moeda($brutoAgentes - $donoAgentes) }}</td><td class="r dono">R$ {{ $moeda($donoAgentes) }}</td></tr></tfoot>
    @endif
  </table>

  <div class="obs">
    <b>Como é calculado:</b> em vendas de <b>balcão</b> (feitas pela empresa) o dono recebe o valor da passagem menos a comissão da empresa ({{ $pct }}%).
    Em vendas de <b>agentes de repasse</b>, a empresa apenas intermedia: o dono recebe o repasse integral e o agente fica com a comissão dele.
    Total a pagar ao dono: <b>R$ {{ $moeda($dono) }}</b>.
  </div>
</body>
</html>
