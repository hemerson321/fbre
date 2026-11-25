@extends('layouts.app')
@section('titulo','Contas a receber')
@section('conteudo')
<div class="max-w-4xl mx-auto space-y-4 pb-24" x-data="contasReceber()">

  <div class="flex items-center justify-between gap-2 flex-wrap">
    <h1 class="text-xl font-bold">💰 Contas a receber <span class="text-sm font-normal text-slate-400">(faturados pendentes)</span></h1>
    <a href="{{ route('clientes-faturado') }}" class="btn btn-ghost btn-sm">🧾 Clientes faturado</a>
  </div>

  @if(session('ok'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-3 py-2 text-sm">{{ session('ok') }}</div>@endif
  @if(session('erro'))<div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-3 py-2 text-sm">{{ session('erro') }}</div>@endif

  {{-- Filtro por cliente --}}
  <form method="get" class="card p-3 flex flex-wrap items-end gap-2">
    <div class="flex-1 min-w-[180px]">
      <label class="label !text-xs">Buscar cliente</label>
      <input type="text" name="busca" value="{{ $busca }}" placeholder="nome ou empresa" class="input">
    </div>
    <div class="min-w-[180px]">
      <label class="label !text-xs">Cliente</label>
      <select name="cliente_id" class="input">
        <option value="">— todos —</option>
        @foreach($clientes as $c)<option value="{{ $c->id }}" @selected((string)$clienteId===(string)$c->id)>{{ $c->nome }}</option>@endforeach
      </select>
    </div>
    <button class="btn btn-sm">Filtrar</button>
    @if($clienteId || $busca)<a href="{{ route('financeiro.contas-receber') }}" class="btn btn-ghost btn-sm">limpar</a>@endif
  </form>

  <div class="card p-3 flex items-center justify-between">
    <span class="text-sm text-slate-500">Total a receber{{ $clienteId ? ' (filtrado)' : '' }}</span>
    <span class="text-xl font-extrabold text-rose-600">R$ {{ number_format($totalGeral,2,',','.') }}</span>
  </div>

  <form method="post" action="{{ route('financeiro.baixar') }}" id="fBaixa">@csrf
    @forelse($grupos as $g)
    <div class="card p-3 mb-3">
      <div class="flex items-center justify-between gap-2 mb-2 pb-2 border-b border-slate-100">
        <label class="flex items-center gap-2 cursor-pointer min-w-0">
          <input type="checkbox" class="w-5 h-5 rounded shrink-0" @change="toggleCliente(@js($g->passagens->pluck('id')->map(fn($i)=>(string)$i)->all()), $event.target.checked)" :checked="clienteOn(@js($g->passagens->pluck('id')->map(fn($i)=>(string)$i)->all()))">
          <span class="font-bold truncate">🧾 {{ $g->nome }}@if($g->empresa)<span class="text-slate-400 font-normal"> · {{ $g->empresa }}</span>@endif</span>
        </label>
        <span class="text-sm font-bold text-rose-600 shrink-0">R$ {{ number_format($g->total,2,',','.') }} <span class="text-slate-400 font-normal">· {{ $g->qtd }}</span></span>
      </div>
      <div class="divide-y divide-slate-100">
        @foreach($g->passagens as $p)
        <label class="flex items-center gap-2.5 py-2 cursor-pointer">
          <input type="checkbox" name="passagem_ids[]" value="{{ $p->id }}" x-model="sel" class="w-5 h-5 rounded shrink-0">
          <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold truncate">{{ $p->numeroFormatado() }} · {{ optional($p->passageiros->first())->nome ?: $p->comprador_nome ?: 'Passageiro' }}</div>
            <div class="text-[11px] text-slate-500 truncate">
              {{ $p->origem }} → {{ $p->destino }}
              @if($p->data_viagem)· 🗓️ {{ $p->data_viagem->format('d/m/Y') }}@endif
              @if($p->embarcacao)· ⛴️ {{ $p->embarcacao->nome }}@endif
            </div>
          </div>
          <span class="text-sm font-bold text-slate-700 shrink-0">R$ {{ number_format($p->devido,2,',','.') }}</span>
        </label>
        @endforeach
      </div>
    </div>
    @empty
    <div class="card p-6 text-center text-slate-400">Nenhuma conta a receber{{ $clienteId || $busca ? ' com esse filtro' : '' }}. 🎉</div>
    @endforelse

    {{-- Barra fixa de baixa --}}
    <div x-show="sel.length" x-cloak class="fixed bottom-0 left-0 right-0 z-30 bg-white border-t border-slate-200 shadow-[0_-4px_12px_-6px_rgba(0,0,0,.2)]">
      <div class="max-w-4xl mx-auto p-3 space-y-2">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
          <div class="text-sm"><b x-text="sel.length"></b> selecionada(s) · <b class="text-emerald-700" x-text="moeda(total)"></b></div>
          <div class="flex items-center gap-1.5 ml-auto flex-wrap">
            <span class="text-xs text-slate-500">% empresa</span>
            <input type="number" min="0" max="100" step="0.5" x-model.number="pct" name="pct" class="input !min-h-0 !py-1 !w-16 text-sm text-right" title="Porcentagem que fica para a empresa (padrão 10%)">
            <span class="text-xs text-slate-500">Recebido em</span>
            <select name="meio" x-model="meio" class="input !min-h-0 !py-1 !w-auto text-sm">
              <option value="pix">PIX</option>
              <option value="dinheiro">Dinheiro</option>
              <option value="debito">Débito</option>
              <option value="credito">Crédito</option>
            </select>
            <button type="submit" class="btn text-white font-bold" style="background:#059669" @click="return confirmarBaixa($event)">✅ Dar baixa</button>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[13px] border-t border-slate-100 pt-1.5">
          <span class="text-slate-500">🏢 Empresa (<b x-text="pct"></b>%): <b class="text-indigo-700" x-text="moeda(empresaVal)"></b></span>
          <span class="text-slate-500">🚤 Dono do transporte (<b x-text="100-(Number(pct)||0)"></b>%): <b class="text-emerald-700" x-text="moeda(donoVal)"></b></span>
          <span class="text-[11px] text-slate-400">via <b x-text="meioLabel"></b> · o valor do dono entra como positivo na próxima prestação dele</span>
        </div>
      </div>
    </div>
  </form>

  {{-- Pagas recentemente (desfazer baixa) --}}
  @if($pagas->count())
  <div class="card p-3">
    <div class="font-bold text-sm mb-2 flex items-center gap-2">💳 Faturados pagos <span class="text-[11px] font-normal text-slate-400">(aqui você desfaz a baixa do que já foi pago)</span></div>
    <div class="divide-y divide-slate-100">
      @foreach($pagas as $p)
      <div class="flex items-center gap-2.5 py-2">
        <div class="flex-1 min-w-0">
          <div class="text-sm font-semibold truncate">{{ $p->numeroFormatado() }} · {{ optional($p->clienteFaturado)->nome ?: 'Cliente' }}</div>
          <div class="text-[11px] text-slate-500 truncate">{{ $p->origem }} → {{ $p->destino }} @if($p->data_viagem)· 🗓️ {{ $p->data_viagem->format('d/m/Y') }}@endif · R$ {{ number_format($p->total,2,',','.') }}</div>
        </div>
        @if($p->acerto_dono || $p->prestacao_id || in_array($p->id, $lockedIds ?? []))
        <span class="text-[10.5px] text-amber-600 shrink-0 text-right leading-tight">já entrou numa prestação fechada<br>desfaça a prestação antes</span>
        @else
        <form method="post" action="{{ route('financeiro.desfazer-baixa', $p) }}" onsubmit="return confirm('Desfazer a baixa dessa passagem? Ela volta pra contas a receber.')">@csrf
          <button class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-[12px] font-semibold text-rose-700 hover:bg-rose-100">↩ Desfazer baixa</button>
        </form>
        @endif
      </div>
      @endforeach
    </div>
  </div>
  @endif

</div>

<script>
function contasReceber(){
  return {
    sel: [],
    meio: 'pix',
    pct: 10,
    valores: @js($grupos->flatMap(fn($g)=>$g->passagens)->mapWithKeys(fn($p)=>[(string)$p->id => round((float)$p->devido,2)])->all()),
    get total(){ return this.sel.reduce((s,id)=>s+(Number(this.valores[id])||0),0); },
    get empresaVal(){ return this.total*(Number(this.pct)||0)/100; },
    get donoVal(){ return this.total*(100-(Number(this.pct)||0))/100; },
    get meioLabel(){ return ({pix:'PIX',dinheiro:'Dinheiro',debito:'Débito',credito:'Crédito'})[this.meio]||this.meio; },
    toggleCliente(ids, on){ ids=(ids||[]).map(String); if(on){ ids.forEach(id=>{ if(!this.sel.includes(id)) this.sel.push(id); }); } else { this.sel = this.sel.filter(id=>!ids.includes(id)); } },
    clienteOn(ids){ ids=(ids||[]).map(String); return ids.length>0 && ids.every(id=>this.sel.includes(id)); },
    moeda(v){ const n=(Number(v)||0).toFixed(2).replace('.',','); return 'R$ '+n.replace(/\B(?=(\d{3})+(?!\d))/g,'.'); },
    confirmarBaixa(ev){ if(!this.sel.length){ ev.preventDefault(); return false; } if(!confirm('Dar baixa em '+this.sel.length+' faturado(s) · '+this.moeda(this.total)+'?\nEmpresa ('+this.pct+'%): '+this.moeda(this.empresaVal)+'\nDono do transporte: '+this.moeda(this.donoVal)+' (entra como positivo na próxima prestação dele).')){ ev.preventDefault(); return false; } return true; },
  };
}
</script>
@endsection
