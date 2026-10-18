@extends('layouts.app')
@section('titulo','Encomenda '.$e->numeroFormatado())
@section('conteudo')
@php $u = auth()->user(); $empresa = \App\Support\Tenant::empresa(); @endphp
<div class="max-w-4xl mx-auto space-y-4" x-data="{ modal:null }">

<div class="flex items-center justify-between gap-2">
  <div>
    <a href="{{ route('encomendas.index') }}" class="text-sm text-slate-500 hover:underline">← Encomendas</a>
    <h1 class="text-2xl font-extrabold">#{{ $e->numeroFormatado() }} <span class="chip st-{{ $e->cor() }} align-middle">{{ $e->statusTexto() }}</span></h1>
    <div class="text-sm text-slate-500">{{ $e->created_at->format('d/m/Y H:i') }} · {{ $e->operador?->name }} · {{ $e->setor }}</div>
  </div>
</div>

{{-- AÇÕES --}}
<div class="card p-3 flex flex-wrap gap-2">
  @if($u->podeImprimir())
  <button class="btn btn-ghost btn-sm" onclick="FBRE.abrirImpressao('{{ route('imprimir.comprovante',$e) }}')">🧾 Comprovante</button>
  <button class="btn btn-ghost btn-sm" onclick="FBRE.abrirImpressao('{{ route('imprimir.etiqueta',$e) }}')">🏷 Etiquetas ({{ $e->volumes }})</button>
  @if($empresa->temRecurso('print_agent'))
    <button class="btn btn-ghost btn-sm" @click="direto('comprovante')">⚡ Comprovante direto</button>
    <button class="btn btn-ghost btn-sm" @click="direto('etiqueta')">⚡ Etiqueta direta</button>
  @endif
  @endif
  <span class="flex-1"></span>
  @if($u->podeBaixar() && !$e->cancelada)
    @if($e->devido > 0)<button class="btn btn-accent btn-sm" @click="modal='pagar'">💵 Registrar pagamento</button>@endif
    @if(!$e->entregue)<button class="btn btn-primary btn-sm" @click="modal='entregar'">📦 Dar baixa na entrega</button>@endif
  @endif
  @if(($u->isAdmin()||$u->isSuper()) && !$e->cancelada)
    <form method="post" action="{{ route('encomendas.cancelar',$e) }}" onsubmit="return confirm('Cancelar esta encomenda?')">@csrf<button class="btn btn-danger btn-sm">Cancelar</button></form>
  @endif
</div>

<div class="grid md:grid-cols-2 gap-4">
  <div class="card p-4 space-y-3">
    <h2 class="font-bold text-sm text-slate-500 uppercase">Rota</h2>
    <div class="text-lg font-bold">{{ $e->origem }} → {{ $e->destino }}</div>
    <div class="text-sm text-slate-600">Transporte: <b>{{ $e->embarcacao?->nome ?? '—' }}</b> · Volumes: <b>{{ $e->volumes }}</b></div>
    <h2 class="font-bold text-sm text-slate-500 uppercase pt-2">Remetente</h2>
    <div><b>{{ $e->remetente_nome }}</b><div class="text-sm text-slate-500">{{ $e->remetente_telefone }} {{ $e->remetente_documento }}</div></div>
    <h2 class="font-bold text-sm text-slate-500 uppercase pt-2">Destinatário</h2>
    <div><b>{{ $e->destinatario_nome }}</b><div class="text-sm text-slate-500">{{ $e->destinatario_telefone }} {{ $e->destinatario_documento }}</div></div>
    @if($e->observacao)<div class="text-sm bg-amber-50 border border-amber-200 rounded-lg p-2">{{ $e->observacao }}</div>@endif
    @if($e->entregue)<div class="text-sm bg-emerald-50 border border-emerald-200 rounded-lg p-2">Entregue em {{ $e->entregue_em->format('d/m/Y H:i') }} por {{ $e->entreguePor?->name }}@if($e->entregue_para) para <b>{{ $e->entregue_para }}</b>@endif</div>@endif
  </div>

  <div class="card p-4 space-y-3">
    <h2 class="font-bold text-sm text-slate-500 uppercase">Produtos</h2>
    <table class="w-full text-sm"><tbody class="divide-y">
      @foreach($e->itens as $i)
        <tr><td class="py-1.5">{{ $i->descricao }}@if($i->observacao)<div class="text-xs text-slate-400">{{ $i->observacao }}</div>@endif</td>
            <td class="py-1.5 text-right text-slate-500 whitespace-nowrap">{{ (float)$i->quantidade }} × {{ number_format($i->valor_unitario,2,',','.') }}</td>
            <td class="py-1.5 text-right font-semibold">{{ number_format($i->valor_total,2,',','.') }}</td></tr>
      @endforeach
    </tbody></table>
    <div class="rounded-xl p-3 text-white text-sm space-y-1" style="background: var(--cor1)">
      <div class="flex justify-between opacity-80"><span>Subtotal</span><span>R$ {{ number_format($e->subtotal,2,',','.') }}</span></div>
      @if($e->desconto>0)<div class="flex justify-between opacity-80"><span>Desconto</span><span>- R$ {{ number_format($e->desconto,2,',','.') }}</span></div>@endif
      <div class="flex justify-between text-lg font-extrabold"><span>Total</span><span>R$ {{ number_format($e->total,2,',','.') }}</span></div>
      <div class="flex justify-between"><span>Pago</span><span>R$ {{ number_format($e->pago,2,',','.') }}</span></div>
      <div class="flex justify-between font-bold {{ $e->devido>0?'text-amber-300':'text-emerald-300' }}"><span>Devido</span><span>R$ {{ number_format($e->devido,2,',','.') }}</span></div>
      <div class="text-xs opacity-70 pt-1">Forma: {{ \App\Models\Encomenda::FORMAS[$e->forma] }}</div>
    </div>
    <h2 class="font-bold text-sm text-slate-500 uppercase pt-1">Pagamentos</h2>
    @forelse($e->pagamentos as $p)
      <div class="flex justify-between text-sm"><span>{{ $meios[$p->meio] }} <span class="text-slate-400">· {{ $p->created_at->format('d/m H:i') }} · {{ $p->user?->name }}</span></span><b>R$ {{ number_format($p->valor,2,',','.') }}</b></div>
    @empty <div class="text-sm text-slate-400">Nenhum pagamento.</div> @endforelse
  </div>
