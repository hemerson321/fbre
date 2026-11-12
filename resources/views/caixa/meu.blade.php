@extends('layouts.app')
@section('titulo','Meu relatório')
@section('conteudo')
<div class="space-y-4">
<div class="flex items-center justify-between flex-wrap gap-2"><h1 class="text-xl font-bold">Meu relatório <span class="text-slate-400 font-normal text-base">· {{ auth()->user()->name }}</span></h1>
  <div class="flex items-center gap-2 no-print flex-wrap">
    <div class="inline-flex rounded-xl border overflow-hidden text-sm shadow-sm">
      <a href="{{ request()->fullUrlWithQuery(['modo'=>'resumido']) }}" class="px-4 py-2 font-semibold {{ $modo==='resumido' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">Resumido</a>
      <a href="{{ request()->fullUrlWithQuery(['modo'=>'analitico']) }}" class="px-4 py-2 font-semibold {{ $modo==='analitico' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">Analítico</a>
    </div>
    <a href="{{ request()->fullUrlWithQuery(['pdf'=>1]) }}" target="_blank" class="rounded-xl border bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 shadow-sm">📄 Prestação (PDF)</a>
    <button onclick="window.print()" class="rounded-xl border bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 shadow-sm">🖨 Imprimir</button>
  </div></div>

<x-periodo :periodo="$periodo" :inicio="$inicio" :fim="$fim">
  <input type="hidden" name="modo" value="{{ $modo }}">
  <div><label class="label">Forma</label>
    <select name="canal" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todas as formas</option>
      @foreach(\App\Models\Pagamento::CANAIS as $ck=>$cv)<option value="{{ $ck }}" @selected(($canal ?? '')===$ck)>{{ $cv }}</option>@endforeach</select></div>
  @if($podeTransporte)
  <div><label class="label">Transporte</label>
    <select name="transporte_id" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todos</option>
      @foreach($transportes as $t)<option value="{{ $t->id }}" @selected((string)$transporteId===(string)$t->id)>{{ $t->nome }}</option>@endforeach</select></div>
  @endif
  @if($datasViagem->isNotEmpty())
  <div><label class="label">Data da viagem</label>
    <select name="data_viagem" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todas as datas</option>
      @foreach($datasViagem as $dv)@php $dvs = \Illuminate\Support\Carbon::parse($dv)->toDateString(); @endphp<option value="{{ $dvs }}" @selected((string)($dataViagem ?? '')===$dvs)>{{ \Illuminate\Support\Carbon::parse($dv)->format('d/m/Y') }}</option>@endforeach</select></div>
  @endif
  @if($viagens->isNotEmpty())
  <div><label class="label">Viagem</label>
    <select name="viagem_id" class="input !min-h-[38px]" onchange="this.form.submit()"><option value="">Todas as viagens</option>
      @foreach($viagens as $v)<option value="{{ $v->id }}" @selected((string)($viagemId ?? '')===(string)$v->id)>{{ $v->data_partida?->format('d/m/Y') }}{{ $v->rota ? ' · '.$v->rota->nome : '' }}</option>@endforeach</select></div>
  @endif
</x-periodo>

<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-2">
  @if($temEncomenda)<div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">Cadastrei</div><div class="text-xl font-extrabold">{{ $totais['cadastros'] }}</div><div class="text-xs text-slate-400">R$ {{ number_format($totais['cadastros_valor'],2,',','.') }}</div></div>@endif
  <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">Recebi</div><div class="text-xl font-extrabold text-emerald-600">R$ {{ number_format($porMeio->sum(),2,',','.') }}</div></div>
  @if(($cancelados->qtd ?? 0) > 0)<div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">❌ Cancelados</div><div class="text-xl font-extrabold text-slate-500">{{ $cancelados->qtd }}</div><div class="text-xs text-slate-400">R$ {{ number_format($cancelados->valor,2,',','.') }}</div></div>@endif
  @foreach(['dinheiro','pix_manual','pix_auto','debito','credito'] as $m)
    <div class="card p-3"><div class="text-[11px] font-semibold text-slate-500 uppercase">{{ $meios[$m] }}</div><div class="text-lg font-extrabold">R$ {{ number_format($porMeio[$m] ?? 0,2,',','.') }}</div></div>
  @endforeach
</div>

