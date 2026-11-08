@extends('layouts.app')
@section('titulo','Passagem '.$p->numeroFormatado())
@php $__e=\App\Support\Tenant::empresa(); $cor = (($__e?->config('cores_por_modulo', true) ?? true) ? \App\Support\Modulos::hex('passagem') : ($__e?->cor_primaria ?? '#0b1a33')); $u=auth()->user(); @endphp
@section('conteudo')
<div class="max-w-2xl mx-auto space-y-4">
  <div class="rounded-2xl px-4 py-3 text-white flex items-center gap-3" style="background:{{ $cor }}">
    <span class="text-2xl">🎫</span>
    <div class="flex-1"><div class="font-bold leading-tight">Passagem #{{ $p->numeroFormatado() }}</div>
      <div class="text-[11px] opacity-80">{{ $p->origem }} → {{ $p->destino }} @if($p->data_viagem)· {{ $p->data_viagem->format('d/m/Y') }}@endif</div></div>
    <span class="chip st-{{ $p->cor() }}">{{ $p->statusTexto() }}</span>
  </div>

  <div class="card p-4 space-y-1 text-sm">
    @if($p->tipo==='camarote' && $p->camarote)<div class="flex justify-between"><span class="text-slate-500">Suíte/Camarote</span><b>🛏️ {{ $p->camarote->nome }}</b></div>@endif
    <div class="flex justify-between"><span class="text-slate-500">Embarcação</span><b>{{ $p->embarcacao?->nome ?: '—' }}</b></div>
    <div class="flex justify-between"><span class="text-slate-500">Viagem</span><b>@if($p->data_viagem){{ ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][$p->data_viagem->dayOfWeek] }} {{ $p->data_viagem->format('d/m/Y') }}@else—@endif</b></div>
    @if($p->comprador_nome)<div class="flex justify-between"><span class="text-slate-500">Comprador</span><b>{{ $p->comprador_nome }}</b></div>@endif
    <div class="flex justify-between"><span class="text-slate-500">Operador</span><b>{{ $p->operador?->name }}</b></div>
    <div class="flex justify-between"><span class="text-slate-500">Forma</span><b>{{ \App\Models\Passagem::FORMAS[$p->forma] ?? $p->forma }}</b></div>
  </div>

  <div class="card p-4">
    <div class="font-semibold mb-2">Passageiros</div>
    <div class="divide-y">
      @foreach($p->passageiros as $pax)
        <div class="flex justify-between py-2 text-sm">
          <div class="flex gap-2">
