<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<title>Passagem {{ $p->numeroFormatado() }}</title>
<style>
@page{margin:0;size:80mm auto}
*{box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{margin:0;padding:0;width:80mm;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12px;color:#111}
.ticket{page-break-after:always;padding:4mm 4mm 5mm;position:relative;overflow:hidden}
.ticket:last-child{page-break-after:auto}
.resumo-venda{position:absolute;left:-10000px;top:0;width:360px;background:#fff;color:#000;padding:4mm 4.5mm 5mm;font-family:inherit;font-size:14px;line-height:1.32}
.rz-h{text-align:center;border-bottom:2px solid #000;padding-bottom:5px;margin-bottom:6px}
.rz-h .t{font-weight:900;font-size:16px;letter-spacing:.06em}
.rz-h .s{font-size:10px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-top:1px}
.rz-h .n{font-size:11px;color:#1f2937;margin-top:3px;font-weight:700}
.rz-emp{font-weight:900;font-size:17px;text-align:center;letter-spacing:.02em}
.rz-tr{text-align:center;font-weight:800;font-size:13px;margin:1px 0}
.rz-line{border-top:1px dashed #9ca3af;margin:6px 0}
.rz-row{display:flex;justify-content:space-between;gap:8px;font-size:13.5px;padding:2px 0}
.rz-row .k{color:#374151;font-weight:600}
.rz-row .vv{font-weight:800;text-align:right;color:#000}
.rz-px{font-size:13.5px;padding:2.5px 0;display:flex;justify-content:space-between;gap:8px;border-bottom:1px dotted #e5e7eb}
.rz-px .nm{font-weight:700}
.rz-px .pl{font-weight:800;white-space:nowrap}
.rz-sech{font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#374151;margin:4px 0 2px}
.rz-pay{font-size:15.5px;padding:5px 9px;margin:4px 0;background:#f1f5f9;border:1.5px solid #cbd5e1;border-radius:9px}
.rz-payrow{display:flex;justify-content:space-between;gap:8px;font-weight:900;padding:2px 0}
.rz-payrow .k{font-weight:700;color:#1f2937}
.rz-tot{display:flex;justify-content:space-between;font-weight:900;font-size:18px;border-top:2px solid #000;margin-top:4px;padding-top:4px}
.rz-pg{display:inline-block;font-weight:900;font-size:11px;padding:1px 8px;border-radius:6px}
.rz-ass{margin-top:10px;font-size:11px}
.rz-ln{border-bottom:1px solid #000;height:14px;margin-top:2px}
.rz-foot{text-align:center;font-size:9px;color:#374151;margin-top:8px;border-top:1px dashed #9ca3af;padding-top:4px}
.cwm{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:60;overflow:hidden}
.cwm b{writing-mode:vertical-rl;text-orientation:mixed;font-size:82px;font-weight:900;letter-spacing:-12px;line-height:.9;color:rgba(185,28,28,.38);white-space:nowrap;text-transform:uppercase}
.c{text-align:center}.muted{color:#6b7280}.b{font-weight:700}
.hdr{text-align:center;margin-bottom:4px}
.hdr img{height:80px;max-width:72mm;object-fit:contain;margin-bottom:4px}
.emp{font-weight:800;font-size:16px;letter-spacing:.02em;line-height:1.12}
.cnpj{font-size:10.5px;color:#1f2937}
.band{margin:6px -4mm 0;padding:5px 0;text-align:center;color:#fff;font-weight:800;font-size:12px;letter-spacing:.09em;background:var(--cor)}
.hero{display:flex;align-items:stretch;gap:6px;margin:9px 0 5px}
.tk{flex:1;border:1.5px solid var(--cor);border-radius:10px;padding:5px 9px}
.tk .l{font-size:9px;text-transform:uppercase;letter-spacing:.06em;color:var(--cor);font-weight:700}
.tk .v{font-size:16px;font-weight:800;line-height:1.1}
.pol{min-width:60px;border-radius:10px;background:var(--cor);color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:4px 8px}
.pol .l{font-size:8px;letter-spacing:.06em;opacity:.92}
.pol .v{font-size:20px;font-weight:800;line-height:1}
.trecho{text-align:center;font-size:16px;font-weight:800;margin:5px 0 1px}
.trecho .ar{color:var(--cor);margin:0 3px}
.sec{border:1px solid #e5e7eb;border-radius:10px;padding:7px 9px;margin-top:7px}
.sec.pay{border-color:var(--cor)}
.sec-h{font-size:9px;text-transform:uppercase;letter-spacing:.07em;color:var(--cor);font-weight:800;margin-bottom:4px}
.paxnome{font-size:15px;font-weight:800;line-height:1.15}
.chip{display:inline-block;background:var(--cor);color:#fff;border-radius:20px;padding:2px 9px;font-size:9px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap}
.row{display:flex;justify-content:space-between;gap:8px;font-size:12px;padding:2px 0}
.row .k{color:#374151;font-weight:600}.row .vv{font-weight:700;color:#000;text-align:right}
.fpg{background:#f1f5f9;border:2px solid var(--cor);border-radius:10px;padding:6px 10px;margin:6px 0}
.fpg .fpg-h{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.09em;color:var(--cor);margin-bottom:3px}
.fpg .row{font-size:17px;padding:3px 0}
.fpg .row .vv{font-weight:900;color:#000}
.fpg .row .k{font-weight:700;color:#111}
.total{display:flex;justify-content:space-between;align-items:center;margin-bottom:3px}
.total .tl{font-size:10px;text-transform:uppercase;color:#6b7280;font-weight:700}
.total .tv{font-size:20px;font-weight:800;color:var(--cor)}
.pg{display:inline-block;border-radius:20px;padding:1px 10px;font-size:10px;font-weight:800}
.pg.ok{background:#dcfce7;color:#166534}.pg.no{background:#fee2e2;color:#991b1b}
.qr{text-align:center;margin-top:9px}
.qrp{display:inline-block}.qrp img,.qrp canvas{margin:0 auto}
.info{margin-top:7px;font-size:10px;color:#4b5563;background:#f9fafb;border:1px solid #eef0f2;border-radius:8px;padding:6px 8px;line-height:1.35}
.foot{text-align:center;font-size:9px;color:#9ca3af;margin-top:8px;border-top:1px dashed #d1d5db;padding-top:5px}
@media print{#pdfbar,#topbar{display:none!important}}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script></head>
<body onload="qrImprimir()">
<div id="topbar" style="padding:8px 10px;background:#0b1a33;position:sticky;top:0;z-index:100">
  <button type="button" onclick="voltarComprovante()" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;font-size:14px;font-weight:700;border:0;border-radius:10px;background:#fff;color:#0b1a33;cursor:pointer">&larr; Voltar</button>
</div>
<script>function voltarComprovante(){ location.href='/passagens'; }</script>
@php
  $e=$empresa; $ehCam = $p->tipo==='camarote';
  $cor = $e?->cor_primaria ?: '#0b1a33';
  $dias = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
  $horaRota = null;
  if ($p->rota) { $par = $p->rota->paradas->firstWhere('localidade', $p->origem); $horaRota = ($par && $par->hora) ? \Illuminate\Support\Str::substr($par->hora, 0, 5) : null; }
  $hora = $horaRota ?: ($p->embarcacao?->hora_saida ? \Illuminate\Support\Str::substr($p->embarcacao->hora_saida, 0, 5) : null);
  $formas = $p->pagamentos->map(fn ($pg) => \App\Models\Pagamento::MEIOS[$pg->meio] ?? $pg->meio)->filter()->unique()->implode(', ');
  $formaLabel = (\App\Models\Passagem::FORMAS[$p->forma] ?? ucfirst((string) $p->forma)) . (($p->forma === 'areceber' && (int) $p->parcelas > 1) ? ' ('.(int) $p->parcelas.'x)' : '');
  $ehProprietario = $p->pagamentos->contains(fn ($pg) => (bool) ($pg->pago_proprietario ?? false));
  $nrViagem = $p->viagem?->id;
  $pagantes = $p->passageiros->filter(fn ($x) => (($x->papel ?? '') !== 'colo'))->values();
  $colos = $p->passageiros->filter(fn ($x) => (($x->papel ?? '') === 'colo'))->values();
  if ($pagantes->isEmpty()) { $pagantes = $p->passageiros->values(); $colos = collect(); }
  $tot = $pagantes->count();
  $vias = max(1, min(9, (int) request('vias', 1)));
  $so = in_array(request('so'), ['bilhete','resumo'], true) ? request('so') : 'tudo';
  $soCancelados = request()->boolean('cancelados');
  $biList = $soCancelados ? $pagantes->filter(fn ($x) => $p->cancelada || ($x->cancelado ?? false))->values() : $pagantes;
  $titulo = $ehCam ? 'BILHETE · CAMAROTE' : 'BILHETE DE PASSAGEM';
  // Dados de empresa NO BILHETE: se a embarcacao tem os proprios, usa; senao os da empresa.
  $emb = $p->embarcacao;
  $embEmp = $emb && (trim((string)($emb->cnpj ?? '')) !== '' || trim((string)($emb->razao_social ?? '')) !== '' || trim((string)($emb->nome_fantasia ?? '')) !== '');
  $bNomeFant = $embEmp ? (trim((string)$emb->nome_fantasia) ?: $emb->nome) : ($e?->nomeExibicao() ?? 'FBRE');
  $bRazao = $embEmp ? trim((string)$emb->razao_social) : (($e?->config('comprovante_dados_completos') && $e && $e->nome && mb_strtoupper(trim($e->nome)) !== mb_strtoupper(trim($e->nomeExibicao()))) ? $e->nome : '');
  $bCnpj = $embEmp ? trim((string)$emb->cnpj) : trim((string)($e?->cnpj ?? ''));
  $bIe   = $embEmp ? trim((string)$emb->ie) : trim((string)($e?->config('ie') ?? ''));
  $bFisc = trim(($bCnpj ? 'CNPJ '.$bCnpj : '').($bIe ? ($bCnpj ? ' · ' : '').'IE '.$bIe : ''));
  $bFone = $embEmp ? trim((string)$emb->contato) : trim(((string)($e?->telefone ?? '')).((($e?->telefone) && ($e?->whatsapp)) ? ' · ' : '').((string)($e?->whatsapp ?? '')));
  $bEnd  = $embEmp ? trim((string)$emb->endereco.(trim((string)($emb->cep ?? '')) !== '' ? ' · CEP '.$emb->cep : '')) : (($e?->config('comprovante_dados_completos') && ($e?->endereco || $e?->cidade)) ? trim(((string)($e->endereco ?? '')).((($e->endereco) && ($e->cidade)) ? ' · ' : '').((string)($e->cidade ?? '')).($e->uf ? '/'.$e->uf : '')) : '');
  $bEmail = $embEmp ? trim((string)$emb->email_bilhete) : '';
@endphp

@if($so !== 'resumo')
@for($__via = 0; $__via < $vias; $__via++)
@foreach($biList as $i => $pax)
<div class="ticket" style="--cor:{{ $cor }}">
@php $ehGrat = (($pax->papel ?? '') !== 'colo') && ((float) ($pax->valor_passagem ?? 0) <= 0.009) && ((float) ($pax->valor_integral ?? 0) > 0.009); @endphp
@php $__tarja = $p->cancelada ? ((($p->cancel_tipo ?? '')==='cancelamento')?'CANCELAMENTO':'DEVOLUÇÃO') : (($pax->cancelado ?? false) ? ((($pax->cancelado_tipo ?? '')==='cancelamento')?'CANCELAMENTO':'DEVOLUÇÃO') : null); @endphp
@if($__tarja)<div class="cwm"><b>{{ $__tarja }}</b></div>@endif
@if($ehGrat && !$p->cancelada)<div style="position:absolute;top:0;bottom:0;right:0.5mm;display:flex;align-items:center;pointer-events:none;z-index:1"><span style="writing-mode:vertical-rl;font-size:15px;font-weight:900;letter-spacing:3px;color:rgba(0,0,0,.22)">GRATUIDADE</span></div>@endif
  <div class="hdr">
    @if($e?->logo)<img src="/{{ $e->logo }}" alt="logo">@endif
    <div class="emp">{{ strtoupper($bNomeFant) }}</div>
    @if($bRazao)<div class="cnpj">{{ strtoupper($bRazao) }}</div>@endif
    @if($bFisc)<div class="cnpj">{{ $bFisc }}</div>@endif
    @if($bFone)<div class="cnpj">{{ $bFone }}</div>@endif
    @if($bEnd)<div class="cnpj">{{ $bEnd }}</div>@endif
    @if($bEmail)<div class="cnpj">{{ strtolower($bEmail) }}</div>@endif
  </div>
  <div class="band">{{ $titulo }}</div>
  @if($e?->aviso_comprovante)<div style="text-align:center;font-weight:800;font-size:10.5px;line-height:1.25;text-transform:uppercase;letter-spacing:.02em;color:var(--cor);border:1.5px dashed var(--cor);border-radius:8px;padding:5px 7px;margin:5px 2px 2px">⚠️ {{ $e->aviso_comprovante }}</div>@endif

  <div class="hero">
    <div class="tk"><div class="l">Bilhete nº</div><div class="v">{{ $p->numeroFormatado() }}</div></div>
    @if($pax->poltrona)
      <div class="pol"><div class="l">POLTRONA</div><div class="v">{{ $pax->poltrona }}</div></div>
    @elseif($ehCam && $p->camarote)
      <div class="pol"><div class="l">CAMAROTE</div><div class="v" style="font-size:13px">{{ $p->camarote->nome }}</div></div>
    @endif
  </div>

  @if($p->embarcacao)<div style="text-align:center;font-size:17px;font-weight:800;color:var(--cor);text-transform:uppercase;margin:2px 0 0">🚤 {{ strtoupper($p->embarcacao->nome) }}</div>@endif
  <div class="trecho">{{ $p->origem }} <span class="ar">→</span> {{ $p->destino }}</div>

  @if($p->data_viagem || $hora)
  <div style="text-align:center;background:var(--cor);color:#fff;border-radius:11px;padding:7px 10px;margin:7px 0 3px">
    <div style="font-size:12px;font-weight:800;letter-spacing:.05em">🕒 SAÍDA @if($p->data_viagem){{ $p->data_viagem->format('d/m/Y') }} · {{ $dias[(int) $p->data_viagem->dayOfWeek] }}@endif</div>
    @if($hora)<div style="font-size:32px;font-weight:900;line-height:1.05;margin-top:3px;letter-spacing:.03em">⏰ {{ $hora }}</div>@endif
  </div>@endif

  @if($p->rota || $p->embarcacao?->logo || $p->camarote)
  <div class="sec">
    <div class="sec-h">Viagem</div>
    @if($p->embarcacao?->logo)<div style="text-align:center;margin:2px 0 4px"><img src="/{{ $p->embarcacao->logo }}" alt="barco" style="height:30px;max-width:46mm;object-fit:contain"></div>@endif
    @if($p->rota)<div class="row"><span class="k">Linha autorizada</span><span class="vv">{{ strtoupper($p->rota->nome) }}</span></div>@endif
    @if($p->camarote)<div class="row"><span class="k">Suíte / Camarote</span><span class="vv">{{ $p->camarote->nome }}</span></div>@endif
  </div>
  @endif

  <div class="sec">
    <div class="sec-h">Passageiro</div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:6px">
      <div class="paxnome">{{ strtoupper($pax->nome ?: 'PASSAGEIRO') }}</div>
      <span class="chip">{{ strtoupper($pax->papel ?: $pax->tipo) }}</span>
    </div>
    @if($pax->documento)<div class="row"><span class="k">{{ $pax->doc_tipo ?: 'Documento' }}</span><span class="vv">{{ $pax->documento }}</span></div>@endif
    @if($pax->nascimento)<div class="row"><span class="k">Nascimento</span><span class="vv">{{ $pax->nascimento->format('d/m/Y') }}</span></div>@endif
    @if($pax->nacionalidade)<div class="row"><span class="k">Nacionalidade</span><span class="vv">{{ $pax->nacionalidade }}</span></div>@endif
    @if($pax->telefone)<div class="row"><span class="k">Telefone</span><span class="vv">{{ $pax->telefone }}</span></div>@endif
    @if($pax->observacao)<div style="margin-top:5px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:5px 8px;font-size:11px;line-height:1.3"><span class="b" style="color:#92400e">📌 Observação:</span> {{ $pax->observacao }}</div>@endif
    @php $meusColos = $colos->filter(fn ($b) => $b->colo_de_id ? ((int) $b->colo_de_id === (int) $pax->id) : ($i === 0)); @endphp
    @foreach($meusColos as $b)
      <div class="row"><span class="k">👶 Colo</span><span class="vv b">{{ strtoupper($b->nome ?: 'BEBÊ') }}@if($b->nascimento)<span style="font-weight:400;color:#555"> · nasc. {{ \Illuminate\Support\Carbon::parse($b->nascimento)->format('d/m/Y') }}</span>@endif</span></div>
    @endforeach
  </div>

  <div class="sec pay">
    <div class="sec-h">Pagamento &amp; discriminação de valores</div>
    <div class="row"><span class="k">Tarifa</span><span class="vv">R$ {{ number_format((float) ($pax->valor_passagem ?? 0),2,',','.') }}</span></div>
    <div class="row"><span class="k">Taxas</span><span class="vv">R$ {{ number_format((float) ($pax->valor_taxa ?? 0),2,',','.') }}</span></div>
    <div class="row"><span class="k">Seguro</span><span class="vv">R$ {{ number_format((float) ($pax->valor_seguro ?? 0),2,',','.') }}</span></div>
    <div class="row"><span class="k">Alimentação e serviço</span><span class="vv">R$ {{ number_format((float) ($pax->valor_alimentacao ?? 0),2,',','.') }}</span></div>
    <div class="total"><span class="tl">Valor total</span><span class="tv">R$ {{ number_format((float) $pax->valor_total,2,',','.') }}</span></div>
    <div class="row"><span class="k">Situação</span><span class="vv">@if($p->cancelada || ($pax->cancelado ?? false))<span class="pg no">CANCELADA</span>@elseif($ehGrat)<span class="pg" style="background:#dbeafe;color:#1e40af">Gratuidade ({{ $pax->tipo ?: 'Idoso' }})</span>@elseif($p->devido > 0.009)<span class="pg no">EM ABERTO</span>@else<span class="pg ok">PAGO</span>@endif</span></div>
    <div class="fpg">
    <div class="fpg-h">💳 Pagamento</div>
    <div class="row"><span class="k">Forma de pagamento</span><span class="vv b">{{ $formaLabel }}</span></div>
    @if($p->desconto > 0.009)<div class="row"><span class="k">Desconto na venda</span><span class="vv">- R$ {{ number_format((float) $p->desconto,2,',','.') }}</span></div>@endif
    @php $pgs = $p->pagamentos->groupBy('meio')->map(fn ($g) => (float) $g->sum('valor'))->filter(fn ($v) => $v > 0.009); @endphp
    @if($pgs->count())
      @foreach($pgs as $meio => $val)<div class="row"><span class="k">{{ \App\Models\Pagamento::MEIOS[$meio] ?? ucfirst($meio) }}</span><span class="vv">R$ {{ number_format($val,2,',','.') }}</span></div>@endforeach
    @elseif($formas)<div class="row"><span class="k">Forma</span><span class="vv">{{ $formas }}</span></div>@endif
    @if($ehProprietario)<div class="row"><span class="k">🚤 Pagamento</span><span class="vv b">Realizado pelo proprietário</span></div>@endif
    </div>
    <div class="row"><span class="k">Vendedor</span><span class="vv">{{ $p->operador?->name ?? '—' }}</span></div>
    <div class="row"><span class="k">Compra</span><span class="vv">{{ $p->created_at->format('d/m/Y H:i') }}</span></div>
    @if($e?->cidade)<div class="row"><span class="k">Local de emissão</span><span class="vv">{{ $e->cidade }}{{ $e->uf ? ' - '.$e->uf : '' }}</span></div>@endif
    <div class="row"><span class="k">Impressão</span><span class="vv">{{ now()->format('d/m/Y H:i:s') }}</span></div>
    @if($pax->valor_integral > $pax->valor_total + 0.009)<div class="row"><span class="k">Preço normal</span><span class="vv">R$ {{ number_format((float) $pax->valor_integral,2,',','.') }}</span></div>@endif
  </div>

  @php $trackUrl = trim((string) ($p->embarcacao?->gps_url ?? '')) ?: null; @endphp
  <div class="qr">
    @if($trackUrl)
    <div style="margin-top:3px">
      <div style="border:1.5px solid var(--cor);border-radius:10px;padding:7px 6px 6px">
        <div class="qrp" data-url="{{ route('checkin',$p) }}" data-size="124"></div>
        <div class="b" style="font-size:11px;color:var(--cor);letter-spacing:.05em">CHECK-IN</div>
        <div class="muted" style="font-size:8.5px">Apresente este QR no embarque</div>
      </div>
      <div style="border:1.5px solid #047857;border-radius:10px;padding:7px 6px 6px;margin-top:7px">
        <div class="qrp" data-url="{{ $trackUrl }}" data-size="124"></div>
        <div class="b" style="font-size:11px;color:#047857;letter-spacing:.05em">ACOMPANHE A VIAGEM</div>
        <div class="muted" style="font-size:8.5px">Siga o barco em tempo real</div>
      </div>
    </div>
    @else
    <div class="qrp" data-url="{{ route('checkin',$p) }}"></div>
    <div class="b" style="font-size:10px;color:var(--cor);letter-spacing:.05em">CHECK-IN</div>
    <div class="muted" style="font-size:9px">Apresente este QR no embarque</div>
    @endif
  </div>

  @if($e?->rodape_comprovante)<div class="info"><div class="b" style="font-size:9px;text-transform:uppercase;letter-spacing:.06em;color:#374151;margin-bottom:2px">ℹ️ Informações importantes</div>{{ $e->rodape_comprovante }}</div>@endif
  @if($e && $e->config('usa_cancel_transporte'))<div class="info">Devolução: antes da saída retém {{ $p->embarcacao?->cancel_antes_pct ?? $e->config('cancel_antes_pct',10) }}%, depois da saída retém {{ $p->embarcacao?->cancel_depois_pct ?? $e->config('cancel_depois_pct',30) }}%.</div>@endif

  <div class="foot">Bilhete {{ $i+1 }}/{{ $tot }} · Emitido {{ now()->format('d/m/Y H:i') }} · {{ $p->operador?->name }}</div>
</div>
@endforeach
@endfor
@endif

@php
  $rzPag = (float) $p->pago; $rzDev = (float) $p->devido;
  $rzSit = $p->cancelada ? ['CANCELADA','#fee2e2','#b91c1c'] : ($rzDev > 0.009 ? ['EM ABERTO','#fef3c7','#92400e'] : ['PAGO','#dcfce7','#166534']);
@endphp
@if($so !== 'bilhete')
<div class="resumo-venda">
  <div class="rz-h">
    <div class="t">RESUMO DA VENDA</div>
    <div class="s">Via interna &middot; arquivo</div>
    <div class="n">Venda #{{ $p->numeroFormatado() }} &middot; {{ $p->created_at->format('d/m/Y H:i') }}</div>
  </div>
  <div class="rz-emp">{{ strtoupper($e?->nomeExibicao() ?? 'FBRE') }}</div>
  <div class="rz-row"><span class="k">Vendedor</span><span class="vv">{{ $p->operador?->name ?? '—' }}</span></div>
  <div class="rz-line"></div>
  @if($p->embarcacao)<div class="rz-tr">🚤 {{ strtoupper($p->embarcacao->nome) }}</div>@endif
  <div class="rz-row"><span class="k">Viagem</span><span class="vv">@if($p->data_viagem){{ $p->data_viagem->format('d/m/Y') }}@else—@endif{{ $hora ? ' · '.$hora : '' }}</span></div>
  <div class="rz-row"><span class="k">Trecho</span><span class="vv">{{ $p->origem }} → {{ $p->destino }}</span></div>
  <div class="rz-line"></div>
  <div class="rz-sech">Passageiros ({{ $pagantes->count() }})</div>
  @foreach($pagantes as $i => $pax)
    <div class="rz-px"><span class="nm">{{ $i+1 }}. {{ strtoupper($pax->nome ?: 'PASSAGEIRO') }}</span>@if($pax->poltrona)<span class="pl">Polt. {{ $pax->poltrona }}</span>@endif</div>
  @endforeach
  @if($colos->count())<div class="rz-row" style="margin-top:3px"><span class="k">+ bebês de colo</span><span class="vv">{{ $colos->count() }}</span></div>@endif
  <div class="rz-line"></div>
  <div class="rz-pay">
    <div class="rz-payrow"><span class="k">💳 Forma</span><span>{{ $formaLabel }}</span></div>
    @if($formas)<div class="rz-payrow"><span class="k">Pago em</span><span>{{ $formas }}</span></div>@endif
    @if($ehProprietario)<div class="rz-payrow"><span class="k">🚤 Proprietário</span><span>Pagamento realizado pelo proprietário</span></div>@endif
  </div>
  @if($p->desconto > 0.009)<div class="rz-row"><span class="k">Desconto</span><span class="vv">- R$ {{ number_format((float) $p->desconto,2,',','.') }}</span></div>@endif
  <div class="rz-row"><span class="k">Recebido</span><span class="vv">R$ {{ number_format($rzPag,2,',','.') }}</span></div>
  <div class="rz-row"><span class="k">Em aberto</span><span class="vv">R$ {{ number_format($rzDev,2,',','.') }}</span></div>
  <div class="rz-tot"><span>TOTAL</span><span>R$ {{ number_format((float) $p->total,2,',','.') }}</span></div>
  <div class="rz-row" style="margin-top:4px"><span class="k">Situação</span><span class="vv"><span class="rz-pg" style="background:{{ $rzSit[1] }};color:{{ $rzSit[2] }}">{{ $rzSit[0] }}</span></span></div>
  <div class="rz-line"></div>
  @if($p->comprador_nome)<div class="rz-row"><span class="k">Comprador</span><span class="vv">{{ $p->comprador_nome }}</span></div>@endif
  <div class="rz-ass"><span style="font-weight:700">Conferido por:</span><div class="rz-ln"></div></div>
  <div class="rz-foot">RESUMO INTERNO · não é bilhete de embarque · impresso {{ now()->format('d/m/Y H:i') }}</div>
</div>


@endif
<div id="pdfbar" style="display:none;padding:10px 6px 22px">
  <button id="btEnviar" onclick="enviarPdf()" style="width:100%;max-width:300px;margin:0 auto;display:block;padding:13px 16px;font-size:15px;font-weight:bold;border:0;border-radius:12px;background:#22c55e;color:#fff">📲 Enviar / Compartilhar PDF</button>
  <div id="pdfStatus" style="text-align:center;font-size:11px;color:#666;margin-top:7px">preparando o PDF…</div>
</div>
<script>
var MODO=@json($modo ?? null);
var _pdfBlob=null,_pdfFile=null,_pdfName='passagem-{{ $p->numeroFormatado() }}.pdf';
var RASTER = new URLSearchParams(location.search).has('raster');
function rasterTickets(){
  var h2c = window.html2canvas;
  if(!h2c){ try{ parent.postMessage({fbreRaster:true, imagens:[], erro:'sem html2canvas'}, '*'); }catch(e){} return; }
  var els = Array.prototype.slice.call(document.querySelectorAll('.ticket'));
  var out = [];
  (function next(i){
    if(i>=els.length){
      var rz=document.querySelector('.resumo-venda');
      if(!rz){ try{ parent.postMessage({fbreRaster:true, imagens:out, resumo:null}, '*'); }catch(e){} return; }
      h2c(rz, {scale:2, backgroundColor:'#ffffff', useCORS:true, logging:false}).then(function(cv){
        var ri=null; try{ ri=cv.toDataURL('image/png'); }catch(e){}
        try{ parent.postMessage({fbreRaster:true, imagens:out, resumo:ri}, '*'); }catch(e){}
      }).catch(function(){ try{ parent.postMessage({fbreRaster:true, imagens:out, resumo:null}, '*'); }catch(e){} });
      return;
    }
    h2c(els[i], {scale:2, backgroundColor:'#ffffff', useCORS:true, logging:false}).then(function(cv){
      try{ out.push(cv.toDataURL('image/png')); }catch(e){}
      next(i+1);
    }).catch(function(){ next(i+1); });
  })(0);
}
function qrImprimir(){
  try{ document.querySelectorAll('.qrp').forEach(function(el){ var sz=parseInt(el.dataset.size)||130; new QRCode(el,{text:el.dataset.url,width:sz,height:sz,correctLevel:QRCode.CorrectLevel.M}); }); }catch(e){}
  if(RASTER){ var _imgs=Array.prototype.slice.call(document.images); var _wi=Promise.all(_imgs.filter(function(im){return !im.complete;}).map(function(im){return new Promise(function(r){im.onload=im.onerror=r;});})); var _wf=(document.fonts&&document.fonts.ready)?document.fonts.ready:Promise.resolve(); Promise.race([Promise.all([_wi,_wf]), new Promise(function(r){setTimeout(r,500);})]).then(function(){ setTimeout(rasterTickets,60); }); return; }
  var isMobile = /Android|iPhone|iPad|iPod|Mobile|Opera Mini/i.test(navigator.userAgent||'') || (window.matchMedia && window.matchMedia('(max-width:820px)').matches) || ('ontouchstart' in window && Math.min(screen.width,screen.height)<820);
  if(MODO==='pdf' || isMobile){
    document.getElementById('pdfbar').style.display='block';
    setTimeout(function(){ prepararPdf(function(){ var st=document.getElementById('pdfStatus'); if(st) st.textContent='Pronto! Toque em Enviar pra mandar no WhatsApp.'; }); }, 400);
  } else {
    setTimeout(function(){ window.print(); }, 350);
  }
}
function prepararPdf(cb){
  var _h=0; document.querySelectorAll('.ticket').forEach(function(el){ if(el.offsetHeight>_h) _h=el.offsetHeight; });
  var _mm=Math.ceil(_h/96*25.4)+8; if(_mm<70) _mm=70;
  var opt={ margin:0, filename:_pdfName, image:{type:'jpeg',quality:0.95}, html2canvas:{scale:2,useCORS:true}, jsPDF:{unit:'mm',format:[80,_mm],orientation:'portrait'}, pagebreak:{mode:['css']} };
  html2pdf().set(opt).from(document.body).toPdf().output('blob').then(function(blob){
    _pdfBlob=blob;
    try{ _pdfFile=new File([blob],_pdfName,{type:'application/pdf'}); }catch(e){ _pdfFile=null; }
    if(cb) cb();
  }).catch(function(e){ var st=document.getElementById('pdfStatus'); if(st) st.textContent='Erro ao gerar PDF: '+e.message; });
}
function enviarPdf(){
  // precisa rodar DENTRO do toque do usuario pra o compartilhamento (WhatsApp) funcionar
  try{
    if(_pdfFile && navigator.canShare && navigator.canShare({files:[_pdfFile]})){
      navigator.share({files:[_pdfFile], title:'Passagem {{ $p->numeroFormatado() }}', text:'Sua passagem 🎫'}).catch(function(){});
      return;
    }
  }catch(e){}
  if(_pdfBlob){
    var url=URL.createObjectURL(_pdfBlob); var a=document.createElement('a'); a.href=url; a.download=_pdfName; document.body.appendChild(a); a.click(); a.remove(); setTimeout(function(){URL.revokeObjectURL(url);},1500);
    return;
  }
  var st=document.getElementById('pdfStatus'); if(st) st.textContent='Ainda gerando… toque de novo em 1 segundo.';
  prepararPdf(function(){ var s2=document.getElementById('pdfStatus'); if(s2) s2.textContent='Pronto! Toque em Enviar.'; });
}
</script>
</body></html>
