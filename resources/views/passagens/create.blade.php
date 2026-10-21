@extends('layouts.app')
@section('titulo','Nova passagem')

@php $cor = \App\Support\Modulos::hex('passagem'); @endphp

@section('conteudo')
<div x-data="novaPassagem()" x-init="init()" class="max-w-3xl mx-auto space-y-4">

  {{-- faixa do módulo (cor própria) --}}
  <div class="rounded-2xl px-4 py-3 text-white flex items-center gap-3" style="background:{{ $cor }}">
    <span class="text-2xl">🎫</span>
    <div><div class="font-bold leading-tight">Nova passagem</div>
      <div class="text-[11px] opacity-80">Nº <span x-text="numeroPreview"></span> · <span x-text="online ? 'online' : 'offline (salva no aparelho)'"></span></div></div>
  </div>

  <form @submit.prevent="salvar" class="space-y-4">
    {{-- viagem --}}
    <div class="card p-4 space-y-3">
      <div class="grid grid-cols-2 gap-3">
        <div><label class="label">Origem</label>
          <input list="locs" class="input" x-model="origem" @change="aplicarPreco()" required></div>
        <div><label class="label">Destino</label>
          <input list="locs" class="input" x-model="destino" @change="aplicarPreco()" required></div>
      </div>
      <datalist id="locs">@foreach($localidades as $l)<option value="{{ $l }}">@endforeach</datalist>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="label">Embarcação</label>
          <select class="input" x-model.number="embarcacao_id" @change="mudarEmbarcacao($event)">
            <option value="">—</option>
            @foreach($embarcacoes as $e)<option value="{{ $e->id }}" data-poltrona="{{ $e->usa_poltrona?1:0 }}" data-qtd="{{ $e->poltronas_qtd }}">{{ $e->nome }}{{ $e->usa_poltrona?' (poltrona)':'' }}</option>@endforeach
          </select></div>
        <div><label class="label">Data da viagem</label>
          <input type="date" class="input" x-model="data_viagem" @change="carregarOcupadas()"></div>
      </div>
      <div x-show="precoInfo" class="text-xs text-emerald-700" x-cloak>
        Preço da rota: passagem <span x-text="FBRE.moeda(basePassagem)"></span> · taxa <span x-text="FBRE.moeda(baseTaxa)"></span>
      </div>
    </div>

    {{-- passageiros --}}
    <div class="card p-4 space-y-3">
      <div class="flex items-center justify-between">
        <div class="font-semibold">Passageiros (<span x-text="passageiros.length"></span>)</div>
        <button type="button" class="btn btn-ghost btn-sm" @click="addPax()">+ passageiro</button>
      </div>

      <template x-for="(p,idx) in passageiros" :key="idx">
        <div class="rounded-xl border border-slate-200 p-3 space-y-2">
          <div class="flex items-center gap-2">
            <input class="input flex-1" placeholder="Nome do passageiro" x-model="p.nome" required>
            <button type="button" class="text-red-500 px-2 text-xl" @click="rmPax(idx)" x-show="passageiros.length>1">×</button>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <select class="input" x-model="p.tipo" @change="aplicarTipo(p)">
                @foreach($tipos as $t)<option value="{{ $t->nome }}" data-pct="{{ $t->percentual }}" data-taxa="{{ $t->paga_taxa?1:0 }}">{{ $t->nome }} ({{ $t->percentual }}%)</option>@endforeach
              </select>
            </div>
            <input class="input" placeholder="Documento (opcional)" x-model="p.documento">
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div><label class="text-[11px] text-slate-500">Passagem</label>
              <input type="number" step="0.01" min="0" class="input" x-model.number="p.valor_passagem" @input="recalc()"></div>
            <div><label class="text-[11px] text-slate-500">Taxa embarque</label>
              <input type="number" step="0.01" min="0" class="input" x-model.number="p.valor_taxa" @input="recalc()"></div>
          </div>
          <div x-show="usaPoltrona" x-cloak>
            <button type="button" class="btn btn-ghost btn-sm w-full" @click="abrirMapa(idx)">
              <span x-text="p.poltrona ? ('Poltrona '+p.poltrona) : 'Escolher poltrona'"></span>
            </button>
          </div>
          <div class="text-right text-sm font-semibold" style="color:{{ $cor }}">
            Subtotal: <span x-text="FBRE.moeda((Number(p.valor_passagem)||0)+(Number(p.valor_taxa)||0))"></span>
          </div>
        </div>
      </template>
    </div>

    {{-- comprador + pagamento --}}
    <div class="card p-4 space-y-3">
      <div class="grid grid-cols-2 gap-3">
        <input class="input" placeholder="Comprador (opcional)" x-model="comprador_nome">
        <input class="input" placeholder="Telefone (opcional)" x-model="comprador_telefone">
      </div>
      <div>
        <label class="label">Forma</label>
        <select class="input" x-model="forma">
          <option value="avista">À vista (pago agora)</option>
          <option value="areceber">A receber</option>
          <option value="faturado">Faturado</option>
        </select>
      </div>
      <div x-show="forma==='avista'" x-cloak class="grid grid-cols-2 gap-2">
        <div><label class="text-[11px] text-slate-500">Dinheiro</label><input type="number" step="0.01" min="0" class="input" x-model.number="pg.dinheiro" @input="recalc()"></div>
        <div><label class="text-[11px] text-slate-500">PIX</label><input type="number" step="0.01" min="0" class="input" x-model.number="pg.pix" @input="recalc()"></div>
        <div><label class="text-[11px] text-slate-500">Débito</label><input type="number" step="0.01" min="0" class="input" x-model.number="pg.debito" @input="recalc()"></div>
        <div><label class="text-[11px] text-slate-500">Crédito</label><input type="number" step="0.01" min="0" class="input" x-model.number="pg.credito" @input="recalc()"></div>
      </div>
      <div><label class="label">Desconto</label><input type="number" step="0.01" min="0" class="input" x-model.number="desconto" @input="recalc()"></div>
    </div>

    {{-- total --}}
    <div class="card p-4">
      <div class="flex items-center justify-between text-lg font-bold">
        <span>Total</span><span style="color:{{ $cor }}" x-text="FBRE.moeda(total)"></span>
      </div>
      <div class="flex items-center justify-between text-sm text-slate-500">
        <span>Pago</span><span x-text="FBRE.moeda(pago)"></span>
      </div>
      <div class="flex items-center justify-between text-sm" :class="devido>0.009?'text-red-600 font-semibold':'text-emerald-600'">
        <span>Devido</span><span x-text="FBRE.moeda(devido)"></span>
      </div>
    </div>

    <button type="submit" class="btn w-full text-white" style="background:{{ $cor }}" x-show="total>0 || forma!=='avista'"
      :disabled="enviando" x-text="enviando ? 'Salvando…' : 'Salvar passagem'"></button>
    <p class="text-center text-xs text-slate-400" x-show="total<=0 && forma==='avista'" x-cloak>Informe o valor ou mude a forma para salvar.</p>
  </form>

  {{-- MAPA DE POLTRONAS --}}
  <div x-show="mapaAberto" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-3" @click="mapaAberto=false">
    <div class="bg-white rounded-2xl p-4 w-full max-w-md max-h-[85vh] overflow-auto" @click.stop>
      <div class="flex items-center justify-between mb-3">
        <div class="font-bold">Escolha a poltrona</div><button class="text-xl px-2" @click="mapaAberto=false">×</button>
      </div>
      <div class="flex gap-3 text-xs mb-2">
        <span class="chip bg-slate-100 text-slate-600">livre</span>
        <span class="chip bg-red-100 text-red-700">ocupada</span>
        <span class="chip" style="background:{{ $cor }}20;color:{{ $cor }}">selecionada</span>
      </div>
      <div class="grid grid-cols-5 gap-2">
        <template x-for="n in qtdPoltronas" :key="n">
          <button type="button"
            class="aspect-square rounded-lg text-sm font-semibold border"
            :class="assentoClasse(n)"
            :disabled="estaOcupada(n) && !poltronaMinha(n)"
            @click="escolherPoltrona(n)" x-text="n"></button>
        </template>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  window.FBRE_OP = @js(['prefixo'=>auth()->user()->prefixo ?: 'OP'.auth()->id(), 'proximoSeq'=>(int)(auth()->user()->proximo_seq ?: 1)]);

  function novaPassagem(){
    const PRECOS = @js($precos);
    return {
      online: navigator.onLine,
      origem: @js($ultimaOrigem ?? ''), destino: '',
      embarcacao_id: '', data_viagem: '',
      usaPoltrona: false, qtdPoltronas: 0, ocupadas: [],
      basePassagem: 0, baseTaxa: 0, precoInfo: false,
      comprador_nome: '', comprador_telefone: '',
      forma: 'avista', desconto: 0,
      pg: { dinheiro:0, pix:0, debito:0, credito:0 },
      passageiros: [],
      total:0, pago:0, devido:0,
      enviando:false, numeroPreview:'—',
      mapaAberto:false, paxAtual:0,

      novoPax(){ return { nome:'', tipo:@js($tipos->first()->nome ?? 'Adulto'), percentual:@js($tipos->first()->percentual ?? 100), paga_taxa:true, documento:'', valor_passagem:0, valor_taxa:0, poltrona:'' }; },
      init(){ this.passageiros=[this.novoPax()]; this.previewNumero(); window.addEventListener('online',()=>this.online=true); window.addEventListener('offline',()=>this.online=false); this.recalc(); },
      async previewNumero(){ try{ const n = window.FBREOffline ? window.FBREOffline.gerarNumeroPreview?.() : null; }catch(e){} const op=window.FBRE_OP||{}; this.numeroPreview=(op.prefixo||'OP')+'-'+String(op.proximoSeq||1).padStart(4,'0'); },

      aplicarPreco(){
        const o=(this.origem||'').trim().toLowerCase(), d=(this.destino||'').trim().toLowerCase();
        const p = PRECOS.find(x=> (x.origem.toLowerCase()===o && x.destino.toLowerCase()===d) || (x.origem.toLowerCase()===d && x.destino.toLowerCase()===o));
        if(p){ this.basePassagem=Number(p.passagem)||0; this.baseTaxa=Number(p.taxa_embarque)||0; this.precoInfo=true; this.passageiros.forEach(x=>this.aplicarTipo(x)); }
        else { this.precoInfo=false; }
      },
      aplicarTipo(p){
        const tipos=@js($tipos);
        const t=tipos.find(x=>x.nome===p.tipo);
        if(t){ p.percentual=t.percentual; p.paga_taxa=!!t.paga_taxa; }
        if(this.basePassagem>0){ p.valor_passagem=Math.round(this.basePassagem*(p.percentual/100)*100)/100; }
        p.valor_taxa = p.paga_taxa ? this.baseTaxa : 0;
        this.recalc();
      },
      addPax(){ const p=this.novoPax(); if(this.basePassagem>0){ p.valor_passagem=this.basePassagem; p.valor_taxa=this.baseTaxa; } this.passageiros.push(p); this.recalc(); },
      rmPax(i){ this.passageiros.splice(i,1); this.recalc(); },

      mudarEmbarcacao(ev){
        const opt=ev?.target?.selectedOptions?.[0];
        this.usaPoltrona = opt?.dataset.poltrona==='1';
        this.qtdPoltronas = parseInt(opt?.dataset.qtd||'0',10)||0;
        if(this.usaPoltrona) this.carregarOcupadas(); else this.passageiros.forEach(p=>p.poltrona='');
      },
      async carregarOcupadas(){
        if(!this.usaPoltrona || !this.embarcacao_id || !this.online) return;
        try{ const u=new URL('/passagens/assentos',location.origin); u.searchParams.set('embarcacao_id',this.embarcacao_id); if(this.data_viagem)u.searchParams.set('data_viagem',this.data_viagem);
          const r=await fetch(u,{headers:{Accept:'application/json'},credentials:'same-origin'}); const j=await r.json(); this.ocupadas=(j.ocupadas||[]).map(String);
        }catch(e){ this.ocupadas=[]; }
      },
      abrirMapa(i){ this.paxAtual=i; this.mapaAberto=true; this.carregarOcupadas(); },
      estaOcupada(n){ return this.ocupadas.includes(String(n)); },
      poltronaMinha(n){ return this.passageiros.some(p=>String(p.poltrona)===String(n)); },
      assentoClasse(n){ const minha=String(this.passageiros[this.paxAtual]?.poltrona)===String(n); if(minha) return 'text-white'; if(this.estaOcupada(n)||this.poltronaMinha(n)) return 'bg-red-100 text-red-700 border-red-200 opacity-70'; return 'bg-slate-50 hover:bg-slate-100 border-slate-200'; },
      escolherPoltrona(n){ if(this.estaOcupada(n)&&!this.poltronaMinha(n))return; this.passageiros[this.paxAtual].poltrona=String(n); this.mapaAberto=false; },

      recalc(){
        let sub=0, taxa=0;
        this.passageiros.forEach(p=>{ sub+=Number(p.valor_passagem)||0; taxa+=Number(p.valor_taxa)||0; });
        const desc=Number(this.desconto)||0;
        this.total=Math.max(sub+taxa-desc,0);
        this.pago = this.forma==='avista' ? (Number(this.pg.dinheiro)||0)+(Number(this.pg.pix)||0)+(Number(this.pg.debito)||0)+(Number(this.pg.credito)||0) : 0;
        this.devido=Math.max(this.total-this.pago,0);
      },

      montarDados(){
        const pagamentos=[];
        if(this.forma==='avista'){ for(const m of ['dinheiro','pix','debito','credito']){ const v=Number(this.pg[m])||0; if(v>0) pagamentos.push({meio:m,valor:v}); } }
        return {
          origem:this.origem, destino:this.destino,
          embarcacao_id:this.embarcacao_id||null, data_viagem:this.data_viagem||null,
          comprador_nome:this.comprador_nome||null, comprador_telefone:this.comprador_telefone||null,
          forma:this.forma, desconto:Number(this.desconto)||0,
          passageiros:this.passageiros.map(p=>({ nome:p.nome, tipo:p.tipo, percentual:p.percentual, documento:p.documento||null, poltrona:p.poltrona||null, valor_passagem:Number(p.valor_passagem)||0, valor_taxa:Number(p.valor_taxa)||0 })),
          pagamentos,
        };
      },

      async salvar(){
        if(this.enviando) return;
        if(this.passageiros.some(p=>!p.nome.trim())){ alert('Informe o nome de todos os passageiros.'); return; }
        this.enviando=true;
        const dados=this.montarDados();
        try{
          const r=await window.FBREOffline.salvarModulo('passagem', dados);
          if(r.servidor && r.servidor.ok){ window.location = r.servidor.url; return; }
          // offline: imprime comprovante local e recomeça
          await window.FBREOffline.imprimirModuloLocal(r.local);
          alert('Sem internet: passagem '+r.local.dados.numero_completo+' salva no aparelho. Vai subir sozinha quando a conexão voltar.');
          window.location.reload();
        }catch(e){ alert('Erro ao salvar: '+e.message); this.enviando=false; }
      },
    };
  }

  // comprovante local 80mm da passagem (offline)
  document.addEventListener('DOMContentLoaded',()=>{
    if(!window.FBREOffline) return;
    window.FBREOffline.renderers['passagem'] = function(item, b){
      const e=(b&&b.empresa)||{}; const d=item.dados;
      const esc=s=>String(s??'').replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
      const moeda=v=>(Number(v)||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
      const sub=d.passageiros.reduce((s,p)=>s+(Number(p.valor_passagem)||0)+(Number(p.valor_taxa)||0),0);
      const desc=Number(d.desconto)||0, total=Math.max(sub-desc,0);
      const pago=(d.pagamentos||[]).reduce((s,p)=>s+(Number(p.valor)||0),0), devido=Math.max(total-pago,0);
      const linhas=d.passageiros.map(p=>`<tr><td>${esc(p.nome)}<div class="sm">${esc(p.tipo)}${p.poltrona?(' · poltrona '+esc(p.poltrona)):''}</div></td><td class="r b">${moeda((Number(p.valor_passagem)||0)+(Number(p.valor_taxa)||0)).replace('R$','').trim()}</td></tr>`).join('');
      const html=`<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Passagem ${esc(d.numero_completo)}</title>
      <style>@page{margin:0;size:80mm auto}*{box-sizing:border-box}body{margin:0;padding:3mm 4mm;width:80mm;font-family:"Courier New",monospace;font-size:11.5px}
      .c{text-align:center}.r{text-align:right}.b{font-weight:bold}.xl{font-size:18px}.lg{font-size:14px}.sm{font-size:10px;color:#333}hr{border:0;border-top:1px dashed #000;margin:4px 0}
      table{width:100%;border-collapse:collapse}td{padding:2px 0;vertical-align:top}</style></head><body onload="window.print()">
      <div class="c b lg">${esc((e.nome||'FBRE').toUpperCase())}</div>${e.cnpj?`<div class="c sm">CNPJ ${esc(e.cnpj)}</div>`:''}<hr>
      <div class="c b xl">PASSAGEM ${esc(d.numero_completo)}</div>
      <div class="c sm">${new Date().toLocaleString('pt-BR')}</div><hr>
      <table><tr><td class="b">ORIGEM</td><td class="r">${esc(d.origem)}</td></tr><tr><td class="b">DESTINO</td><td class="r">${esc(d.destino)}</td></tr>${d.data_viagem?`<tr><td class="b">VIAGEM</td><td class="r">${esc(d.data_viagem)}</td></tr>`:''}</table><hr>
      <div class="b">PASSAGEIROS</div><table>${linhas}</table><hr>
      <table>${desc>0?`<tr><td>Desconto</td><td class="r">-${moeda(desc)}</td></tr>`:''}<tr class="lg"><td class="b">TOTAL</td><td class="r b">${moeda(total)}</td></tr><tr><td>Pago</td><td class="r">${moeda(pago)}</td></tr><tr><td class="b">DEVIDO</td><td class="r b">${moeda(devido)}</td></tr></table>
      ${e.rodape?`<hr><div class="c sm">${esc(e.rodape)}</div>`:''}<hr><div class="c sm">Apresente esta passagem no embarque.</div><br><br></body></html>`;
      const w=window.open('','fbre_print','width=420,height=640'); if(!w)return alert('Permita pop-ups para imprimir.'); w.document.open(); w.document.write(html); w.document.close();
    };
  });
</script>
@endpush