@php $miniatura = $pax->foto ?: $pax->foto_rg; @endphp @if($miniatura)<a href="/{{ $pax->foto_rg ?: $pax->foto }}" target="_blank" title="Ver documento"><img src="/{{ $miniatura }}" class="h-10 w-10 object-cover rounded border shrink-0"></a>@endif
            <div>{{ $pax->nome }}
            <span class="inline-block text-[10px] font-bold px-1.5 py-0.5 rounded {{ $pax->checkin ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $pax->checkin ? 'check-in ✓' : 'sem check-in' }}</span>
            <div class="text-xs text-slate-500">{{ $pax->tipo }} ({{ $pax->percentual }}%)@if($pax->poltrona) · poltrona {{ $pax->poltrona }}@endif @if($pax->documento)· {{ $pax->documento }}@endif</div>@if($pax->valor_integral > $pax->valor_total + 0.009)<div class="text-xs text-amber-600">Preço normal R$ {{ number_format($pax->valor_integral,2,',','.') }} · Pago R$ {{ number_format($pax->valor_total,2,',','.') }}</div>@endif<div class="text-xs text-slate-400">Transp. R$ {{ number_format($pax->valor_passagem,2,',','.') }}@if(($pax->valor_alimentacao + $pax->valor_taxa)>0) · Alim.+Adic. R$ {{ number_format($pax->valor_alimentacao + $pax->valor_taxa,2,',','.') }}@endif</div>@if($pax->documentos)<div class="flex flex-wrap gap-1 mt-1">@foreach($pax->documentos as $doc)<a href="/{{ $doc }}" target="_blank" title="documento anexado"><img src="/{{ $doc }}" class="h-9 w-9 object-cover rounded border shrink-0"></a>@endforeach</div>@endif</div></div>
          <div class="text-right font-semibold">R$ {{ number_format($pax->valor_total,2,',','.') }}</div>
        </div>
      @endforeach
    </div>
    <div class="border-t mt-2 pt-2 space-y-1 text-sm">
      <div class="flex justify-between"><span>Tarifa de Transporte</span><span>R$ {{ number_format($p->subtotal,2,',','.') }}</span></div>
      <div class="flex justify-between"><span>Alimentação + Adicional</span><span>R$ {{ number_format($p->alimentacao_total + $p->taxa_total,2,',','.') }}</span></div>
      @if($p->desconto>0)<div class="flex justify-between text-amber-600"><span>Desconto</span><span>- R$ {{ number_format($p->desconto,2,',','.') }}</span></div>@endif
      <div class="flex justify-between text-lg font-bold" style="color:{{ $cor }}"><span>Total</span><span>R$ {{ number_format($p->total,2,',','.') }}</span></div>
      <div class="flex justify-between"><span>Pago</span><span>R$ {{ number_format($p->pago,2,',','.') }}</span></div>
      <div class="flex justify-between {{ $p->devido>0.009?'text-red-600 font-semibold':'text-emerald-600' }}"><span>Devido</span><span>R$ {{ number_format($p->devido,2,',','.') }}</span></div>
    </div>
  </div>

  {{-- baixa de pagamento --}}
  @if(!$p->cancelada && $p->devido>0.009 && $u->permite('passagem','baixa'))
  <form method="post" action="{{ route('passagens.pagar',$p) }}" class="card p-4 space-y-3" x-data="{d:0,px:0,db:0,cr:0}">
    @csrf
    <div class="font-semibold">Receber pagamento</div>
    <div class="grid grid-cols-2 gap-2">
      <div><label class="text-[11px] text-slate-500">Dinheiro</label><input name="dinheiro" class="input" x-money="d"></div>
      <div><label class="text-[11px] text-slate-500">PIX</label><input name="pix" class="input" x-money="px"></div>
      <div><label class="text-[11px] text-slate-500">Débito</label><input name="debito" class="input" x-money="db"></div>
      <div><label class="text-[11px] text-slate-500">Crédito</label><input name="credito" class="input" x-money="cr"></div>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="embarcar" value="1" class="w-5 h-5 rounded"> Confirmar embarque ao pagar</label>
    <button class="btn w-full text-white" style="background:{{ $cor }}">Registrar pagamento</button>
  </form>
  @endif

  {{-- embarque --}}
  @if(!$p->cancelada && !$p->embarcou && ($p->devido<=0.009 || $p->forma==='faturado') && $u->permite('passagem','baixa'))
  <form method="post" action="{{ route('passagens.embarcar',$p) }}">@csrf
    <button class="btn btn-ghost w-full">✅ Confirmar embarque</button>
  </form>
  @endif

  @php $empP = \App\Support\Tenant::empresa(); @endphp
  @if(!$p->cancelada && $empP && $empP->usaPix('passagem') && $p->devido > 0)
  <button onclick="FBRE.abrirImpressao('{{ route('imprimir.pix.passagem',$p) }}')" class="btn btn-accent w-full">💠 Gerar PIX dinâmico (imprimir QR)</button>
  @endif

  <button type="button" onclick="imprimirDiretoPassagem(this)" class="btn btn-ghost w-full">⚡ Imprimir direto (1 por passageiro)</button>
  <div x-data="{ painel:'' }">
  <div class="flex gap-2">
    <button onclick="FBRE.abrirImpressao('{{ route('passagens.comprovante',$p) }}')" class="btn btn-ghost flex-1">🖨 Comprovante</button>
    <button onclick="window.open('{{ route('passagens.comprovante',$p) }}?modo=pdf','_blank')" class="btn btn-ghost flex-1">📲 Enviar PDF (WhatsApp)</button>
    @if(!$p->cancelada && ($u->isAdmin()||$u->isSuper()))
    <button type="button" class="btn btn-danger" @click="painel = painel==='cancel' ? '' : 'cancel'">↩ Devolução</button>
    @endif
    @if(!$p->cancelada && !$p->transferida && ($empP?->config('usa_cancel_transporte')) && ($u->isAdmin()||$u->isSuper()))
    <button type="button" class="btn btn-ghost" style="border:1px solid #6366f1;color:#4f46e5" @click="painel = painel==='transf' ? '' : 'transf'">↪ Transferir</button>
    @endif
    @if(!$p->cancelada && !$p->transferida && ($u->isAdmin()||$u->isSuper()))
    <button type="button" class="btn btn-ghost" style="border:1px solid #06b6d4;color:#0891b2" @click="painel = painel==='troca' ? '' : 'troca'">📅 Trocar viagem</button>
    @endif
  </div>
  @if(!$p->cancelada && ($u->isAdmin()||$u->isSuper()))
  <div x-show="painel==='cancel'" x-cloak>
  <form method="post" action="{{ route('passagens.cancelar',$p) }}" class="card p-4">@csrf
    @php $embc = $p->embarcacao; $pa = $embc?->cancelAntes() ?? 10; $pd = $embc?->cancelDepois() ?? 30;
         $jaSaiu = $p->jaSaiu(); @endphp
    <div class="font-bold text-sm mb-1">↩ Devolução ao passageiro</div>
    <div class="rounded-lg bg-slate-50 border p-2.5 text-[12px] text-slate-600 mb-2 space-y-2">
      <div class="flex gap-2"><span class="shrink-0">🟢</span><span><b>Antes</b> da lancha sair — devolve <b>{{ 100-$pa }}%</b> ao passageiro e retém <b>{{ $pa }}%</b>, que vai pro <b>caixa local</b> (não entra no caixa da empresa).</span></div>
      <div class="flex gap-2"><span class="shrink-0">🔴</span><span><b>Depois</b> que a lancha saiu — devolve <b>{{ 100-$pd }}%</b> ao passageiro e retém <b>{{ $pd }}%</b>, que fica pro <b>dono</b> da lancha.</span></div>
      @if($p->acerto_dono && ! $p->revendedor_id)
      <div class="flex gap-2 text-rose-600"><span class="shrink-0">⚠️</span><span>Esta passagem <b>já foi acertada com o dono</b>. Ele vai ficar <b>devendo o repasse à empresa</b> — descontado no próximo acerto.</span></div>
      @endif
      <div class="pt-0.5"><span class="inline-block rounded px-2 py-1 text-white font-bold text-[11px]" style="background:{{ $jaSaiu ? '#e11d48' : '#16a34a' }}">Esta passagem agora: {{ $jaSaiu ? 'DEPOIS — retém '.$pd.'%' : 'ANTES — retém '.$pa.'%' }}</span></div>
    </div>
    <label class="label">Motivo da devolução</label>
    <input name="motivo" class="input mb-2" required minlength="3" placeholder="ex.: passageiro desistiu">
    <button class="btn btn-danger w-full">Confirmar devolução</button>
  </form>
  </div>
  @endif
  @if(!$p->cancelada && !$p->transferida && ($empP?->config('usa_cancel_transporte')) && ($u->isAdmin()||$u->isSuper()))
  <div x-show="painel==='transf'" x-cloak>
  <form method="post" action="{{ route('passagens.transferir',$p) }}" class="card p-4">@csrf
    <div class="font-bold text-sm mb-2">↪ Transferir pra outro transporte</div>
    <div x-data="transfCal(@js($linhasShow ?? []), @js($p->origem), @js($p->destino), @js(route('passagens.assentos')))">
      <label class="label">Transporte destino</label>
      <select name="embarcacao_id" x-model="embSel" @change="onEmb()" class="input mb-2" required>
        <option value="">— escolher —</option>
        @foreach(\App\Models\Embarcacao::where('ativo',true)->orderBy('nome')->get() as $b)@if($b->id !== $p->embarcacao_id)<option value="{{ $b->id }}">{{ $b->nome }}</option>@endif @endforeach
      </select>
      <label class="label">Data da viagem</label>
      <button type="button" @click="abrir()" class="input mb-2 w-full text-left font-semibold" :class="viagemDia===null?'text-slate-400':''">
        📅 <span x-text="viagemDia===null ? 'escolha o transporte' : rotulo(viagemData)"></span>
      </button>
      <input type="hidden" name="data_viagem" :value="viagemData">
      <div x-show="calAberto" x-cloak @click="calAberto=false" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
        <div @click.stop class="bg-white rounded-2xl border shadow-2xl p-3 w-72 max-w-full text-slate-700">
          <div class="text-center mb-2"><span x-show="linhaNome" class="block text-[11px] font-semibold text-slate-400" x-text="linhaNome"></span><span class="text-sm font-bold" style="color:var(--cor1)" x-text="rotulo(viagemData)"></span></div>
          <div class="flex items-center justify-between mb-1"><button type="button" @click="mudarMes(-1)" class="w-8 h-8 rounded hover:bg-slate-100 text-lg leading-none">‹</button><div class="text-sm font-bold capitalize" x-text="nomeMes"></div><button type="button" @click="mudarMes(1)" class="w-8 h-8 rounded hover:bg-slate-100 text-lg leading-none">›</button></div>
          <div class="grid grid-cols-7 text-center text-[10px] font-bold text-slate-400 mb-1"><template x-for="(d,i) in ['D','S','T','Q','Q','S','S']" :key="i"><div x-text="d"></div></template></div>
          <div class="grid grid-cols-7 gap-0.5"><template x-for="c in calDias" :key="c.iso"><button type="button" :disabled="!c.valido" @click="c.valido && escolher(c.iso)" class="h-9 rounded text-sm transition" :class="c.sel ? 'text-white font-bold' : (c.valido ? 'font-bold hover:brightness-95' : (c.outroMes ? 'text-slate-200' : 'text-slate-300 cursor-not-allowed'))" :style="c.sel ? 'background:var(--cor1)' : (c.valido ? 'color:var(--cor1);background:color-mix(in srgb, var(--cor2) 20%, white)' : '')" x-text="c.dia"></button></template></div>
          <div class="text-[10px] text-slate-400 mt-2 text-center">Só os dias de viagem da linha ficam disponíveis.</div>
        </div>
      </div>
      <div x-show="usaPoltrona" x-cloak class="mb-2 rounded-lg border border-slate-200 p-2">
        <label class="label !text-xs">🪑 Poltronas no destino</label>
        <div class="text-[11px] mb-1" :class="ocupadas.length?'text-rose-500':'text-emerald-600'"><span x-show="ocupadas.length">Ocupadas nesse trecho: <b x-text="ocupadasTxt"></b></span><span x-show="!ocupadas.length">Todas livres nesse trecho</span></div>
        @foreach($p->passageiros as $pax)
        <div class="flex items-center gap-2 mb-1">
          <span class="text-xs flex-1 truncate text-slate-600">{{ $pax->nome }}</span>
          <input name="poltronas[{{ $pax->id }}]" value="{{ $pax->poltrona }}" @input="$event.target.value=$event.target.value.toUpperCase()" class="input !min-h-[34px] !w-24 text-center font-bold" :class="poltronaOcupada($event?.target?.value)?'!border-rose-500':''" placeholder="poltrona">
        </div>
        @endforeach
        <p class="text-[11px] text-slate-400">Escolha poltronas livres pra não conflitar com quem já está no trecho.</p>
      </div>
    </div>
    <label class="label">Motivo (opcional)</label>
    <input name="motivo" class="input mb-2" maxlength="300" placeholder="ex.: cliente perdeu a lancha 1">
    <button class="btn w-full text-white" style="background:#4f46e5">Confirmar transferência</button>
    <p class="text-[11px] text-slate-400 mt-1">Cria uma passagem nova (paga) no transporte destino e lança a despesa de transferência aqui.</p>
  </form>
  </div>
  @endif
  @if(!$p->cancelada && !$p->transferida && ($u->isAdmin()||$u->isSuper()))
  <div x-show="painel==='troca'" x-cloak>
  <form method="post" action="{{ route('passagens.trocar-viagem',$p) }}" class="card p-4" x-data="trocaCal(@js($linhasShow ?? []), @js($p->origem), @js((string)$p->embarcacao_id), @js($p->destino), @js(route('passagens.assentos')))">@csrf
    <div class="font-bold text-sm">📅 Trocar a data da viagem (remarcar)</div>
    <p class="text-[12px] text-slate-500 mb-2">Mantém <b>a mesma passagem</b> e <b>o mesmo transporte</b> — só muda o <b>dia da viagem</b>. Use quando o passageiro remarca pra outra data. <b>Não mexe no dinheiro</b> (diferente do cancelamento e da transferência).</p>
    <label class="label">Nova data da viagem</label>
    <button type="button" @click="abrir()" class="input mb-2 w-full text-left font-semibold">📅 <span x-text="viagemData ? rotulo(viagemData) : (viagemDia===null ? 'transporte sem rota — remarque manual' : 'escolher')"></span></button>
    <input type="hidden" name="data_viagem" :value="viagemData">
    <div x-show="calAberto" x-cloak @click="calAberto=false" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
      <div @click.stop class="bg-white rounded-2xl border shadow-2xl p-3 w-72 max-w-full text-slate-700">
        <div class="text-center mb-2"><span x-show="linhaNome" class="block text-[11px] font-semibold text-slate-400" x-text="linhaNome"></span><span class="text-sm font-bold" style="color:var(--cor1)" x-text="rotulo(viagemData)"></span></div>
        <div class="flex items-center justify-between mb-1"><button type="button" @click="mudarMes(-1)" class="w-8 h-8 rounded hover:bg-slate-100 text-lg leading-none">‹</button><div class="text-sm font-bold capitalize" x-text="nomeMes"></div><button type="button" @click="mudarMes(1)" class="w-8 h-8 rounded hover:bg-slate-100 text-lg leading-none">›</button></div>
        <div class="grid grid-cols-7 text-center text-[10px] font-bold text-slate-400 mb-1"><template x-for="(d,i) in ['D','S','T','Q','Q','S','S']" :key="i"><div x-text="d"></div></template></div>
        <div class="grid grid-cols-7 gap-0.5"><template x-for="c in calDias" :key="c.iso"><button type="button" :disabled="!c.valido" @click="c.valido && escolher(c.iso)" class="h-9 rounded text-sm transition" :class="c.sel ? 'text-white font-bold' : (c.valido ? 'font-bold hover:brightness-95' : (c.outroMes ? 'text-slate-200' : 'text-slate-300 cursor-not-allowed'))" :style="c.sel ? 'background:var(--cor1)' : (c.valido ? 'color:var(--cor1);background:color-mix(in srgb, var(--cor2) 20%, white)' : '')" x-text="c.dia"></button></template></div>
        <div class="text-[10px] text-slate-400 mt-2 text-center">Só os dias de viagem da linha ficam disponíveis.</div>
      </div>
    </div>
      <div x-show="usaPoltrona" x-cloak class="mb-2 rounded-lg border border-slate-200 p-2">
        <label class="label !text-xs">🪑 Poltronas nessa data</label>
        <div class="text-[11px] mb-1" :class="ocupadas.length?'text-rose-500':'text-emerald-600'"><span x-show="ocupadas.length">Ocupadas nesse trecho: <b x-text="ocupadasTxt"></b></span><span x-show="!ocupadas.length">Todas livres nesse trecho</span></div>
        @foreach($p->passageiros as $pax)
        <div class="flex items-center gap-2 mb-1">
          <span class="text-xs flex-1 truncate text-slate-600">{{ $pax->nome }}</span>
          <input name="poltronas[{{ $pax->id }}]" value="{{ $pax->poltrona }}" @input="$event.target.value=$event.target.value.toUpperCase()" class="input !min-h-[34px] !w-24 text-center font-bold" :class="poltronaOcupada($event?.target?.value)?'!border-rose-500':''" placeholder="poltrona">
        </div>
        @endforeach
        <p class="text-[11px] text-slate-400">Se a poltrona estiver ocupada na nova data, escolha outra livre.</p>
      </div>
    <label class="label">Motivo (opcional)</label>
    <input name="motivo" class="input mb-2" maxlength="300" placeholder="ex.: passageiro remarcou">
    <button class="btn w-full text-white" style="background:#0891b2">Confirmar troca de data</button>
  </form>
  </div>
  @endif
  </div>
  @if($p->transferida)<div class="card p-3 text-center text-indigo-600 text-sm font-semibold">↪ Transferida @if($p->transferida_para_id)para <a href="{{ route('passagens.show',$p->transferida_para_id) }}" class="underline">nova passagem</a>@endif · {{ $p->motivo_cancelamento }}</div>
  @elseif($p->cancelada)
    <div class="card p-3 border border-rose-200 bg-rose-50 text-center text-sm">
      <div class="font-bold text-rose-700">↩ Devolução{{ $p->cancel_fase==='depois' ? ' · depois da viagem' : ($p->cancel_fase==='antes' ? ' · antes da viagem' : '') }}</div>
      @if($p->cancel_retido !== null)
        <div class="text-rose-600 text-[12px] mt-0.5">Retido R$ {{ number_format((float)$p->cancel_retido,2,',','.') }}{{ $p->cancel_fase==='depois' ? ' (dono)' : ($p->cancel_fase==='antes' ? ' (caixa local)' : '') }} · Devolvido R$ {{ number_format((float)$p->cancel_reembolso,2,',','.') }}</div>
      @endif
      @if($p->motivo_cancelamento)<div class="text-slate-500 text-[12px] mt-0.5">{{ $p->motivo_cancelamento }}</div>@endif
    </div>
  @endif
  @if($p->origem_transferencia_id)<div class="card p-3 text-center text-indigo-600 text-sm">↪ Esta passagem veio de uma <a href="{{ route('passagens.show',$p->origem_transferencia_id) }}" class="underline">transferência</a>.</div>@endif