@if(!empty($acerto))
<div class="card p-4" style="background:linear-gradient(135deg,#0b1a33,#13294d);color:#fff" x-data="acertoPix(@js($acerto))">
  <div class="text-[11px] font-bold uppercase opacity-70 mb-2">Acerto com a empresa · {{ $acerto['qtd'] }} passagem(ns) no período</div>
  @if(($acerto['total_devido'] ?? 0) > 0)
    <div class="text-xs opacity-70">Você deve à empresa (tudo em aberto)</div>
    <div class="text-2xl font-extrabold text-amber-300 leading-tight mb-2">R$ {{ number_format($acerto['total_devido'],2,',','.') }}</div>
  @else
    <div class="text-xs opacity-70">Arrecadei no período</div>
    <div class="text-2xl font-extrabold text-emerald-300 leading-tight mb-2">R$ {{ number_format($acerto['bruto'],2,',','.') }}</div>
  @endif
  <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm mb-3">
    <div><span class="opacity-70">Vendi:</span> <b>R$ {{ number_format($acerto['bruto'],2,',','.') }}</b></div>
    <div><span class="opacity-70">Minha comissão ({{ $acerto['pct'] }}%):</span> <b class="text-emerald-300">R$ {{ number_format($acerto['comissao'],2,',','.') }}</b></div>
  </div>
  @php
    $_pm = $acerto['pix_modo'] ?? 'nenhum';
    $_podeBotao = ($acerto['total_devido'] ?? 0) > 0 && $_pm !== 'nenhum'
       && (($_pm === 'dinamico' && ($acerto['pode_dinamico'] ?? false)) || ($_pm === 'estatico' && ! empty($acerto['emp_pix'])));
  @endphp
  <div class="border-t border-white/15 pt-2 mb-2 space-y-1">
    @if(($acerto['acertado_hoje'] ?? 0) > 0)
      <div class="flex justify-between text-sm"><span class="opacity-70">Acertado hoje</span><b class="text-emerald-300">R$ {{ number_format($acerto['acertado_hoje'],2,',','.') }}</b></div>
    @endif
    @if(($acerto['av_entrada'] ?? 0) > 0 || ($acerto['av_saida'] ?? 0) > 0)
      <div class="flex justify-between text-xs opacity-80"><span>Passagens a repassar</span><span>R$ {{ number_format($acerto['repasse_passagem'] ?? 0,2,',','.') }}</span></div>
      @if(($acerto['av_entrada'] ?? 0) > 0)<div class="flex justify-between text-xs text-emerald-300"><span>+ Entradas avulsas</span><span>R$ {{ number_format($acerto['av_entrada'],2,',','.') }}</span></div>@endif
      @if(($acerto['av_saida'] ?? 0) > 0)<div class="flex justify-between text-xs text-rose-300"><span>− Despesas / saídas</span><span>R$ {{ number_format($acerto['av_saida'],2,',','.') }}</span></div>@endif
    @endif
    <div class="flex justify-between text-sm"><span class="opacity-70">Falta acertar (tudo em aberto)</span><b class="{{ ($acerto['total_devido'] ?? 0) > 0 ? 'text-amber-300':'text-emerald-300' }}">R$ {{ number_format($acerto['total_devido'] ?? 0,2,',','.') }}</b></div>
  </div>
  @if(($acerto['total_devido'] ?? 0) > 0)
    @if($_podeBotao)
    <button type="button" @click="acertar()" :disabled="carregando" class="w-full rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-2.5 disabled:opacity-50">
      <span x-text="carregando ? 'Gerando…' : '💠 Acertar com a empresa (PIX)'"></span>
    </button>
    @endif
  @else
    <div class="text-emerald-300 text-sm font-semibold">✓ Você está em dia com a empresa.</div>
  @endif

  <div x-show="modal" x-cloak class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4" @click="fechar()">
    <div class="bg-white text-slate-800 rounded-2xl p-5 max-w-sm w-full text-center" @click.stop>
      <div class="font-bold text-lg" x-text="dinamico ? '💠 PIX pra empresa (automático)' : '💠 PIX da empresa'"></div>
      <div class="text-xs text-slate-500 mb-3" x-text="dinamico ? 'Pague que o acerto baixa sozinho.' : 'Pague na chave da empresa e mande o comprovante.'"></div>
      <template x-if="qrImg"><img :src="qrImg" class="mx-auto w-52 h-52"></template>
      <div id="qr-acerto" x-show="!qrImg" class="inline-block p-2 bg-white rounded-xl border mx-auto"></div>
      <div class="font-extrabold text-xl my-2">R$ <span x-text="fmt(valor)"></span></div>
      <button type="button" @click="copiar()" class="btn btn-ghost btn-sm w-full mb-2">📋 Copiar código PIX</button>
      <template x-if="!dinamico">
        <div class="text-[12px] text-amber-800 bg-amber-50 border border-amber-200 rounded-xl p-2 mb-2">⚠️ Depois de pagar, <b>mande o comprovante</b> pra empresa. Ela confere e dá baixa no seu acerto.</div>
      </template>
      <template x-if="dinamico">
        <div class="text-[12px] text-slate-500 mb-2">Aguardando o pagamento… assim que cair, o acerto baixa sozinho.</div>
      </template>
      <button type="button" @click="fechar()" class="btn btn-ghost btn-sm w-full text-slate-500">Fechar</button>
    </div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
