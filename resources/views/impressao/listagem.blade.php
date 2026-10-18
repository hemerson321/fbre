<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Listagem de encomendas</title>
<style>
 @page{size:A4 landscape;margin:10mm} body{font-family:Arial,sans-serif;font-size:10px;color:#000;margin:0}
 h1{font-size:15px;margin:0} .cab{display:flex;justify-content:space-between;border-bottom:2px solid #000;padding-bottom:5px;margin-bottom:6px}
 table{width:100%;border-collapse:collapse} th,td{border:1px solid #000;padding:3px 4px;text-align:left} th{background:#eee;font-size:9px} .r{text-align:right} .sm{font-size:9px;color:#444}
 tfoot td{font-weight:bold;background:#f5f5f5}
</style></head><body onload="window.print()">
<div class="cab">
  <div><h1>{{ $empresa->nomeExibicao() }}</h1><div class="sm">Listagem de encomendas · {{ now()->format('d/m/Y H:i') }} · {{ auth()->user()->name }}</div></div>
  <div class="sm" style="text-align:right">@if($f['inicio'] ?? null)Período {{ \Carbon\Carbon::parse($f['inicio'])->format('d/m/Y') }} {{ $f['hora_inicio'] ?? '00:00' }} a {{ \Carbon\Carbon::parse($f['fim'] ?? now())->format('d/m/Y') }} {{ $f['hora_fim'] ?? '23:59' }}<br>@endif{{ $encomendas->count() }} encomendas · {{ $encomendas->sum('volumes') }} volumes</div>
</div>
<table>
<thead><tr><th>Nº</th><th>Data</th><th>Origem</th><th>Destino</th><th>Transporte</th><th>Remetente</th><th>Destinatário</th><th>Vol.</th><th class="r">Total</th><th class="r">Pago</th><th class="r">Devido</th><th>Status</th></tr></thead>
<tbody>
@foreach($encomendas as $e)
<tr><td><b>{{ $e->numeroFormatado() }}</b></td><td>{{ $e->created_at->format('d/m H:i') }}</td><td>{{ $e->origem }}</td><td>{{ $e->destino }}</td><td>{{ $e->embarcacao?->nome }}</td><td>{{ $e->remetente_nome }}</td><td>{{ $e->destinatario_nome }}</td><td style="text-align:center">{{ $e->volumes }}</td><td class="r">{{ number_format($e->total,2,',','.') }}</td><td class="r">{{ number_format($e->pago,2,',','.') }}</td><td class="r">{{ number_format($e->devido,2,',','.') }}</td><td>{{ $e->statusTexto() }}</td></tr>
@endforeach
</tbody>
<tfoot><tr><td colspan="7">TOTAIS</td><td style="text-align:center">{{ $encomendas->sum('volumes') }}</td><td class="r">{{ number_format($encomendas->sum('total'),2,',','.') }}</td><td class="r">{{ number_format($encomendas->sum('pago'),2,',','.') }}</td><td class="r">{{ number_format($encomendas->sum('devido'),2,',','.') }}</td><td></td></tr></tfoot>
</table>
</body></html>