</div>

{{-- MODAL PAGAR --}}
<div x-show="modal==='pagar'" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-0 sm:p-4" @click.self="modal=null">
  <form method="post" action="{{ route('encomendas.pagar',$e) }}" class="bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl p-5 space-y-3" x-data="{ d:0,p:0,de:0,c:0,ds:0, devido:{{ (float)$e->devido }} }">
    @csrf
    <h3 class="text-lg font-bold">Registrar pagamento</h3>
    <div class="text-sm">Falta pagar: <b class="text-red-600">R$ {{ number_format($e->devido,2,',','.') }}</b></div>
    <div class="grid grid-cols-2 gap-2">
      <div><label class="label">Dinheiro</label><input name="dinheiro" type="number" step="0.01" min="0" inputmode="decimal" class="input text-right" x-model.number="d"></div>
      <div><label class="label">PIX</label><input name="pix" type="number" step="0.01" min="0" inputmode="decimal" class="input text-right" x-model.number="p"></div>
      <div><label class="label">Débito</label><input name="debito" type="number" step="0.01" min="0" inputmode="decimal" class="input text-right" x-model.number="de"></div>
      <div><label class="label">Crédito</label><input name="credito" type="number" step="0.01" min="0" inputmode="decimal" class="input text-right" x-model.number="c"></div>
      <div><label class="label">Desconto</label><input name="desconto" type="number" step="0.01" min="0" inputmode="decimal" class="input text-right" x-model.number="ds"></div>
      <div class="flex items-end"><button type="button" class="btn btn-ghost w-full" @click="d=Math.max(devido-p-de-c-ds,0)">Tudo em dinheiro</button></div>
    </div>
    <div class="text-sm flex justify-between"><span>Restará:</span><b x-text="FBRE.moeda(Math.max(devido-d-p-de-c-ds,0))"></b></div>
    @if(!$e->entregue)
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="entregar" value="1" class="rounded w-5 h-5"> Dar baixa na entrega também</label>
      <input name="entregue_para" class="input" placeholder="Quem retirou (opcional)">
    @endif
    <div class="flex gap-2 pt-1">
      <button type="button" class="btn btn-ghost flex-1" @click="modal=null">Cancelar</button>
      <button class="btn btn-accent flex-1">Confirmar</button>
    </div>
  </form>
</div>

{{-- MODAL ENTREGAR --}}
<div x-show="modal==='entregar'" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-0 sm:p-4" @click.self="modal=null">
  <form method="post" action="{{ route('encomendas.entregar',$e) }}" class="bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl p-5 space-y-3">
    @csrf
    <h3 class="text-lg font-bold">Baixa na entrega</h3>
    @if($e->devido>0)<div class="text-sm bg-red-50 border border-red-200 rounded-lg p-2">Atenção: ainda faltam <b>R$ {{ number_format($e->devido,2,',','.') }}</b>. Para receber e entregar, use "Registrar pagamento".</div>@endif
    <div><label class="label">Quem retirou</label><input name="entregue_para" class="input" placeholder="Nome de quem recebeu" autofocus></div>
    <div class="flex gap-2 pt-1">
      <button type="button" class="btn btn-ghost flex-1" @click="modal=null">Cancelar</button>
      <button class="btn btn-primary flex-1">Confirmar entrega</button>
    </div>
  </form>
</div>

</div>
@endsection

@push('scripts')
<script>
async function direto(tipo){
  const r = await fetch('{{ url('/imprimir/direto/'.$e->id) }}/'+tipo, {method:'POST', headers:{'X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json'}});
  const j = await r.json();
  alert(j.ok ? 'Enviado para a impressora.' : (j.erro||'Falha ao enviar.'));
}
@if(request()->boolean('print'))
  window.addEventListener('load', () => FBRE.abrirImpressao('{{ route('imprimir.comprovante',$e) }}'));
@endif
</script>
@endpush