function acertoPix(ac){
  return {
    ac: ac, carregando:false, modal:false, dinamico:false, valor: ac.total_devido||0,
    qrImg:'', copia:'', cobId:null, poll:null,
    fmt(v){ return (Number(v)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); },
    acertar(){ if(this.ac.pix_modo==='dinamico' && this.ac.pode_dinamico){ this.dinamicoFlow(); } else { this.estaticoFlow(); } },
    dinamicoFlow(){
      this.carregando=true;
      fetch('{{ route('acertos.pagar-empresa') }}',{method:'POST',headers:{'X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json'}})
        .then(r=>r.json()).then(d=>{
          this.carregando=false;
          if(!d.ok){ alert(d.erro||'Falha ao gerar PIX'); return; }
          this.dinamico=true; this.valor=d.valor; this.copia=d.copia_cola||''; this.cobId=d.id;
          this.qrImg = d.qr_base64 ? ('data:image/png;base64,'+d.qr_base64) : '';
          this.modal=true; this.poll=setInterval(()=>this.checar(),4000);
        }).catch(()=>{ this.carregando=false; alert('Falha de conexão'); });
    },
    checar(){
      if(!this.cobId) return;
      fetch('/cobranca/'+this.cobId+'/status',{headers:{'Accept':'application/json'}}).then(r=>r.json()).then(d=>{
        if(d && d.pago){ clearInterval(this.poll); alert('✅ Acerto pago! Suas passagens foram baixadas.'); location.reload(); }
      }).catch(()=>{});
    },
    estaticoFlow(){
      var chave=(this.ac.emp_pix||'').trim();
      if(!chave){ alert('A empresa não cadastrou a chave PIX (Minha empresa → Chave PIX). Fale com o administrador.'); return; }
      this.dinamico=false; this.valor=this.ac.total_devido||0; this.qrImg='';
      this.copia=this.payloadEstatico(this.valor);
      this.modal=true;
      this.$nextTick(()=>{ var el=document.getElementById('qr-acerto'); if(el){ el.innerHTML=''; try{ new QRCode(el,{text:this.copia,width:208,height:208}); }catch(e){} } });
    },
    emvF(id,v){ v=String(v); return id+String(v.length).padStart(2,'0')+v; },
    crc16(p){ let c=0xFFFF; for(let i=0;i<p.length;i++){ c^=p.charCodeAt(i)<<8; for(let j=0;j<8;j++){ c=(c&0x8000)?((c<<1)^0x1021):(c<<1); c&=0xFFFF; } } return c.toString(16).toUpperCase().padStart(4,'0'); },
    payloadEstatico(valor){
      var limpa=s=>(s||'').normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/[^A-Za-z0-9 ]/g,'').toUpperCase().trim();
      var chave=(this.ac.emp_pix||'').trim();
      var nome=(limpa(this.ac.emp_nome)||'EMPRESA').slice(0,25);
      var cidade=(limpa(this.ac.emp_cidade)||'CIDADE').slice(0,15);
      var mai=this.emvF('00','BR.GOV.BCB.PIX')+this.emvF('01',chave);
      var p=this.emvF('00','01')+this.emvF('26',mai)+this.emvF('52','0000')+this.emvF('53','986');
      if(valor>0) p+=this.emvF('54',Number(valor).toFixed(2));
      p+=this.emvF('58','BR')+this.emvF('59',nome)+this.emvF('60',cidade)+this.emvF('62',this.emvF('05','***'))+'6304';
      return p+this.crc16(p);
    },
    copiar(){ try{ navigator.clipboard.writeText(this.copia); }catch(e){} },
    fechar(){ if(this.poll) clearInterval(this.poll); this.modal=false; }
  };
}
</script>
@endif


