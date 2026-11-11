  {{-- ACERTO COM A EMPRESA (barcos sem dono) --}}
@if(!empty($acerto))
<div class="card p-4" style="background:linear-gradient(135deg,#0b1a33,#13294d);color:#fff" x-data="acertoPix(@js($acerto))">
  <div class="text-[11px] font-bold uppercase opacity-70 mb-2">Acerto com a empresa · {{ $acerto['qtd'] }} passagem(ns)</div>
  @if(($acerto['total_devido'] ?? 0) > 0)
    <div class="text-xs opacity-70">Você deve à empresa (tudo em aberto)</div>
    <div class="text-2xl font-extrabold text-amber-300 leading-tight mb-2">R$ {{ number_format($acerto['total_devido'],2,',','.') }}</div>
  @else
    <div class="text-xs opacity-70">Arrecadei</div>
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
        <div class="text-[12px] text-amber-800 bg-amber-50 border border-amber-200 rounded-xl p-2 mb-2">⚠️ Depois de pagar, <b>mande o comprovante</b> pra empresa.</div>
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
      if(!chave){ alert('A empresa não cadastrou a chave PIX. Fale com o administrador.'); return; }
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

  {{-- DÉBITO COM OS DONOS --}}
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

  {{-- DÉBITOS EM ABERTO (empresa) --}}
  @if($pendentes->isNotEmpty())
  <div class="card overflow-hidden border border-amber-200">
    <div class="p-3 border-b bg-amber-50 flex items-center justify-between">
      <div class="font-bold text-sm text-amber-700">⚠️ Passagens a repassar pra empresa</div>
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
  </div>
  @endif
