@extends('layouts.app')
@section('titulo','Frete '.$f->numeroFormatado())
@php $__e=\App\Support\Tenant::empresa(); $cor = (($__e?->config('cores_por_modulo', true) ?? true) ? \App\Support\Modulos::hex('frete') : ($__e?->cor_primaria ?? '#0b1a33')); $u=auth()->user(); @endphp
@section('conteudo')
<div class="max-w-2xl mx-auto space-y-4">
  <div class="rounded-2xl px-4 py-3 text-white flex items-center gap-3" style="background:{{ $cor }}">
    <span class="text-2xl">🚢</span>
    <div class="flex-1"><div class="font-bold leading-tight">Frete #{{ $f->numeroFormatado() }}</div><div class="text-[11px] opacity-80">{{ $f->origem }} → {{ $f->destino }}</div></div>
    <span class="chip st-{{ $f->cor() }}">{{ $f->statusTexto() }}</span>
  </div>
  <div class="card p-4 space-y-1 text-sm">
    <div class="flex justify-between"><span class="text-slate-500">Remetente</span><b>{{ $f->remetente_nome }}</b></div>
    <div class="flex justify-between"><span class="text-slate-500">Destinatário</span><b>{{ $f->destinatario_nome }}</b></div>
    <div class="flex justify-between"><span class="text-slate-500">Volumes / Peso</span><b>{{ $f->volumes }} vol · {{ $f->peso ? number_format($f->peso,0,',','.').' kg' : '—' }}</b></div>
    <div class="flex justify-between"><span class="text-slate-500">Operador</span><b>{{ $f->operador?->name }}</b></div>
    <div class="flex justify-between"><span class="text-slate-500">Quem paga</span><b>{{ $f->quem_paga==='remetente' ? 'Remetente' : 'Destinatário' }} · {{ $f->pagadorNome() }}</b></div>
    @if($f->nome_responsavel)<div class="flex justify-between"><span class="text-slate-500">Responsável</span><b>{{ $f->nome_responsavel }}</b></div>@endif
    @if($f->nf_numero || $f->nf_chave)<div class="flex justify-between"><span class="text-slate-500">Nota fiscal</span><b>{{ $f->nf_numero ? 'Nº '.$f->nf_numero : '' }}@if($f->nf_chave)<span class="block text-[10px] font-normal text-slate-400 break-all">{{ $f->nf_chave }}</span>@endif</b></div>@endif
  </div>

  @if($f->veiculos->count())
  <div class="card p-4">
    <div class="font-semibold mb-2">🚗 Veículos ({{ $f->veiculos->count() }})</div>
    <div class="space-y-2">
      @foreach($f->veiculos as $v)
        <div class="text-sm bg-slate-50 rounded-lg p-2">
          <b>{{ $v->placa ?: 'Sem placa' }}</b> · {{ $v->modelo ?: '—' }}{{ $v->cor ? ' · '.$v->cor : '' }}
          @if($v->documento)<div class="text-xs text-slate-500">Doc: {{ $v->documento }}</div>@endif
          @if($v->obs)<div class="text-xs text-slate-400">{{ $v->obs }}</div>@endif
        </div>
      @endforeach
    </div>
  </div>
  @endif
  <div class="card p-4"><div class="font-semibold mb-1">Carga</div><div class="text-sm text-slate-600">{{ $f->descricao }}</div>
    @if($f->itens->count())
    <div class="border-t mt-2 pt-2 space-y-1">
      @foreach($f->itens as $it)
        <div class="flex justify-between text-sm"><span>{{ $it->descricao }} <span class="text-slate-400">× {{ rtrim(rtrim(number_format((float)$it->quantidade,2,',','.'),'0'),',') }}</span></span><b>R$ {{ number_format((float)$it->valor_total,2,',','.') }}</b></div>
      @endforeach
    </div>
    @endif
    <div class="border-t mt-2 pt-2 space-y-1 text-sm">
      <div class="flex justify-between text-lg font-bold" style="color:{{ $cor }}"><span>Total</span><span>R$ {{ number_format($f->total,2,',','.') }}</span></div>
      <div class="flex justify-between"><span>Pago</span><span>R$ {{ number_format($f->pago,2,',','.') }}</span></div>
      <div class="flex justify-between {{ $f->devido>0.009?'text-red-600 font-semibold':'text-emerald-600' }}"><span>Devido</span><span>R$ {{ number_format($f->devido,2,',','.') }}</span></div>
    </div>
  </div>
  @if(!$f->cancelada && $f->devido>0.009 && $u->permite('frete','baixa'))
  <form method="post" action="{{ route('fretes.pagar',$f) }}" class="card p-4 space-y-3">@csrf
    <div class="font-semibold">Receber pagamento</div>
    <div class="grid grid-cols-2 gap-2">
      <div><label class="text-[11px] text-slate-500">Dinheiro</label><input name="dinheiro" class="input" x-money></div>
      <div><label class="text-[11px] text-slate-500">PIX</label><input name="pix" class="input" x-money></div>
      <div><label class="text-[11px] text-slate-500">Débito</label><input name="debito" class="input" x-money></div>
      <div><label class="text-[11px] text-slate-500">Crédito</label><input name="credito" class="input" x-money></div>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="entregar" value="1" class="w-5 h-5 rounded"> Confirmar entrega ao pagar</label>
    <button class="btn w-full text-white" style="background:{{ $cor }}">Registrar pagamento</button>
  </form>
  @endif
  @if(!$f->cancelada && !$f->entregue && ($f->devido<=0.009 || $f->forma==='faturado') && $u->permite('frete','baixa'))
  <form method="post" action="{{ route('fretes.entregar',$f) }}">@csrf<button class="btn btn-ghost w-full">✅ Confirmar entrega</button></form>
  @endif
  <div class="flex gap-2">
    <button onclick="FBRE.abrirImpressao('{{ route('fretes.show',$f) }}?print=1')" class="btn btn-ghost flex-1">🖨 Comprovante</button>
    @if(!$f->cancelada && ($u->isAdmin()||$u->isSuper()))<button type="button" class="btn btn-danger" x-data @click="$refs.c.classList.toggle('hidden')">Cancelar</button>@endif
  </div>
  @if(!$f->cancelada && ($u->isAdmin()||$u->isSuper()))
  <form method="post" action="{{ route('fretes.cancelar',$f) }}" class="card p-4 hidden" x-ref="c">@csrf
    <label class="label">Motivo</label><input name="motivo" class="input mb-2" required minlength="3"><button class="btn btn-danger w-full">Confirmar cancelamento</button></form>
  @endif
  @if($f->cancelada)<div class="card p-3 text-center text-slate-500 text-sm">Cancelado: {{ $f->motivo_cancelamento }}</div>@endif
