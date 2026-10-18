<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Protocolo de entrega</title>
<style>
 @page{size:A4;margin:12mm} body{font-family:Arial,sans-serif;font-size:11px;color:#000;margin:0}
 h1{font-size:16px;margin:0} .cab{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #000;padding-bottom:6px;margin-bottom:8px}
 table{width:100%;border-collapse:collapse} th,td{border:1px solid #000;padding:4px 5px;text-align:left;vertical-align:top} th{background:#eee;font-size:10px}
 .ass{width:110px} .doc{width:80px} .data{width:60px} .sm{font-size:9px;color:#444}
</style></head><body onload="window.print()">
<div class="cab">
  <div><h1>{{ $empresa->nomeExibicao() }}</h1><div class="sm">{{ $empresa->cnpj }} · {{ $empresa->telefone }}</div></div>
  <div style="text-align:right"><b>PROTOCOLO DE ENTREGA</b><div class="sm">Emitido em {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()->name }}</div>
  <div class="sm">@if($f['inicio'] ?? null)Período {{ \Carbon\Carbon::parse($f['inicio'])->format('d/m/Y') }} {{ $f['hora_inicio'] ?? '00:00' }} a {{ \Carbon\Carbon::parse($f['fim'] ?? now())->format('d/m/Y') }} {{ $f['hora_fim'] ?? '23:59' }} ·@endif {{ $encomendas->count() }} encomendas</div></div>
</div>
<table>
<thead><tr><th>Nº</th><th>Destino</th><th>Destinatário</th><th>Remetente</th><th>Vol.</th><th>Devido</th><th class="doc">DOC</th><th class="data">DATA</th><th class="ass">ASSINATURA</th></tr></thead>
<tbody>
@foreach($encomendas as $e)
<tr><td><b>{{ $e->numeroFormatado() }}</b></td><td>{{ $e->destino }}</td><td>{{ $e->destinatario_nome }}<div class="sm">{{ $e->destinatario_telefone }}</div></td><td>{{ $e->remetente_nome }}</td><td style="text-align:center">{{ $e->volumes }}</td><td style="text-align:right">{{ $e->devido>0 ? number_format($e->devido,2,',','.') : '—' }}</td><td></td><td></td><td></td></tr>
@endforeach
</tbody></table>
<p class="sm">Declaro ter recebido a(s) encomenda(s) acima em perfeitas condições.</p>
</body></html>
