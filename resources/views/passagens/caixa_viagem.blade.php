<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8">
@php
  $modo = $modo ?? (($ehRelatorio ?? false) ? 'relatorio' : 'caixa');
  $titP = [['caixa'=>'Caixa por viagem','prestacao'=>'Pre-prestacao de contas','relatorio'=>'Relatorio por viagem'][$modo]];
  if($embs->count()) $titP[] = $embs->implode(', ');
  if($datas->count()) $titP[] = $datas->map(fn($d)=>\Illuminate\Support\Carbon::parse($d)->format('d-m-Y'))->implode(' ');
  elseif(!empty($f['inicio']) || !empty($f['fim'])) $titP[] = (!empty($f['inicio'])?\Illuminate\Support\Carbon::parse($f['inicio'])->format('d-m-Y'):'').(!empty($f['fim']) && ($f['fim']??'')!==($f['inicio']??'')?(' a '.\Illuminate\Support\Carbon::parse($f['fim'])->format('d-m-Y')):'');
  foreach(['Operador','Forma','Origem','Destino','Status','Cliente faturado'] as $fk){ if(!empty($filtros[$fk])) $titP[] = $filtros[$fk]; }
  $tituloPdf = \Illuminate\Support\Str::of(implode(' - ', array_filter($titP)))->replace(['/','\\',':','*','?','"','<','>','|'], '-');