</div>
@endsection
@push('scripts')
@if(request('print'))
<script>document.addEventListener('DOMContentLoaded',()=>{
  const d=@js(['numero'=>$f->numeroFormatado(),'origem'=>$f->origem,'destino'=>$f->destino,'vol'=>$f->volumes,'peso'=>$f->peso,'rem'=>$f->remetente_nome,'dest'=>$f->destinatario_nome,'desc'=>$f->descricao,'total'=>(float)$f->total,'pago'=>(float)$f->pago,'devido'=>(float)$f->devido,'emp'=>\App\Support\Tenant::empresa()->nomeExibicao(),'cnpj'=>\App\Support\Tenant::empresa()->cnpj,'rodape'=>\App\Support\Tenant::empresa()->rodape_comprovante]);
  const esc=s=>String(s??'').replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); const moeda=v=>(Number(v)||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
  const html=`<!doctype html><html><head><meta charset="utf-8"><title>Frete ${esc(d.numero)}</title><style>@page{margin:0;size:80mm auto}body{margin:0;padding:3mm 4mm;width:80mm;font-family:"Courier New",monospace;font-size:11.5px}.c{text-align:center}.r{text-align:right}.b{font-weight:bold}.xl{font-size:18px}.lg{font-size:14px}.sm{font-size:10px;color:#333}hr{border:0;border-top:1px dashed #000;margin:4px 0}table{width:100%;border-collapse:collapse}td{padding:2px 0}</style></head><body onload="window.print()">
  <div class="c b lg">${esc((d.emp||'FBRE').toUpperCase())}</div>${d.cnpj?`<div class="c sm">CNPJ ${esc(d.cnpj)}</div>`:''}<hr><div class="c b xl">FRETE ${esc(d.numero)}</div><hr>
  <table><tr><td class="b">ORIGEM</td><td class="r">${esc(d.origem)}</td></tr><tr><td class="b">DESTINO</td><td class="r">${esc(d.destino)}</td></tr><tr><td class="b">VOLUMES</td><td class="r">${d.vol}</td></tr>${d.peso?`<tr><td class="b">PESO</td><td class="r">${d.peso} kg</td></tr>`:''}</table><hr>
  <div class="b">REMETENTE</div><div>${esc(d.rem)}</div><div class="b" style="margin-top:3px">DESTINATÁRIO</div><div>${esc(d.dest)}</div><hr><div class="b">CARGA</div><div class="sm">${esc(d.desc)}</div><hr>
  <table><tr class="lg"><td class="b">TOTAL</td><td class="r b">${moeda(d.total)}</td></tr><tr><td>Pago</td><td class="r">${moeda(d.pago)}</td></tr><tr><td class="b">DEVIDO</td><td class="r b">${moeda(d.devido)}</td></tr></table>${d.rodape?`<hr><div class="c sm">${esc(d.rodape)}</div>`:''}<hr><div class="c sm">Apresente para retirar a carga.</div><br><br></body></html>`;
  const w=window.open('','fbre_print','width=420,height=640'); if(w){w.document.open();w.document.write(html);w.document.close();}
});</script>
@endif
@endpush