@if($modo==='resumido')
  <div class="card p-4 text-sm text-slate-600">
    Resumo do período — <b>{{ $totais['cadastros'] }}</b> cadastro(s) · recebido <b>R$ {{ number_format($porMeio->sum(),2,',','.') }}</b> · <b>{{ $totais['entregas'] }}</b> entrega(s)@if(($cancelados->qtd ?? 0) > 0) · <b>{{ $cancelados->qtd }}</b> cancelado(s) (R$ {{ number_format($cancelados->valor,2,',','.') }})@endif.
    <div class="text-slate-400 mt-1">Para ver item a item, use o modo <b>Analítico</b>.</div>
  </div>
@else
@php $muito = max($totais['cadastros'], $totais['recebido_qtd'], $totais['entregas']) > $limite; @endphp
@if($muito)
  <div class="card p-3 text-xs text-amber-700 bg-amber-50 border border-amber-200">Período grande: mostrando os <b>{{ $limite }}</b> mais recentes de cada lista.</div>
@endif
<div class="grid lg:grid-cols-2 gap-4">
@if($temEncomenda)
  <div class="card overflow-hidden">
    <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Encomendas que cadastrei ({{ $totais['cadastros'] }})</div>
    <div class="divide-y text-sm">
      @forelse($cadastros as $e)
        <a href="{{ route('encomendas.show',$e) }}" class="flex justify-between p-3 hover:bg-slate-50 row-{{ $e->cor() }}"><div><b>#{{ $e->numeroFormatado() }}</b> {{ $e->destino }} · {{ $e->destinatario_nome }}<div class="text-xs text-slate-400">{{ $e->created_at->format('d/m H:i') }}</div></div><b>R$ {{ number_format((float)$e->total,2,',','.') }}</b></a>
      @empty <div class="p-6 text-center text-slate-400">Nenhum cadastro.</div> @endforelse
    </div>
  </div>
@endif
  @if($pendentes->isNotEmpty())
  <div class="card overflow-hidden border border-amber-200">
    <div class="p-3 border-b bg-amber-50 flex items-center justify-between">
      <div class="font-bold text-sm text-amber-700">⚠️ Débitos em aberto (não acertados)</div>
      <div class="text-[11px] text-amber-600">{{ $pendentes->count() }} passagem(ns) · de qualquer data</div>
    </div>
    <div class="divide-y text-sm">
      @foreach($pendentes as $pd)
      <div class="flex items-center justify-between p-3">
        <div class="min-w-0">
          <div class="font-semibold truncate">#{{ $pd->numero_completo ?: $pd->numero }} · {{ $pd->origem }} → {{ $pd->destino }}</div>
          <div class="text-xs text-slate-400">{{ $pd->created_at?->format('d/m/Y H:i') }}</div>
        </div>
        <b class="text-amber-600 whitespace-nowrap">R$ {{ number_format((float)($pd->valor_aberto ?? 0),2,',','.') }}</b>
      </div>
      @endforeach
    </div>
    <div class="flex items-center justify-between p-3 border-t bg-amber-50 font-bold text-sm">
      <span>Total a repassar pra empresa</span>
      <span class="text-amber-700">R$ {{ number_format((float)$pendentes->sum('valor_aberto'),2,',','.') }}</span>
    </div>
  </div>
  @endif

  @if($debitosDono->isNotEmpty())
  <div class="card overflow-hidden border border-sky-200">
    <div class="p-3 border-b bg-sky-50 flex items-center justify-between">
      <div class="font-bold text-sm text-sky-700">🚤 Débito com os donos das lanchas</div>
      <div class="text-[11px] text-sky-600">você paga direto o dono</div>
    </div>
    <div class="divide-y text-sm">
      @foreach($debitosDono as $d)
      <div class="flex items-center justify-between p-3">
        <div class="min-w-0">
          <div class="font-semibold truncate">{{ $d->nome }}@if($d->dono) <span class="text-[11px] text-slate-400 font-normal">· dono {{ $d->dono }}</span>@endif</div>
          <div class="text-xs text-slate-400">{{ $d->qtd }} passagem(ns) · de qualquer data</div>
        </div>
        <b class="text-sky-700 whitespace-nowrap">R$ {{ number_format($d->valor,2,',','.') }}</b>
      </div>
      @endforeach
    </div>
    <div class="flex items-center justify-between p-3 border-t bg-sky-50 font-bold text-sm">
      <span>Total a pagar aos donos</span>
      <span class="text-sky-700">R$ {{ number_format((float)$debitosDono->sum('valor'),2,',','.') }}</span>
    </div>
  </div>
  @endif