@endphp
<title>{{ $tituloPdf }}</title>
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
  .prest{margin-top:16px;max-width:430px;border:2px solid #0b3b6f;border-radius:10px;padding:10px 14px;background:#f4f8ff}
  .prest-h{font-size:12px;font-weight:bold;color:#0b3b6f;letter-spacing:.03em;text-align:center;margin-bottom:8px}
  .prest-row{display:flex;justify-content:space-between;gap:16px;padding:4px 0;border-bottom:1px dotted #c3d2e6;font-size:12.5px}
  .prest-row b{white-space:nowrap}
  .prest-row.tot{border-top:2px solid #0b3b6f;border-bottom:none;margin-top:4px;padding-top:7px;font-weight:bold;color:#b45309}
  .prest-row.liq{border-bottom:none;margin-top:6px;padding:8px 10px;background:#eafaf0;border-radius:7px;font-weight:bold;font-size:15px;color:#067d3b}
  .leg span{display:inline-block;padding:1px 7px;border-radius:4px;margin-right:6px}
  .bar{margin-bottom:12px;display:flex;gap:8px}
  .btn{display:inline-block;padding:7px 14px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;cursor:pointer;font-weight:700;text-decoration:none;color:#111}
  .btn.p{background:#0b1a33;color:#fff;border-color:#0b1a33}
  @page{ size: A4 landscape; margin: 8mm; }
  @media print{ body{padding:0;font-size:11px} .noprint{display:none} th{position:static} }
</style></head>
<body>
<div class="bar noprint">
  <a href="javascript:window.print()" class="btn p">🖨 Imprimir</a>
  <a href="{{ route('passagens.index') }}" onclick="if(document.referrer){history.back();return false;}" class="btn">← Voltar</a>
</div>
@php $e = \App\Support\Tenant::empresa(); $rel = $ehRelatorio ?? false; @endphp
<div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
  @if($e?->logo)<img src="/{{ $e->logo }}" alt="" style="height:44px;max-width:130px;object-fit:contain">@endif
  <div>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      @php $mt = ['caixa'=>['💰 Caixa por viagem','CAIXA','background:#dcfce7;color:#166534'],'prestacao'=>['🧾 Pré-prestação de contas','PRÉ-PRESTAÇÃO · EM ABERTO','background:#fef3c7;color:#92400e'],'relatorio'=>['📋 Relatório por viagem','RELATÓRIO','background:#e0e7ff;color:#3730a3']][$modo]; @endphp
      <h1 style="margin:0">{{ $mt[0] }}</h1>
      <span style="font-size:10px;font-weight:900;letter-spacing:.08em;padding:2px 8px;border-radius:6px;{{ $mt[2] }}">{{ $mt[1] }}</span>
    </div>
    @if($e)<div style="font-size:12px;color:#555;font-weight:700">{{ $e->nomeExibicao() }}</div>@endif
  </div>
</div>
@if(!empty($filtros))
<div class="sub" style="margin-bottom:4px">
  <b>Filtros:</b>
  @foreach($filtros as $fk => $fv)<span style="display:inline-block;background:#eef2ff;color:#3730a3;border-radius:5px;padding:1px 7px;margin:1px 2px;font-weight:700">{{ $fk }}: {{ $fv }}</span>@endforeach
</div>
@endif
<div class="sub">
  @if($datas->count())Data da viagem: <b>{{ $datas->map(fn($d)=>\Illuminate\Support\Carbon::parse($d)->format('d/m/Y'))->implode(', ') }}</b> · @endif
  {{ $linhas->count() }} linha(s) · Emitido {{ now()->format('d/m/Y H:i') }}
</div>

@include('passagens._manifesto_tabela', ['linhas' => $linhas])

<div class="totais">
  <div class="bloco">
    <h2>Contagem</h2>
    <div class="lin"><span>Total de passageiros</span><b>{{ $nPax }}</b></div>
    <div class="lin"><span>Idosos</span><b>{{ $nIdosos }}</b></div>
    <div class="lin"><span>PCD</span><b>{{ $nPcd ?? 0 }}</b></div>
    <div class="lin"><span>Outros</span><b>{{ $nOutros ?? 0 }}</b></div>
    <div class="lin"><span>Bebês de colo</span><b>{{ $nColos }}</b></div>
    <div class="lin"><span>Devolvidas</span><b>{{ $nDevolvidas }}</b></div>
    <div class="lin"><span>Cancelados</span><b>{{ $nCancelados ?? 0 }}</b></div>
  </div>
  <div class="bloco">
    <h2>Caixa da viagem</h2>
    @foreach($totForma as $meio => $val)
    <div class="lin"><span>{{ \App\Models\Pagamento::MEIOS[$meio] ?? ucfirst($meio) }}</span><b>R$ {{ number_format($val,2,',','.') }}</b></div>
    @endforeach
    @if($totDesconto > 0.009)<div class="lin" style="color:#b45309"><span>Desconto concedido</span><b>- R$ {{ number_format($totDesconto,2,',','.') }}</b></div>@endif
    <div class="lin g"><span>Total recebido</span><span>R$ {{ number_format($totRecebido,2,',','.') }}</span></div>
    @if(($totCancelado ?? 0) > 0.009)<div class="lin" style="color:#b91c1c"><span>Cancelado (fora do caixa)</span><b>- R$ {{ number_format($totCancelado,2,',','.') }}</b></div>@endif
    @if($totDevolvido > 0.009)<div class="lin" style="color:#b91c1c"><span>Devolvido (fora do caixa)</span><b>- R$ {{ number_format($totDevolvido,2,',','.') }}</b></div>@endif
  </div>
  @if($prestacao ?? false)
  <div class="bloco">
    <h2>Prestação de contas</h2>
    <div class="lin"><span>Total vendas de Passagens</span><b>R$ {{ number_format($prestacao->bruto,2,',','.') }}</b></div>
    <div class="lin"><span>Comissão de Agência{{ !is_null($prestacao->pct) ? ' ('.$prestacao->pct.'%)' : '' }}</span><b style="color:#b91c1c">- R$ {{ number_format($prestacao->comissao,2,',','.') }}</b></div>
    @if($prestacao->debProp > 0.009)<div class="lin"><span>Débito Prop.</span><b style="color:#b91c1c">- R$ {{ number_format($prestacao->debProp,2,',','.') }}</b></div>@endif
    @if($prestacao->pixProp > 0.009)<div class="lin"><span>Pix Prop.</span><b style="color:#b91c1c">- R$ {{ number_format($prestacao->pixProp,2,',','.') }}</b></div>@endif
    @if($prestacao->credProp > 0.009)<div class="lin"><span>Crédito Prop.</span><b style="color:#b91c1c">- R$ {{ number_format($prestacao->credProp,2,',','.') }}</b></div>@endif
    @if($prestacao->dinProp > 0.009)<div class="lin"><span>Dinheiro Prop.</span><b style="color:#b91c1c">- R$ {{ number_format($prestacao->dinProp,2,',','.') }}</b></div>@endif
    <div class="lin"><span>Cartão de crédito</span><b style="color:#b91c1c">- R$ {{ number_format($prestacao->cartao,2,',','.') }}</b></div>
    @if(($prestacao->entradas ?? 0) > 0.009)<div class="lin"><span>Entradas avulsas</span><b style="color:#067d3b">+ R$ {{ number_format($prestacao->entradas,2,',','.') }}</b></div>@endif
    <div class="lin" style="border-top:1px solid #999;margin-top:4px;padding-top:4px;color:#b45309"><span>Total Geral de Despesas <span style="font-weight:400;color:#777">(saídas)</span></span><b style="color:#b91c1c">- R$ {{ number_format($prestacao->saidas,2,',','.') }}</b></div>
    <div class="lin g" style="color:#067d3b"><span>Total Geral Líquido</span><span>R$ {{ number_format($prestacao->liquido,2,',','.') }}</span></div>
  </div>
  @endif
</div>

@if(!empty($porCategoria))
<div style="margin-top:14px">
  <h2 style="font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#374151;border-bottom:2px solid #111;padding-bottom:3px;margin:0 0 6px">Vendas por categoria × forma</h2>
  <table style="width:100%;border-collapse:collapse;font-size:11.5px">
    <thead><tr style="background:#f1f5f9">
      <th style="text-align:left;padding:3px 6px">Categoria</th><th style="text-align:right;padding:3px 6px">Dinheiro</th><th style="text-align:right;padding:3px 6px">PIX</th><th style="text-align:right;padding:3px 6px">Débito</th><th style="text-align:right;padding:3px 6px">Créd. à vista</th><th style="text-align:right;padding:3px 6px">Créd. parcelado</th><th style="text-align:right;padding:3px 6px">A Receber</th><th style="text-align:right;padding:3px 6px">Total</th>
    </tr></thead>
    <tbody>
      @foreach(['balcao'=>'Balcão','proprietario'=>'Proprietário','agente'=>'Agente interior'] as $ck=>$cl)
      @php $c=$porCategoria[$ck] ?? []; @endphp
      <tr style="border-bottom:1px solid #e5e7eb">
        <td style="text-align:left;padding:3px 6px;font-weight:600">{{ $cl }} <span style="color:#999;font-weight:400">· {{ $c['qtd'] ?? 0 }}</span></td>
        <td style="text-align:right;padding:3px 6px">{{ number_format($c['dinheiro'] ?? 0,2,',','.') }}</td>
        <td style="text-align:right;padding:3px 6px">{{ number_format($c['pix'] ?? 0,2,',','.') }}</td>
        <td style="text-align:right;padding:3px 6px">{{ number_format($c['debito'] ?? 0,2,',','.') }}</td>
        <td style="text-align:right;padding:3px 6px">{{ number_format($c['credito_avista'] ?? 0,2,',','.') }}</td>
        <td style="text-align:right;padding:3px 6px">{{ number_format($c['credito_parcelado'] ?? 0,2,',','.') }}</td>
        <td style="text-align:right;padding:3px 6px;color:#b45309">{{ number_format($c['areceber'] ?? 0,2,',','.') }}</td>
        <td style="text-align:right;padding:3px 6px;font-weight:700">{{ number_format($c['total'] ?? 0,2,',','.') }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

@if($modo !== 'relatorio')
<div style="margin-top:14px">
  <h2 style="display:flex;justify-content:space-between;align-items:baseline;gap:12px;flex-wrap:wrap">
    <span>Entradas e saídas avulsas</span>
    <span style="font-size:11px;font-weight:700">
      <span style="color:#067d3b">Entradas R$ {{ number_format($avEntradas ?? 0,2,',','.') }}</span> ·
      <span style="color:#b91c1c">Saídas R$ {{ number_format($avSaidas ?? 0,2,',','.') }}</span> ·
      Saldo R$ {{ number_format(($avEntradas ?? 0) - ($avSaidas ?? 0),2,',','.') }}
    </span>
  </h2>
  <table style="width:100%;border-collapse:collapse;font-size:11px">
    <thead><tr style="background:#f1f5f9;text-align:left">
      <th style="padding:4px 6px">Data</th><th style="padding:4px 6px">Tipo</th><th style="padding:4px 6px">Motivo</th>@if($modo === 'prestacao')<th style="padding:4px 6px">Transporte / viagem</th>@endif<th style="padding:4px 6px">Forma</th><th style="padding:4px 6px">Operador</th><th style="padding:4px 6px;text-align:right">Valor</th>
    </tr></thead>
    <tbody>
      @forelse(($avulsos ?? collect()) as $av)
      <tr style="border-bottom:1px solid #e5e7eb">
        <td style="padding:4px 6px;white-space:nowrap">{{ optional($av->created_at)->format('d/m/Y H:i') }}</td>
        <td style="padding:4px 6px;font-weight:700;color:{{ $av->tipo_mov === 'saida' ? '#b91c1c' : '#067d3b' }}">{{ $av->tipo_mov === 'saida' ? 'Saída' : 'Entrada' }}</td>
        <td style="padding:4px 6px">{{ trim(($av->categoria ? $av->categoria.' · ' : '').($av->descricao ?? ''), ' ·') ?: '—' }}</td>
        @if($modo === 'prestacao')<td style="padding:4px 6px;white-space:nowrap">{{ optional($av->embarcacao)->nome ?? '—' }}{{ $av->data_viagem ? ' · '.\Illuminate\Support\Carbon::parse($av->data_viagem)->format('d/m/Y') : '' }}</td>@endif
        <td style="padding:4px 6px">{{ \App\Models\Pagamento::MEIOS[$av->meio] ?? ucfirst((string) $av->meio) }}</td>
        <td style="padding:4px 6px">{{ optional($av->user)->name ?? '—' }}</td>
        <td style="padding:4px 6px;text-align:right;font-weight:700;white-space:nowrap;color:{{ $av->tipo_mov === 'saida' ? '#b91c1c' : '#067d3b' }}">{{ $av->tipo_mov === 'saida' ? '−' : '+' }} R$ {{ number_format($av->valor,2,',','.') }}</td>
      </tr>
      @empty
      <tr><td colspan="{{ $modo === 'prestacao' ? 7 : 6 }}" style="padding:8px;text-align:center;color:#777">Nenhuma entrada ou saída avulsa no filtro.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endif

<div class="leg">
  Legenda:
  <span style="background:#dbeafe">Agente / Município</span>
  <span style="background:#dcfce7">Idoso</span>
  <span style="background:#fef9c3">PCD</span>
  <span style="background:#fee2e2;color:#b91c1c">Cancelado / Devolvido</span>
</div>

@if($auto ?? false)<script>window.addEventListener('load',function(){setTimeout(function(){window.print();},300);});</script>@endif
</body></html>
