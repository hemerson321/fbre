f='resources/views/passagens/comprovante.blade.php'
s=open(f,encoding='utf-8').read()
orig=s

# A) CSS do resumo (depois do .ticket:last-child)
css_anchor=".ticket:last-child{page-break-after:auto}"
css_add=css_anchor+"""
.resumo-venda{position:absolute;left:-10000px;top:0;width:360px;background:#fff;color:#000;padding:4mm 4.5mm 5mm;font-family:inherit;font-size:12.5px;line-height:1.3}
.rz-h{text-align:center;border-bottom:2px solid #000;padding-bottom:5px;margin-bottom:6px}
.rz-h .t{font-weight:900;font-size:16px;letter-spacing:.06em}
.rz-h .s{font-size:10px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-top:1px}
.rz-h .n{font-size:11px;color:#1f2937;margin-top:3px;font-weight:700}
.rz-emp{font-weight:900;font-size:14px;text-align:center;letter-spacing:.02em}
.rz-tr{text-align:center;font-weight:800;font-size:13px;margin:1px 0}
.rz-line{border-top:1px dashed #9ca3af;margin:6px 0}
.rz-row{display:flex;justify-content:space-between;gap:8px;font-size:12px;padding:1.5px 0}
.rz-row .k{color:#374151;font-weight:600}
.rz-row .vv{font-weight:700;text-align:right}
.rz-px{font-size:12px;padding:2px 0;display:flex;justify-content:space-between;gap:8px;border-bottom:1px dotted #e5e7eb}
.rz-px .nm{font-weight:700}
.rz-px .pl{font-weight:800;white-space:nowrap}
.rz-sech{font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#374151;margin:4px 0 2px}
.rz-tot{display:flex;justify-content:space-between;font-weight:900;font-size:14px;border-top:2px solid #000;margin-top:4px;padding-top:4px}
.rz-pg{display:inline-block;font-weight:900;font-size:11px;padding:1px 8px;border-radius:6px}
.rz-ass{margin-top:10px;font-size:11px}
.rz-ln{border-bottom:1px solid #000;height:14px;margin-top:2px}
.rz-foot{text-align:center;font-size:9px;color:#374151;margin-top:8px;border-top:1px dashed #9ca3af;padding-top:4px}"""
if '.resumo-venda{' not in s:
    s=s.replace(css_anchor,css_add,1)

# B) Bloco HTML do resumo, depois do @endfor
end_anchor="@endfor\n"
block=r'''@endfor

@php
  $rzPag = (float) $p->pago; $rzDev = (float) $p->devido;
  $rzSit = $p->cancelada ? ['CANCELADA','#fee2e2','#b91c1c'] : ($rzDev > 0.009 ? ['EM ABERTO','#fef3c7','#92400e'] : ['PAGO','#dcfce7','#166534']);
@endphp
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
  <div class="rz-row"><span class="k">Forma de pagamento</span><span class="vv">{{ \App\Models\Passagem::FORMAS[$p->forma] ?? ucfirst((string) $p->forma) }}</span></div>
  <div class="rz-row"><span class="k">Recebido</span><span class="vv">R$ {{ number_format($rzPag,2,',','.') }}</span></div>
  <div class="rz-row"><span class="k">Em aberto</span><span class="vv">R$ {{ number_format($rzDev,2,',','.') }}</span></div>
  <div class="rz-tot"><span>TOTAL</span><span>R$ {{ number_format((float) $p->total,2,',','.') }}</span></div>
  <div class="rz-row" style="margin-top:4px"><span class="k">Situação</span><span class="vv"><span class="rz-pg" style="background:{{ $rzSit[1] }};color:{{ $rzSit[2] }}">{{ $rzSit[0] }}</span></span></div>
  <div class="rz-line"></div>
  @if($p->comprador_nome)<div class="rz-row"><span class="k">Comprador</span><span class="vv">{{ $p->comprador_nome }}</span></div>@endif
  <div class="rz-ass"><span style="font-weight:700">Conferido por:</span><div class="rz-ln"></div></div>
  <div class="rz-foot">RESUMO INTERNO · não é bilhete de embarque · impresso {{ now()->format('d/m/Y H:i') }}</div>
</div>
'''
if 'class="resumo-venda"' not in s:
    # substitui apenas o primeiro @endfor (o do loop de bilhetes)
    s=s.replace(end_anchor, block, 1)

open(f,'w',encoding='utf-8').write(s)
print("css:", '.resumo-venda{' in s, "| bloco:", 'class="resumo-venda"' in s, "| changed:", s!=orig)