</div>{{-- fecha a grade de cards secundários --}}

@php $mostraComprador = (bool) \App\Support\Tenant::empresa()?->config('comprador_passagem'); @endphp
<div class="card overflow-hidden">
  <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Recebimentos ({{ $totais['recebido_qtd'] }})</div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="bg-slate-50 text-slate-500 text-[11px] uppercase text-left">
          <th class="p-2 pl-3">Nº</th>
          <th class="p-2">Transporte</th>
          <th class="p-2">Trecho</th>
          <th class="p-2">Viagem</th>
          @if($mostraComprador)<th class="p-2">Comprador</th>@endif
          <th class="p-2">Poltrona</th>
          <th class="p-2">Vendeu</th>
          <th class="p-2">Compra</th>
          <th class="p-2">Meio</th>
          <th class="p-2 pr-3 text-right">Valor</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        @forelse($recebimentos as $p)
          @php
            $pg = $p->passagemOrigem; $en = $p->encomenda;
            $numero = $pg?->numeroFormatado() ?? $en?->numeroFormatado();
            $transp = $pg?->embarcacao?->nome ?? $en?->embarcacao?->nome;
            $quem   = $pg ? ($pg->comprador_nome ?: '—') : ($en?->destinatario_nome ?: '—');
            $rota   = $pg ? trim(($pg->origem ?? '').' → '.($pg->destino ?? '')) : '';
            $poltronas = $pg ? $pg->passageiros->pluck('poltrona')->filter()->implode(', ') : '';
            $viagem = $pg?->data_viagem ? \Illuminate\Support\Carbon::parse($pg->data_viagem)->format('d/m/Y') : '—';
          @endphp
          <tr class="hover:bg-slate-50 align-top">
            <td class="p-2 pl-3 font-semibold whitespace-nowrap">#{{ $numero }}</td>
            <td class="p-2">@if($transp)<span class="text-[11px] font-bold text-white px-1.5 py-0.5 rounded whitespace-nowrap" style="background:var(--cor1)">🚤 {{ $transp }}</span>@else<span class="text-slate-300">—</span>@endif</td>
            <td class="p-2 whitespace-nowrap">{{ $rota && $rota !== '→' ? $rota : '—' }}</td>
            <td class="p-2 whitespace-nowrap">{{ $viagem }}</td>
            @if($mostraComprador)<td class="p-2">{{ $quem }}</td>@endif
            <td class="p-2 whitespace-nowrap font-medium">{{ $poltronas ?: '—' }}</td>
            <td class="p-2 whitespace-nowrap">{{ $p->user?->name ?? '—' }}</td>
            <td class="p-2 whitespace-nowrap text-slate-500">{{ $p->created_at->format('d/m/Y H:i') }}</td>
            <td class="p-2 whitespace-nowrap"><span class="chip bg-slate-100 text-slate-600">{{ $meios[$p->canal] ?? \App\Models\Pagamento::MEIOS[$p->meio] ?? $p->meio }}</span></td>
            <td class="p-2 pr-3 text-right font-bold whitespace-nowrap">R$ {{ number_format((float)$p->valor,2,',','.') }}</td>
          </tr>
        @empty
          <tr><td colspan="{{ $mostraComprador ? 10 : 9 }}" class="p-6 text-center text-slate-400">Nenhum recebimento.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@if($temEncomenda)
<div class="card overflow-hidden">
  <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Entregas que dei baixa ({{ $totais['entregas'] }})</div>
  <div class="divide-y text-sm">
    @forelse($entregas as $ev)
      <div class="p-3"><b>#{{ $ev->encomenda?->numeroFormatado() }}</b> {{ $ev->encomenda?->destinatario_nome }} <span class="text-xs text-slate-400">{{ $ev->created_at->format('d/m H:i') }}{{ ($ev->dados['para'] ?? null) ? ' · retirou: '.$ev->dados['para'] : '' }}</span></div>
    @empty <div class="p-6 text-center text-slate-400">Nenhuma entrega.</div> @endforelse
  </div>
</div>
@endif
@endif
</div>
@endsection