</div>
@endsection
@push('scripts')
<script>
function imprimirDiretoPassagem(btn){
  const txt = btn.textContent;
  btn.disabled = true; btn.textContent = 'Enviando pra impressora...';
  fetch('{{ route('passagens.imprimir-direto',$p) }}', {method:'POST', headers:{'X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json'}})
    .then(r=>r.json())
    .then(d=>{
      if(d && d.ok){ btn.textContent = '✅ Enviado ('+d.jobs+' bilhete'+(d.jobs>1?'s':'')+')'; setTimeout(()=>{btn.textContent=txt; btn.disabled=false;}, 2500); }
      else { alert((d && d.erro) || 'Não consegui enviar pra impressora.'); btn.textContent=txt; btn.disabled=false; }
    })
    .catch(()=>{ alert('Falha de conexão ao enviar pra impressora.'); btn.textContent=txt; btn.disabled=false; });
}
</script>
@if(request('print'))
<script>document.addEventListener('DOMContentLoaded',()=>FBRE.abrirImpressao('{{ route('passagens.comprovante',$p) }}'));</script>
@endif
<script>
function transfCal(linhas, origem, destino, assentosUrl){
  return {
    linhas: linhas||[], origem: origem||'', destino: destino||'', assentosUrl: assentosUrl||'', ocupadas:[], usaPoltrona:false, embSel:'', viagemData:'', viagemDia:null, viagemHora:'', calAberto:false, calMes:null, linhaNome:'',
    diasCurto:['Dom','Seg','Ter','Qua','Qui','Sex','Sab'],
    isoLocal(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); },
    get hojeISO(){ return this.isoLocal(new Date()); },
    fmtBr(d){ return String(d.getDate()).padStart(2,'0')+'/'+String(d.getMonth()+1).padStart(2,'0')+'/'+d.getFullYear(); },
    onEmb(){ this.resolver(); this.carregarAssentos(); },
    resolver(){
      this.viagemDia=null; this.viagemData=''; this.viagemHora=''; this.linhaNome='';
      const emb=this.embSel?String(this.embSel):''; if(!emb) return;
      const n=x=>String(x||'').trim().toLowerCase();
      const Ls=this.linhas.filter(L=>String(L.embarcacao_id||'')===emb);
      let p=null, ln='';
      for(const L of Ls){ const f=L.paradas.find(x=>n(x.n)===n(this.origem)); if(f && f.d!==null && f.d!==undefined && f.d!==''){ p=f; ln=L.nome; break; } }
      if(!p && Ls[0] && Ls[0].paradas[0]){ p=Ls[0].paradas[0]; ln=Ls[0].nome; }
      if(!p || p.d===null||p.d===undefined||p.d===''){ return; }
      this.viagemDia=Number(p.d); this.viagemHora=p.h||''; this.linhaNome=ln;
      this.viagemData=this.proxima(this.viagemDia);
    },
    proxima(dia){ const d=new Date(); d.setHours(0,0,0,0); while(d.getDay()!==dia) d.setDate(d.getDate()+1); return this.isoLocal(d); },
    rotulo(iso){ if(!iso) return 'escolher'; const [y,m,day]=iso.split('-').map(Number); const d=new Date(y,m-1,day); return this.diasCurto[d.getDay()]+' '+this.fmtBr(d)+(this.viagemHora?' '+this.viagemHora:''); },
    abrir(){ if(this.viagemDia===null) return; const base=this.viagemData||this.hojeISO; const [y,m]=base.split('-').map(Number); this.calMes=new Date(y,m-1,1); this.calAberto=true; },
    mudarMes(nn){ const d=new Date(this.calMes); d.setMonth(d.getMonth()+nn); this.calMes=d; },
    get nomeMes(){ return this.calMes ? this.calMes.toLocaleDateString('pt-BR',{month:'long',year:'numeric'}) : ''; },
    get calDias(){
      if(!this.calMes) return [];
      const ano=this.calMes.getFullYear(), mes=this.calMes.getMonth();
      const ini=new Date(ano,mes,1); ini.setDate(1-ini.getDay()); const out=[];
      for(let i=0;i<42;i++){ const d=new Date(ini); d.setDate(ini.getDate()+i); const iso=this.isoLocal(d); const outroMes=d.getMonth()!==mes;
        const valido=!outroMes && d.getDay()===this.viagemDia && iso>=this.hojeISO;
        out.push({iso,dia:d.getDate(),valido,outroMes,sel:iso===this.viagemData}); }
      return out;
    },
    escolher(iso){ this.viagemData=iso; this.calAberto=false; this.carregarAssentos(); },
    carregarAssentos(){
      this.ocupadas=[]; this.usaPoltrona=false;
      const emb=this.embSel?String(this.embSel):''; if(!emb||!this.assentosUrl) return;
      const qs=new URLSearchParams({embarcacao_id:emb, data_viagem:this.viagemData||'', origem:this.origem||'', destino:this.destino||''});
      fetch(this.assentosUrl+'?'+qs.toString(),{headers:{'Accept':'application/json'}}).then(r=>r.json()).then(j=>{ this.usaPoltrona=!!j.usa_poltrona; this.ocupadas=(j.ocupadas||[]).map(x=>String(x).toUpperCase()); }).catch(()=>{});
    },
    get ocupadasTxt(){ return this.ocupadas.join(', '); },
    poltronaOcupada(v){ return this.ocupadas.includes(String(v||'').trim().toUpperCase()); }
  };
}
function trocaCal(linhas, origem, embId, destino, assentosUrl){
  const c = transfCal(linhas, origem, destino, assentosUrl);
  c.embSel = embId || '';
  c.init = function(){ this.resolver(); this.carregarAssentos(); };
  return c;
}
</script>
@endpush
