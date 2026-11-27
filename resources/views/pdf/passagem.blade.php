{{-- Bilhete em PDF (enviado pelo agente no WhatsApp). Mesmo desenho do bilhete que o operador gera na tela
     (passagens/comprovante), refeito em tabelas porque o gerador de PDF não entende flex/grid. --}}
@php
  $e = $empresa; $ehCam = $p->tipo === 'camarote';
  $cor = $e?->cor_primaria ?: '#0b1a33';
  $dias = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
  $horaRota = null;
  if ($p->rota) { $par = $p->rota->paradas->firstWhere('localidade', $p->origem); $horaRota = ($par && $par->hora) ? \Illuminate\Support\Str::substr($par->hora, 0, 5) : null; }
  $hora = $horaRota ?: ($p->embarcacao?->hora_saida ? \Illuminate\Support\Str::substr($p->embarcacao->hora_saida, 0, 5) : null);
  $formas = $p->pagamentos->map(fn ($pg) => (\App\Models\Pagamento::MEIOS[$pg->meio] ?? $pg->meio).((bool) ($pg->pago_proprietario ?? false) ? ' Prop.' : ''))->filter()->unique()->implode(', ');
  $formaLabel = (\App\Models\Passagem::FORMAS[$p->forma] ?? ucfirst((string) $p->forma)) . (($p->forma === 'areceber' && (int) $p->parcelas > 1) ? ' ('.(int) $p->parcelas.'x)' : '');
  $ehProprietario = $p->pagamentos->contains(fn ($pg) => (bool) ($pg->pago_proprietario ?? false));
  $pagantes = $p->passageiros->filter(fn ($x) => (($x->papel ?? '') !== 'colo'))->values();
  $colos = $p->passageiros->filter(fn ($x) => (($x->papel ?? '') === 'colo'))->values();
  if ($pagantes->isEmpty()) { $pagantes = $p->passageiros->values(); $colos = collect(); }
  $tot = $pagantes->count();
  $titulo = $ehCam ? 'BILHETE · CAMAROTE' : 'BILHETE DE PASSAGEM';
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
  $br = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
  // linha "rótulo ........ valor"
  $row = fn ($k, $v, $cls = '') => '<table class="row '.$cls.'"><tr><td class="k">'.e($k).'</td><td class="vv">'.$v.'</td></tr></table>';
