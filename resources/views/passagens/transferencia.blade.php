@extends('layouts.app')
@section('titulo','Transferência de transporte')
@section('conteudo')
<div class="max-w-6xl mx-auto space-y-4" x-data="transfTool()">

  <div class="rounded-2xl px-4 py-3 text-white flex items-center gap-3" style="background:{{ $cor ?? '#0b1a33' }}">
    <span class="text-2xl">🔄</span>
    <div><div class="font-bold">Transferência de transporte</div><div class="text-[11px] opacity-80">Move todos os passageiros de uma viagem para outra lancha</div></div>
    <a href="{{ route('passagens.index') }}" class="btn btn-sm bg-white/20 text-white ml-auto">← Voltar</a>
  </div>

  @if(session('ok'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-3 py-2 text-sm">✅ {{ session('ok') }}</div>@endif

  {{-- SELETORES --}}
  <form method="get" class="card p-3 grid grid-cols-1 sm:grid-cols-3 gap-2">
    <div>
      <label class="label">Transporte de ORIGEM</label>
      <select name="origem_id" class="input w-full" onchange="this.form.submit()">
        <option value="">— escolha —</option>
        @foreach($embarcacoes as $e)<option value="{{ $e->id }}" @selected((string)$origemId===(string)$e->id)>🚤 {{ $e->nome }}</option>@endforeach
      </select>
    </div>
    <div>
      <label class="label">Data da viagem</label>
      <select name="data_viagem" class="input w-full" onchange="this.form.submit()" @if(!$origemId)disabled @endif>
        <option value="">— escolha —</option>
        @foreach($datas as $dv)@php $dvs=\Illuminate\Support\Carbon::parse($dv)->toDateString(); @endphp<option value="{{ $dvs }}" @selected((string)$dataViagem===$dvs)>📅 {{ \Illuminate\Support\Carbon::parse($dv)->format('d/m/Y') }}</option>@endforeach
      </select>
    </div>
    <div>
      <label class="label">Transporte de DESTINO</label>
      <select name="destino_id" class="input w-full" onchange="this.form.submit()" @if(!$dataViagem)disabled @endif>
        <option value="">— escolha —</option>
        @foreach($embarcacoes as $e)@if((string)$e->id!==(string)$origemId)<option value="{{ $e->id }}" @selected((string)$destinoId===(string)$e->id)>🚤 {{ $e->nome }}</option>@endif @endforeach
      </select>
    </div>
  </form>

  @if(!$dados)
    <div class="card p-6 text-center text-slate-400">Escolha o transporte de origem e a data da viagem para ver os passageiros.</div>
  @elseif(!($dados['passageiros'] ?? []))
    <div class="card p-6 text-center text-slate-400">Nenhum passageiro ativo nessa viagem.</div>
  @else

    <div class="card p-3 flex flex-wrap items-center gap-3">
      <div class="text-sm"><b x-text="nPax"></b> passageiro(s) · <b x-text="nSentados"></b> com poltrona</div>
      <template x-if="destino">
        <div class="text-sm">Destino: <b x-text="destino.nome"></b> · <span :class="cabe?'text-emerald-600':'text-rose-600'"><b x-text="nLivresDestino"></b> poltrona(s) livre(s)</span></div>
      </template>
      <div class="ml-auto flex gap-2">
        <button type="button" class="btn btn-sm" @click="limparTudo()" :disabled="!destino">Limpar</button>
        <button type="button" class="btn btn-sm btn-accent" @click="organizar()" :disabled="!destino">✨ Organizar automaticamente</button>
      </div>
    </div>

    <template x-if="destino && !cabe">
      <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-700 px-3 py-2 text-sm">⚠️ O destino não tem poltronas suficientes (<b x-text="nLivresDestino"></b> livres para <b x-text="nPax"></b> passageiros). Escolha outra lancha ou libere assentos.</div>
    </template>

    <template x-if="destino">
      <div class="text-[12px] text-slate-500">Dica: clique num passageiro (mapa da esquerda ou na lista) e depois numa poltrona livre do destino (direita) para atribuir. Clique de novo numa poltrona verde para desfazer.</div>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">

      <div class="card p-3">
        <div class="font-bold text-sm text-slate-600 mb-2">🚤 Origem: {{ $dados['origem']['nome'] ?? '—' }} <span class="text-slate-400 font-normal">(distribuição atual)</span></div>
        <div class="mx-auto" style="max-width:380px">
          <div class="text-center text-[11px] font-bold text-slate-400 mb-1">⬆ PROA (frente)</div>
          <div class="rounded-t-[40px] rounded-b-2xl border-2 border-slate-300 bg-slate-50 px-2 py-3">
            <template x-if="temLayout(origem)">
              <div>
                <template x-for="(bloco,bi) in blocos(origem)" :key="'ob'+bi">
                  <div :class="bi>0?'mt-2 pt-2 border-t border-dashed border-slate-300 space-y-1':'space-y-1'">
                    <template x-for="(f,ri) in bloco" :key="'or'+bi+'_'+ri">
                      <div class="flex items-center justify-center gap-1">
                        <div class="flex gap-1"><template x-for="n in f.esq" :key="'oe'+n">
                          <button type="button" class="min-w-[40px] h-9 px-0.5 rounded-t-lg rounded-b-md border-2 text-[10px] font-bold leading-tight" :class="classeOrigem(n)" @click="paxNaPoltronaOrigem(n)&&selecionar(paxNaPoltronaOrigem(n).passageiro_id)"><span x-text="n"></span><span class="block text-[8px] font-normal truncate" style="max-width:40px" x-text="(paxNaPoltronaOrigem(n)?.nome||'').split(' ')[0]"></span></button>
                        </template></div>
                        <div class="w-3 text-center text-[9px] text-slate-300" x-show="f.dir.length">║</div>
                        <div class="flex gap-1"><template x-for="n in f.dir" :key="'od'+n">
                          <button type="button" class="min-w-[40px] h-9 px-0.5 rounded-t-lg rounded-b-md border-2 text-[10px] font-bold leading-tight" :class="classeOrigem(n)" @click="paxNaPoltronaOrigem(n)&&selecionar(paxNaPoltronaOrigem(n).passageiro_id)"><span x-text="n"></span><span class="block text-[8px] font-normal truncate" style="max-width:40px" x-text="(paxNaPoltronaOrigem(n)?.nome||'').split(' ')[0]"></span></button>
                        </template></div>
                      </div>
                    </template>
                  </div>
                </template>
              </div>
            </template>
            <template x-if="!temLayout(origem)">
              <div class="space-y-1">
                <template x-for="(f,ri) in fileiras(origem)" :key="'ofn'+ri">
                  <div class="flex items-center justify-center gap-1">
                    <div class="flex gap-1.5"><template x-for="n in f.esq" :key="'ofe'+n">
                      <button type="button" class="w-11 h-10 rounded-t-xl rounded-b-md border-2 text-[10px] font-bold leading-tight" :class="classeOrigem(n)" @click="paxNaPoltronaOrigem(n)&&selecionar(paxNaPoltronaOrigem(n).passageiro_id)"><span x-text="n"></span><span class="block text-[8px] font-normal truncate" style="max-width:42px" x-text="(paxNaPoltronaOrigem(n)?.nome||'').split(' ')[0]"></span></button>
                    </template></div>
                    <div class="w-5 text-center text-[9px] text-slate-300">║</div>
                    <div class="flex gap-1.5"><template x-for="n in f.dir" :key="'ofd'+n">
                      <button type="button" class="w-11 h-10 rounded-t-xl rounded-b-md border-2 text-[10px] font-bold leading-tight" :class="classeOrigem(n)" @click="paxNaPoltronaOrigem(n)&&selecionar(paxNaPoltronaOrigem(n).passageiro_id)"><span x-text="n"></span><span class="block text-[8px] font-normal truncate" style="max-width:42px" x-text="(paxNaPoltronaOrigem(n)?.nome||'').split(' ')[0]"></span></button>
                    </template></div>
                  </div>
                </template>
              </div>
            </template>
          </div>
          <div class="text-center text-[11px] font-bold text-slate-400 mt-1">POPA (fundo) ⬇</div>
        </div>
      </div>

      <div class="card p-3">
        <div class="font-bold text-sm text-slate-600 mb-2">🚤 Destino: <span x-text="destino?destino.nome:'— escolha a lancha —'"></span></div>
        <template x-if="!destino"><div class="p-6 text-center text-slate-400 text-sm">Escolha o transporte de destino acima.</div></template>
        <template x-if="destino">
          <div class="mx-auto" style="max-width:380px">
            <div class="text-center text-[11px] font-bold text-slate-400 mb-1">⬆ PROA (frente)</div>
            <div class="rounded-t-[40px] rounded-b-2xl border-2 px-2 py-3" style="border-color:#10b981;background:#ecfdf5">
              <template x-if="temLayout(destino)">
                <div>
                  <template x-for="(bloco,bi) in blocos(destino)" :key="'db'+bi">
                    <div :class="bi>0?'mt-2 pt-2 border-t border-dashed border-emerald-300 space-y-1':'space-y-1'">
                      <template x-for="(f,ri) in bloco" :key="'dr'+bi+'_'+ri">
                        <div class="flex items-center justify-center gap-1">
                          <div class="flex gap-1"><template x-for="n in f.esq" :key="'de'+n">
                            <button type="button" class="min-w-[40px] h-9 px-0.5 rounded-t-lg rounded-b-md border-2 text-[10px] font-bold leading-tight" :class="classeDestino(n)" @click="atribuirSeat(n)"><span x-text="n"></span><span class="block text-[8px] font-normal truncate" style="max-width:40px" x-text="(paxNoDestino(n)?.nome||'').split(' ')[0]"></span></button>
                          </template></div>
                          <div class="w-3 text-center text-[9px] text-emerald-300" x-show="f.dir.length">║</div>
                          <div class="flex gap-1"><template x-for="n in f.dir" :key="'dd'+n">
                            <button type="button" class="min-w-[40px] h-9 px-0.5 rounded-t-lg rounded-b-md border-2 text-[10px] font-bold leading-tight" :class="classeDestino(n)" @click="atribuirSeat(n)"><span x-text="n"></span><span class="block text-[8px] font-normal truncate" style="max-width:40px" x-text="(paxNoDestino(n)?.nome||'').split(' ')[0]"></span></button>
                          </template></div>
                        </div>
                      </template>
                    </div>
                  </template>
                </div>
              </template>
              <template x-if="!temLayout(destino)">
                <div class="space-y-1">
                  <template x-for="(f,ri) in fileiras(destino)" :key="'dfn'+ri">
                    <div class="flex items-center justify-center gap-1">
                      <div class="flex gap-1.5"><template x-for="n in f.esq" :key="'dfe'+n">
                        <button type="button" class="w-11 h-10 rounded-t-xl rounded-b-md border-2 text-[10px] font-bold leading-tight" :class="classeDestino(n)" @click="atribuirSeat(n)"><span x-text="n"></span><span class="block text-[8px] font-normal truncate" style="max-width:42px" x-text="(paxNoDestino(n)?.nome||'').split(' ')[0]"></span></button>
                      </template></div>
                      <div class="w-5 text-center text-[9px] text-emerald-300">║</div>
                      <div class="flex gap-1.5"><template x-for="n in f.dir" :key="'dfd'+n">
                        <button type="button" class="w-11 h-10 rounded-t-xl rounded-b-md border-2 text-[10px] font-bold leading-tight" :class="classeDestino(n)" @click="atribuirSeat(n)"><span x-text="n"></span><span class="block text-[8px] font-normal truncate" style="max-width:42px" x-text="(paxNoDestino(n)?.nome||'').split(' ')[0]"></span></button>
                      </template></div>
                    </div>
                  </template>
                </div>
              </template>
            </div>
            <div class="text-center text-[11px] font-bold text-slate-400 mt-1">POPA (fundo) ⬇</div>
            <div class="flex flex-wrap gap-2 justify-center mt-2 text-[10px] text-slate-500">
              <span><span class="inline-block w-3 h-3 rounded bg-white border-2 border-slate-300 align-middle"></span> livre</span>
              <span><span class="inline-block w-3 h-3 rounded bg-emerald-500 align-middle"></span> transferido</span>
              <span><span class="inline-block w-3 h-3 rounded bg-rose-100 border border-rose-300 align-middle"></span> ocupada (outra viagem)</span>
            </div>
          </div>
        </template>
      </div>
    </div>

    <div class="card overflow-hidden">
      <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Passageiros · origem → destino</div>
      <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-[11px] uppercase text-slate-500"><tr>
          <th class="p-2 text-left">Passageiro</th><th class="p-2 text-left">Tipo</th>
          <th class="p-2 text-center">Poltrona origem</th><th class="p-2 text-center">Poltrona destino</th><th class="p-2"></th>
        </tr></thead>
        <tbody class="divide-y">
          <template x-for="p in pax" :key="p.passageiro_id">
            <tr :class="selPax===p.passageiro_id ? 'bg-amber-50' : ''">
              <td class="p-2 font-semibold cursor-pointer" @click="!p.colo && selecionar(p.passageiro_id)" x-text="p.nome"></td>
              <td class="p-2 text-slate-500" x-text="p.colo?'colo':p.tipo"></td>
              <td class="p-2 text-center" x-text="p.poltrona||'—'"></td>
              <td class="p-2 text-center">
                <template x-if="p.colo"><span class="text-slate-400">colo</span></template>
                <template x-if="!p.colo"><span class="font-bold" :class="atrib[p.passageiro_id]?'text-emerald-600':'text-rose-500'" x-text="atrib[p.passageiro_id]||'sem poltrona'"></span></template>
              </td>
              <td class="p-2 text-right">
                <button type="button" class="text-[12px] px-2 py-1 rounded border" :class="selPax===p.passageiro_id?'bg-amber-400 text-white border-amber-500':'border-slate-200 text-slate-600'" x-show="!p.colo" @click="selecionar(p.passageiro_id)" x-text="selPax===p.passageiro_id?'selecionado':'escolher'"></button>
                <button type="button" class="text-[12px] px-2 py-1 rounded border border-rose-200 text-rose-600 ml-1" x-show="!p.colo && atrib[p.passageiro_id]" @click="limparPax(p.passageiro_id)">tirar</button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
      </div>
    </div>

    <form method="post" action="{{ route('passagens.transferencia.processar') }}" x-ref="formT">
      @csrf
      <input type="hidden" name="origem_id" :value="origem?origem.id:''">
      <input type="hidden" name="destino_id" :value="destino?destino.id:''">
      <input type="hidden" name="data_viagem" value="{{ $dados['data_viagem'] ?? '' }}">
      <template x-for="(p,i) in pax" :key="'h'+p.passageiro_id">
        <span>
          <input type="hidden" :name="'mapa['+i+'][passageiro_id]'" :value="p.passageiro_id">
          <input type="hidden" :name="'mapa['+i+'][poltrona]'" :value="atrib[p.passageiro_id]||''">
        </span>
      </template>
    </form>
    <div class="flex items-center gap-3 sticky bottom-0 bg-white/90 backdrop-blur border-t py-3 px-1 rounded-t-xl">
      <div class="text-sm text-slate-600"><b x-text="nSentados"></b>/<b x-text="nPax"></b> com poltrona</div>
      <button type="button" class="btn btn-accent ml-auto" :disabled="!destino || salvando || (destino && !cabe)" @click="confirmar()">
        <span x-show="!salvando">✅ Confirmar transferência</span><span x-show="salvando">Transferindo…</span>
      </button>
    </div>

  @endif
</div>

<script>
function transfTool(){
  return {
    pax: @js($dados['passageiros'] ?? []),
    origem: @js($dados['origem'] ?? null),
    destino: @js($dados['destino'] ?? null),
    atrib: {},
    selPax: null,
    salvando: false,

    seatsNamed(layout){ const txt=(layout||'').replace(/\r/g,''); const out=[];
      txt.split(/\n[ \t]*\n/).forEach(b=>b.split('\n').filter(l=>l.trim()).forEach(linha=>{ const p=linha.split('|');
        (p[0]||'').trim().split(/\s+/).filter(Boolean).forEach(s=>out.push(s));
        (p[1]||'').trim().split(/\s+/).filter(Boolean).forEach(s=>out.push(s)); })); return out; },
    seatsNum(qtd){ const a=[]; for(let i=1;i<=(qtd||0);i++) a.push(String(i)); return a; },
    seatsOf(emb){ if(!emb) return []; const L=(emb.layout||'').trim(); return L?this.seatsNamed(L):this.seatsNum(emb.qtd); },
    blocos(emb){ const txt=(emb.layout||'').replace(/\r/g,''); return txt.split(/\n[ \t]*\n/).map(b=>b.split('\n').filter(l=>l.trim()).map(linha=>{const p=linha.split('|'); return {esq:(p[0]||'').trim().split(/\s+/).filter(Boolean), dir:(p[1]||'').trim().split(/\s+/).filter(Boolean)};})).filter(b=>b.length); },
    fileiras(emb){ const col=emb.colunas||4, qtd=emb.qtd||0, meio=Math.ceil(col/2); const rows=[]; let n=1; while(n<=qtd){const r=[]; for(let c=0;c<col&&n<=qtd;c++)r.push(String(n++)); rows.push({esq:r.slice(0,meio),dir:r.slice(meio)});} return rows; },
    temLayout(emb){ return !!(emb && (emb.layout||'').trim().length); },

    paxNaPoltronaOrigem(seat){ return this.pax.find(p=>String(p.poltrona)===String(seat)); },
    paxNoDestino(seat){ const id=Object.keys(this.atrib).find(k=>String(this.atrib[k])===String(seat)); return id?this.pax.find(p=>String(p.passageiro_id)===String(id)):null; },
    destinoOcupadaOutros(seat){ return ((this.destino&&this.destino.ocupadas)||[]).map(String).includes(String(seat)); },

    selecionar(id){ this.selPax=(this.selPax===id?null:id); },
    atribuirSeat(seat){
      if(!this.destino) return;
      if(this.destinoOcupadaOutros(seat)) return;
      const oc=this.paxNoDestino(seat);
      if(oc){ delete this.atrib[oc.passageiro_id]; return; }
      if(!this.selPax){ return; }
      this.atrib[this.selPax]=String(seat);
      const prox=this.pax.find(p=>!p.colo && !this.atrib[p.passageiro_id] && p.passageiro_id!==this.selPax);
      this.selPax = prox?prox.passageiro_id:null;
    },
    limparPax(id){ delete this.atrib[id]; },
    organizar(){ this.atrib={}; this.selPax=null;
      const livres=this.seatsOf(this.destino).filter(s=>!this.destinoOcupadaOutros(s));
      const ordem=this.seatsOf(this.origem); const idx=s=>{const i=ordem.indexOf(String(s)); return i<0?99999:i;};
      const fila=this.pax.filter(p=>!p.colo).slice().sort((a,b)=>idx(a.poltrona)-idx(b.poltrona));
      let i=0; fila.forEach(p=>{ if(i<livres.length) this.atrib[p.passageiro_id]=livres[i++]; });
    },
    limparTudo(){ this.atrib={}; this.selPax=null; },

    get nPax(){ return this.pax.filter(p=>!p.colo).length; },
    get nSentados(){ return this.pax.filter(p=>!p.colo && this.atrib[p.passageiro_id]).length; },
    get nLivresDestino(){ return this.destino?this.seatsOf(this.destino).filter(s=>!this.destinoOcupadaOutros(s)).length:0; },
    get cabe(){ return !!this.destino && this.nLivresDestino>=this.nPax; },
    get completo(){ return this.nPax>0 && this.nSentados===this.nPax; },

    classeDestino(seat){ if(this.destinoOcupadaOutros(seat)) return 'bg-rose-100 border-rose-300 text-rose-400 cursor-not-allowed';
      if(this.paxNoDestino(seat)) return 'bg-emerald-500 border-emerald-600 text-white'; return 'bg-white border-slate-300 hover:border-emerald-400 text-slate-600'; },
    classeOrigem(seat){ const p=this.paxNaPoltronaOrigem(seat); if(!p) return 'bg-white border-slate-200 text-slate-300';
      if(this.selPax===p.passageiro_id) return 'bg-amber-400 border-amber-500 text-white';
      if(this.atrib[p.passageiro_id]) return 'bg-emerald-100 border-emerald-300 text-emerald-700';
      return 'bg-sky-100 border-sky-300 text-sky-700'; },

    confirmar(){
      if(!this.destino){ alert('Escolha o transporte de destino.'); return; }
      if(!this.cabe){ alert('O destino não tem poltronas suficientes.'); return; }
      if(!this.completo){ if(!confirm('Nem todos os passageiros têm poltrona atribuída. Confirmar assim mesmo?')) return; }
      this.salvando=true;
      this.$nextTick(()=>{ this.$refs.formT.submit(); });
    },
  };
}
</script>
@endsection