@endphp
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8">
<style>
  @page { margin: 0; }
  * { font-family: "DejaVu Sans", sans-serif; }
  body { margin: 0; padding: 0; font-size: 12px; color: #111; }
  .ticket { padding: 4mm 4mm 5mm; page-break-after: always; }
  .ticket.ult { page-break-after: auto; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 0; vertical-align: middle; }
  .hdr { text-align: center; }
  .hdr img { max-height: 46px; max-width: 150px; }
  .emp { font-size: 15px; font-weight: bold; margin-top: 2px; }
  .cnpj { font-size: 10px; color: #1f2937; }
  .band { margin: 6px -4mm 0; padding: 5px 0; text-align: center; color: #fff; font-weight: bold; font-size: 12px; letter-spacing: 1px; background: {{ $cor }}; }
  .aviso { text-align: center; font-weight: bold; font-size: 10px; line-height: 1.25; text-transform: uppercase; color: {{ $cor }}; border: 1.5px dashed {{ $cor }}; border-radius: 8px; padding: 5px 7px; margin: 6px 2px 2px; }
  .hero { margin: 9px 0 5px; }
  .tk { border: 1.5px solid {{ $cor }}; border-radius: 10px; padding: 5px 9px; }
  .tk .l { font-size: 8.5px; text-transform: uppercase; letter-spacing: .5px; color: {{ $cor }}; font-weight: bold; }
  .tk .v { font-size: 16px; font-weight: bold; line-height: 1.1; }
  .pol { border-radius: 10px; background: {{ $cor }}; color: #fff; text-align: center; padding: 5px 8px; }
  .pol .l { font-size: 8px; letter-spacing: .5px; }
  .pol .v { font-size: 20px; font-weight: bold; line-height: 1; }
  .lancha { text-align: center; font-size: 16px; font-weight: bold; color: {{ $cor }}; text-transform: uppercase; margin: 2px 0 0; }
  .trecho { text-align: center; font-size: 16px; font-weight: bold; margin: 5px 0 1px; }
  .saida { text-align: center; background: {{ $cor }}; color: #fff; border-radius: 11px; padding: 7px 10px; margin: 7px 0 3px; }
  .saida .d { font-size: 12px; font-weight: bold; letter-spacing: .5px; }
  .saida .h { font-size: 30px; font-weight: bold; line-height: 1.05; margin-top: 3px; }
  .sec { border: 1px solid #e5e7eb; border-radius: 10px; padding: 7px 9px; margin-top: 7px; }
  .sec.pay { border-color: {{ $cor }}; }
  .sec-h { font-size: 8.5px; text-transform: uppercase; letter-spacing: .6px; color: {{ $cor }}; font-weight: bold; margin-bottom: 4px; }
  .paxnome { font-size: 14px; font-weight: bold; line-height: 1.15; }
  .chip { background: {{ $cor }}; color: #fff; border-radius: 10px; padding: 2px 9px; font-size: 8.5px; font-weight: bold; text-transform: uppercase; }
  .row td { font-size: 12px; padding: 2px 0; }
  .row .k { color: #374151; font-weight: bold; }
  .row .vv { font-weight: bold; color: #000; text-align: right; white-space: nowrap; }
  .fpg { background: #f1f5f9; border: 2px solid {{ $cor }}; border-radius: 10px; padding: 6px 10px; margin: 6px 0; }
  .fpg-h { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: {{ $cor }}; margin-bottom: 3px; }
  .fpg .row td { font-size: 15px; padding: 3px 0; }
  .total .tl { font-size: 10px; text-transform: uppercase; color: #6b7280; font-weight: bold; }
  .total .tv { font-size: 20px; font-weight: bold; color: {{ $cor }}; text-align: right; }
  .pg { border-radius: 10px; padding: 1px 10px; font-size: 10px; font-weight: bold; }
  .pg.ok { background: #dcfce7; color: #166534; } .pg.no { background: #fee2e2; color: #991b1b; }
  .qr { text-align: center; margin-top: 9px; }
  .qr img { width: 124px; height: 124px; }
  .qrbox { border: 1.5px solid {{ $cor }}; border-radius: 10px; padding: 7px 6px 6px; margin-top: 4px; }
  .qr .t { font-size: 10.5px; font-weight: bold; color: {{ $cor }}; letter-spacing: .5px; }
  .muted { color: #6b7280; font-size: 8.5px; }
  .info { margin-top: 7px; font-size: 9.5px; color: #4b5563; background: #f9fafb; border: 1px solid #eef0f2; border-radius: 8px; padding: 6px 8px; line-height: 1.35; }
  .foot { text-align: center; font-size: 8.5px; color: #9ca3af; margin-top: 8px; border-top: 1px dashed #d1d5db; padding-top: 5px; }
  .canc { text-align: center; font-size: 20px; font-weight: bold; color: #b91c1c; border: 2px solid #b91c1c; border-radius: 8px; padding: 4px; margin-top: 6px; letter-spacing: 2px; }
</style></head><body>
@foreach($pagantes as $i => $pax)
@php
  $ehGrat = (($pax->papel ?? '') !== 'colo') && ((float) ($pax->valor_passagem ?? 0) <= 0.009) && ((float) ($pax->valor_integral ?? 0) > 0.009);
  $tarja = $p->cancelada ? ((($p->cancel_tipo ?? '') === 'cancelamento') ? 'CANCELADO' : 'DEVOLUÇÃO') : (($pax->cancelado ?? false) ? ((($pax->cancelado_tipo ?? '') === 'cancelamento') ? 'CANCELADO' : 'DEVOLUÇÃO') : null);
  $totalPax = (float) ($pax->valor_total ?? ((float) ($pax->valor_passagem ?? 0) + (float) ($pax->valor_taxa ?? 0) + (float) ($pax->valor_seguro ?? 0) + (float) ($pax->valor_alimentacao ?? 0)));
@endphp
<div class="ticket {{ $loop->last ? 'ult' : '' }}">
  <div class="hdr">
    @if($logo)<img src="{{ $logo }}">@endif
    <div class="emp">{{ mb_strtoupper($bNomeFant) }}</div>
    @if($bRazao)<div class="cnpj">{{ mb_strtoupper($bRazao) }}</div>@endif
    @if($bFisc)<div class="cnpj">{{ $bFisc }}</div>@endif
    @if($bFone)<div class="cnpj">{{ $bFone }}</div>@endif
    @if($bEnd)<div class="cnpj">{{ $bEnd }}</div>@endif
    @if($bEmail)<div class="cnpj">{{ mb_strtolower($bEmail) }}</div>@endif
  </div>
  <div class="band">{{ $titulo }}</div>
  @if($tarja)<div class="canc">{{ $tarja }}</div>@endif
  @if($e?->aviso_comprovante)<div class="aviso">⚠ {{ $e->aviso_comprovante }}</div>@endif

  @if($pax->transf_de_lancha ?? null)
  <div style="border:1.5px solid #000;border-radius:4px;padding:3px 5px;margin:4px 0;font-size:10.5px;line-height:1.25">
    <b>⚠ MUDANÇA DE TRANSPORTE</b><br>
    Antes: {{ mb_strtoupper($pax->transf_de_lancha) }}{{ $pax->transf_de_poltrona ? ' · poltrona '.$pax->transf_de_poltrona : '' }}{{ $pax->transf_de_bilhete ? ' · bilhete '.$pax->transf_de_bilhete : '' }}<br>
    <b>Agora: {{ mb_strtoupper($p->embarcacao?->nome ?? '') }}{{ $pax->poltrona ? ' · POLTRONA '.$pax->poltrona : '' }}</b>
  </div>
  @endif

  <table class="hero"><tr>
    <td><div class="tk"><div class="l">Bilhete nº</div><div class="v">{{ $p->numeroFormatado() }}</div></div></td>
    @if($pax->poltrona)
      <td style="width:6px"></td><td style="width:70px"><div class="pol"><div class="l">POLTRONA</div><div class="v">{{ $pax->poltrona }}</div></div></td>
    @elseif($ehCam && $p->camarote)
      <td style="width:6px"></td><td style="width:86px"><div class="pol"><div class="l">CAMAROTE</div><div class="v" style="font-size:12px">{{ $p->camarote->nome }}</div></div></td>
    @endif
  </tr></table>

  @if($p->embarcacao)<div class="lancha">{{ mb_strtoupper($p->embarcacao->nome) }}</div>@endif
  <div class="trecho">{{ $p->origem }} <span style="color:{{ $cor }}">→</span> {{ $p->destino }}</div>

  @if($p->data_viagem || $hora)
  <div class="saida">
    <div class="d">SAÍDA @if($p->data_viagem){{ $p->data_viagem->format('d/m/Y') }} · {{ $dias[(int) $p->data_viagem->dayOfWeek] }}@endif</div>
    @if($hora)<div class="h">{{ $hora }}</div>@endif
  </div>
  @endif

  @if($p->rota || $p->camarote)
  <div class="sec">
    <div class="sec-h">Viagem</div>
    @if($p->rota){!! $row('Linha autorizada', e(mb_strtoupper($p->rota->nome))) !!}@endif
    @if($p->camarote){!! $row('Suíte / Camarote', e($p->camarote->nome)) !!}@endif
  </div>
  @endif

  <div class="sec">
    <div class="sec-h">Passageiro</div>
    <table><tr>
      <td><div class="paxnome">{{ mb_strtoupper($pax->nome ?: 'PASSAGEIRO') }}</div></td>
      <td style="text-align:right;width:84px"><span class="chip">{{ mb_strtoupper($pax->papel ?: $pax->tipo) }}</span></td>
    </tr></table>
    @if($pax->documento){!! $row($pax->doc_tipo ?: 'Documento', e($pax->documento)) !!}@endif
    @if($pax->nascimento){!! $row('Nascimento', e(\Illuminate\Support\Carbon::parse($pax->nascimento)->format('d/m/Y'))) !!}@endif
    @if($pax->nacionalidade){!! $row('Nacionalidade', e($pax->nacionalidade)) !!}@endif
    @if($pax->telefone){!! $row('Telefone', e($pax->telefone)) !!}@endif
    @if($pax->observacao)<div style="margin-top:5px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:5px 8px;font-size:10.5px;line-height:1.3"><b style="color:#92400e">Observação:</b> {{ $pax->observacao }}</div>@endif
    @php $meusColos = $colos->filter(fn ($b) => $b->colo_de_id ? ((int) $b->colo_de_id === (int) $pax->id) : ($i === 0)); @endphp
    @foreach($meusColos as $b)
      {!! $row('Colo', e(mb_strtoupper($b->nome ?: 'BEBÊ')).($b->nascimento ? ' · nasc. '.e(\Illuminate\Support\Carbon::parse($b->nascimento)->format('d/m/Y')) : '')) !!}
    @endforeach
  </div>

  <div class="sec pay">
    <div class="sec-h">Pagamento &amp; discriminação de valores</div>
    {!! $row('Tarifa', $br($pax->valor_passagem ?? 0)) !!}
    {!! $row('Taxas', $br($pax->valor_taxa ?? 0)) !!}
    {!! $row('Seguro', $br($pax->valor_seguro ?? 0)) !!}
    {!! $row('Alimentação e serviço', $br($pax->valor_alimentacao ?? 0)) !!}
    <table class="total"><tr><td class="tl">Valor total</td><td class="tv">{{ $br($totalPax) }}</td></tr></table>
    @php
      $sit = ($p->cancelada || ($pax->cancelado ?? false)) ? '<span class="pg no">CANCELADA</span>'
          : ($ehGrat ? '<span class="pg" style="background:#dbeafe;color:#1e40af">Gratuidade ('.e($pax->tipo ?: 'Idoso').')</span>'
          : ($p->devido > 0.009 ? '<span class="pg no">EM ABERTO</span>' : '<span class="pg ok">PAGO</span>'));
      $pgs = $p->pagamentos->groupBy('meio')->map(fn ($g) => (float) $g->sum('valor'))->filter(fn ($v) => $v > 0.009);
    @endphp
    {!! $row('Situação', $sit) !!}
    <div class="fpg">
      <div class="fpg-h">Pagamento</div>
      {!! $row('Forma de pagamento', e($formaLabel)) !!}
      @if($p->desconto > 0.009){!! $row('Desconto na venda', '- '.$br($p->desconto)) !!}@endif
      @if($pgs->count())
        @foreach($pgs as $meio => $val){!! $row(\App\Models\Pagamento::MEIOS[$meio] ?? ucfirst($meio), $br($val)) !!}@endforeach
      @elseif($formas){!! $row('Forma', e($formas)) !!}@endif
      @if($ehProprietario){!! $row('Pagamento', 'Realizado pelo proprietário') !!}@endif
    </div>
    {!! $row('Vendedor', e($p->operador?->name ?? '—')) !!}
    {!! $row('Compra', e($p->created_at->format('d/m/Y H:i'))) !!}
    @if($e?->cidade){!! $row('Local de emissão', e($e->cidade.($e->uf ? ' - '.$e->uf : ''))) !!}@endif
    @if(($pax->valor_integral ?? 0) > $totalPax + 0.009){!! $row('Preço normal', $br($pax->valor_integral)) !!}@endif
  </div>

  @if($qr)
  <div class="qr">
    @if($qrTrack)
      <div class="qrbox"><img src="{{ $qr }}"><div class="t">CHECK-IN</div><div class="muted">Apresente este QR no embarque</div></div>
      <div class="qrbox" style="border-color:#047857;margin-top:7px"><img src="{{ $qrTrack }}"><div class="t" style="color:#047857">ACOMPANHE A VIAGEM</div><div class="muted">Siga o barco em tempo real</div></div>
    @else
      <img src="{{ $qr }}"><div class="t">CHECK-IN</div><div class="muted">Apresente este QR no embarque</div>
    @endif
  </div>
  @endif

  @if($e?->rodape_comprovante)<div class="info"><b style="font-size:8.5px;text-transform:uppercase;color:#374151">Informações importantes</b><br>{{ $e->rodape_comprovante }}</div>@endif
  @if($e && $e->config('usa_cancel_transporte'))<div class="info">Devolução: antes da saída retém {{ $p->embarcacao?->cancel_antes_pct ?? $e->config('cancel_antes_pct',10) }}%, depois da saída retém {{ $p->embarcacao?->cancel_depois_pct ?? $e->config('cancel_depois_pct',30) }}%.</div>@endif

  <div class="foot">Bilhete {{ $i+1 }}/{{ $tot }} · Emitido {{ now()->format('d/m/Y H:i') }} · {{ $p->operador?->name }}</div>
</div>
@endforeach
</body></html>
