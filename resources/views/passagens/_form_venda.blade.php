<form method="post" action="{{ route('passagens.store') }}" x-data="novaPassagem()" @submit.prevent="salvar($event)" @keydown.window="teclaAtalho($event)"
      class="mx-auto max-w-[1440px] space-y-2 pb-28 lg:pb-0" @php $emp=\App\Support\Tenant::empresa(); $corMod=(bool)($emp?->config('cores_por_modulo', true) ?? true); @endphp style="--cor1:{{ $corMod ? '#059669' : ($emp?->cor_primaria ?? '#0b1a33') }};--cor2:{{ $corMod ? '#34d399' : ($emp?->cor_secundaria ?? '#00a8e8') }}">
@csrf
<div class="flex items-center justify-between gap-3 flex-wrap">
  <div class="flex items-center gap-x-4 gap-y-1 flex-wrap">
    <h1 class="text-lg font-extrabold" style="color:var(--cor1)">🎫 Nova passagem</h1>
    <span class="text-[11px] font-bold rounded-lg px-2 py-0.5 text-white" style="background:var(--cor1)"><span x-text="edMode ? ('✏️ Editando '+edNumero+(edFila>0?(' · +'+edFila+' na fila'):'')) : ('Nº '+numeroPreview)"></span></span>
    @if($podeRetroativo)
    <div class="flex items-center gap-1.5">
      <span class="lbl !mb-0 whitespace-nowrap">Lançamento</span>
      <input type="date" class="field !min-h-0 !py-1 w-32 text-sm" x-model="lancamentoEm" :max="hojeISO">
    </div>
    @endif
    {{-- ABAS DE VENDAS (ao lado do título) --}}
    <div class="flex items-center gap-1 flex-wrap">
      <template x-for="(a,ai) in abas" :key="ai">
        <div x-show="abas.length>1" class="flex items-center shrink-0 rounded-lg border-2 overflow-hidden" :class="ai===abaAtual ? 'border-[var(--cor1)]' : 'border-slate-200'">
          <button type="button" @click="trocarAba(ai)" class="px-2.5 py-1 text-[12px] font-bold whitespace-nowrap" :class="ai===abaAtual ? 'text-white' : 'text-slate-600 bg-white'" :style="ai===abaAtual ? 'background:var(--cor1)' : ''" x-text="abaLabel(ai)"></button>
          <button type="button" @click="fecharAba(ai)" class="px-1.5 py-1 text-[13px] font-extrabold leading-none" :class="ai===abaAtual ? 'text-white/85' : 'text-slate-400 bg-white'" :style="ai===abaAtual ? 'background:var(--cor1)' : ''" title="Remover esta venda">×</button>
        </div>
      </template>
      <button type="button" x-show="!edMode" @click="novaAba()" class="shrink-0 rounded-lg border-2 border-dashed px-2.5 py-1 text-[12px] font-bold whitespace-nowrap" style="border-color:var(--cor1);color:var(--cor1)">＋ <span x-text="abas.length>1 ? 'Nova venda' : 'Outra venda'"></span></button>
    </div>
  </div>

</div>

<div class="grid gap-2 lg:grid-cols-12 items-start">

  {{-- COLUNA 1: rota + comprador --}}
  <div class="lg:col-span-3 space-y-2">
    <section class="sec shrink-0">
      <div class="sec-h"><span class="n">1</span><span class="ic">🗺️</span><span class="t">Rota</span></div>
      <div class="sec-b">
        <div class="grid grid-cols-2 gap-2 items-start">
          <div><label class="lbl">Transporte</label>
            <select class="field" x-model="embarcacaoSel"><option value="">—</option>
              @foreach($embarcacoes as $b)<option value="{{ $b->id }}">{{ $b->nome }}{{ $b->usa_poltrona ? ' 💺' : '' }}</option>@endforeach
            </select></div>
        <div x-show="viagemData" x-cloak class="max-w-full min-w-0">
          <label class="lbl">Viagem</label>
          <button type="button" @click="abrirCal()" class="field !min-h-0 !py-1 text-sm font-semibold w-full text-left truncate">
            📅 <span x-text="(viagemLabel||'escolher').replace(/ \d{1,2}:\d{2}.*$/,'')"></span>
          </button>
          <div x-show="calAberto" x-cloak @click="calAberto=false" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
            <div @click.stop class="bg-white rounded-2xl border shadow-2xl p-3 w-72 max-w-full text-slate-700">
              <div class="text-center mb-2">
                <span x-show="linhaNome" class="block text-[11px] font-semibold text-slate-400" x-text="linhaNome"></span>
                <span class="text-sm font-bold" style="color:var(--cor1)" x-text="viagemLabel"></span>
              </div>
              <div class="flex items-center justify-between mb-1">
                <button type="button" @click="mudarMes(-1)" class="w-8 h-8 rounded hover:bg-slate-100 text-lg leading-none">‹</button>
                <div class="text-sm font-bold capitalize" x-text="nomeMes"></div>
                <button type="button" @click="mudarMes(1)" class="w-8 h-8 rounded hover:bg-slate-100 text-lg leading-none">›</button>
              </div>
              <div class="grid grid-cols-7 text-center text-[10px] font-bold text-slate-400 mb-1">
                <template x-for="(d,i) in ['D','S','T','Q','Q','S','S']" :key="i"><div x-text="d"></div></template>
              </div>
              <div class="grid grid-cols-7 gap-0.5">
                <template x-for="c in calDias" :key="c.iso">
                  <button type="button" :disabled="!c.valido" @click="clicarDia(c)"
                    :title="c.viagens.length>1 ? (c.viagens.length+' lanchas — clique p/ escolher') : (c.viagens.length===1 && !c.temAtual ? ('🚤 '+c.viagens[0].embNome+(c.viagens[0].hora?(' · '+c.viagens[0].hora):'')+' — clique pra trocar') : '')"
                    class="h-9 rounded text-sm transition relative"
                    :class="c.sel ? 'text-white font-bold' : (c.valido ? 'font-bold hover:brightness-95' : (c.outroMes ? 'text-slate-200' : 'text-slate-300 cursor-not-allowed'))"
                    :style="c.sel ? 'background:var(--cor1)' : (c.valido ? (c.temAtual ? 'color:var(--cor1);background:color-mix(in srgb, var(--cor2) 20%, white)' : 'color:#b45309;background:#fff7ed;border:1px dashed #f59e0b') : '')">
                    <span x-text="c.dia"></span>
                    <template x-if="c.valido && c.viagens.length>1"><span class="absolute top-0.5 right-0.5 text-[8px] font-extrabold" style="color:#f59e0b" x-text="c.viagens.length"></span></template>
                  </button>
                </template>
              </div>
              {{-- seletor quando há mais de uma lancha no dia --}}
              <div x-show="calEscolha" x-cloak class="mt-2 border-t pt-2">
                <div class="text-[11px] font-bold text-slate-500 mb-1">Mais de uma lancha nesse dia — escolha:</div>
                <template x-for="o in (calEscolha ? calEscolha.opcoes : [])" :key="o.embId">
                  <button type="button" @click="escolherDiaEmb(calEscolha.iso, o.embId)" class="w-full text-left rounded-lg border px-2 py-1.5 mb-1 text-sm font-semibold hover:bg-slate-50 flex items-center justify-between" :class="o.embId===(embarcacaoSel?String(embarcacaoSel):'') ? 'border-[var(--cor1)]' : 'border-slate-200'">
                    <span>🚤 <span x-text="o.embNome"></span><span x-show="o.embId===(embarcacaoSel?String(embarcacaoSel):'')" class="text-[10px] text-slate-400"> (atual)</span></span>
                    <span class="text-slate-400 text-[12px]" x-text="o.hora"></span>
                  </button>
                </template>
                <button type="button" @click="calEscolha=null" class="text-[11px] text-slate-400">cancelar</button>
              </div>
              <div class="text-[10px] text-slate-400 mt-2 text-center leading-snug" x-show="!calEscolha">
                <span style="color:var(--cor1)">■</span> desta lancha
                <template x-if="calDias.some(c=>c.valido && !c.temAtual)"><span> · <span style="color:#f59e0b">▨</span> outra lancha (clique pra trocar)</span></template>
              </div>
            </div>
          </div>
        </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div><label class="lbl">Origem</label>
            <select class="field" x-model="origemSel" required @change="onRota()">
              <option value="">—</option>
              <template x-for="c in origens" :key="c"><option :value="c" x-text="c"></option></template>
            </select>
          </div>
          <div><label class="lbl">Destino</label>
            <select class="field" x-model="destinoSel" required @change="onRota()" x-ref="fDestino">
              <option value="">—</option>
              <template x-for="c in destinos" :key="c"><option :value="c" x-text="c"></option></template>
            </select>
          </div>
        </div>
        <div x-show="precoInfo" class="text-[11px] font-semibold" style="color:var(--cor1)" x-cloak>
          💲 Preço do trecho carregado
        </div>
        <div x-show="gratInfo" x-cloak class="text-[11px] font-semibold flex items-center gap-1 mt-0.5" :class="(gratInfo && gratInfo.restante<=0) ? 'text-red-500' : 'text-slate-400'" title="Idoso (gratuito + meia) dividem a MESMA cota por VIAGEM completa">
          🧓 <span x-text="gratInfo ? ('Idoso (grátis+½) nesta viagem: '+gratInfo.usados+'/'+gratInfo.cota) : ''"></span><span x-show="gratInfo && gratInfo.restante<=0"> · esgotado</span>
        </div>
        <div x-show="pcdInfo" x-cloak class="text-[11px] font-semibold flex items-center gap-1 mt-0.5" :class="(pcdInfo && pcdInfo.restante<=0) ? 'text-red-500' : 'text-slate-400'" title="Vagas PCD já usadas nesta VIAGEM completa">
          ♿ <span x-text="pcdInfo ? ('PCD nesta viagem: '+pcdInfo.usados+'/'+pcdInfo.cota) : ''"></span><span x-show="pcdInfo && pcdInfo.restante<=0"> · esgotado</span>
        </div>
      </div>
    </section>

    @if($mostraComprador ?? true)
    <section class="sec shrink-0">
      <div class="sec-h"><span class="n">2</span><span class="ic">👤</span><span class="t">Comprador (opcional)</span></div>
      <div class="sec-b">
        <div class="pessoa" x-data="pessoaBusca()">
          <div class="relative">
            <label class="lbl">Nome</label>
            <input class="field" :name="'cn'+nonce" autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other" x-model="compradorNome" @input.debounce.300ms="buscar" @focus="aberto=true" @click.outside="aberto=false" placeholder="Quem está comprando">
            <div x-show="aberto && lista.length" x-cloak class="absolute z-20 mt-1 w-full bg-white rounded-xl border shadow-xl max-h-48 overflow-auto">
              <template x-for="p in lista" :key="p.nome+p.telefone">
                <button type="button" @click="escolher(p)" class="w-full text-left px-3 py-2 hover:bg-slate-50 text-sm border-b last:border-0">
                  <span class="font-semibold" x-text="p.nome"></span> <span class="text-slate-500 text-xs" x-text="p.telefone"></span>
                </button>
              </template>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <input class="field" inputmode="tel" maxlength="16" placeholder="WhatsApp" x-model="compradorTel" @input="compradorTel=FBRE.foneMask($event.target.value)">
            <input class="field" :class="{'!border-red-400 !ring-1 !ring-red-300':!FBRE.docOk(compradorDoc)}" inputmode="numeric" placeholder="CPF" x-model="compradorDoc" title="Se digitar um CPF, precisa ser válido.">
          </div>
        </div>
      </div>
    </section>
    @endif
  </div>

  {{-- COLUNA 2: passageiros --}}
  <section class="sec lg:col-span-6">
    <div class="sec-h">
      <span class="n">3</span><span class="ic">🎫</span><span class="t">Passageiros</span>
      <span class="ml-auto text-[13px] font-bold shrink-0">(<span x-text="passageiros.length"></span>)</span>
      <button type="button" class="rounded-lg bg-white/20 hover:bg-white/30 px-2.5 py-1 text-[12px] font-bold shrink-0 whitespace-nowrap" @click="addPax()">＋<span class="hidden sm:inline"> passageiro</span></button>
      @unless(auth()->user()->ehRevendedor())<button type="button" class="rounded-lg bg-white/20 hover:bg-white/30 px-2.5 py-1 text-[12px] font-bold ml-1 shrink-0 whitespace-nowrap" @click="abrirCaptura()">📷<span class="hidden sm:inline"> Captura</span></button>@endunless
    </div>
    <div class="sec-b">
      <div x-show="capturaAviso" x-cloak class="mb-2 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold px-3 py-2" x-text="capturaAviso"></div>
      <div class="rounded-xl border border-slate-200 bg-slate-50 lg:max-h-[52vh] lg:overflow-auto p-2 space-y-1.5">
        <template x-for="(p,i) in passageiros" :key="p.id">
          <div class="pax">
            {{-- linha 1: numero + nome + foto + remover --}}
            <div class="flex items-center gap-2">
              <span class="w-6 h-6 rounded-full text-white text-[11px] font-bold flex items-center justify-center shrink-0" style="background:var(--cor1)" x-text="i+1"></span>
              <div class="relative flex-1">
                <input :id="'pax-nome-'+i" :name="'pn'+nonce+i" class="field !min-h-0 py-1 w-full" placeholder="Nome do passageiro" x-model="p.nome" autocomplete="off" data-lpignore="true" data-1p-ignore data-form-type="other" @input.debounce.300ms="buscarPax(i)" @focus="paxAberto=i" @click.outside="paxAberto===i && (paxAberto=-1)" required>
                <div x-show="paxAberto===i && paxLista.length" x-cloak class="absolute z-30 mt-1 w-full bg-white rounded-xl border shadow-xl max-h-48 overflow-auto">
                  <template x-for="pp in paxLista" :key="pp.nome+pp.telefone">
                    <button type="button" @click="escolherPax(i,pp)" class="w-full text-left px-3 py-2 hover:bg-slate-50 text-sm border-b last:border-0">
                      <span class="font-semibold" x-text="pp.nome"></span> <span class="text-slate-500 text-xs" x-text="pp.documento||pp.telefone"></span>
                    </button>
                  </template>
                </div>
              </div>
              <button type="button" class="w-9 h-9 rounded-lg border-2 shrink-0 text-base leading-none flex items-center justify-center" style="border-color:var(--cor1);color:var(--cor1)" @click="abrirCam(i)" title="Foto do documento">📷</button>
              <img :src="'/'+(p.foto||p.foto_rg)" x-show="p.foto||p.foto_rg" x-cloak class="h-9 w-9 object-cover rounded-lg border shrink-0 cursor-pointer" @click="window.open('/'+(p.foto_rg||p.foto),'_blank')" title="Foto (clique p/ ver o documento)">
              <button type="button" class="w-8 h-8 rounded-full text-slate-400 hover:bg-red-50 hover:text-red-600 text-lg leading-none shrink-0" @click="rmPax(i)" x-show="passageiros.length>1" title="Remover">×</button>
            </div>
            {{-- nascimento + sexo + tipo + valor lado a lado (card compacto) --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-1.5 items-start">
              <div><label class="lbl">Nascimento</label><input type="text" inputmode="numeric" maxlength="10" placeholder="dd/mm/aaaa" class="field !min-h-0 py-1 text-sm w-full" x-model="p.nasc_br" @input="mascaraNasc(p)"><div x-show="idadeTxt(p)" x-cloak class="text-[10px] font-bold mt-0.5" style="color:var(--cor1)" x-text="'\ud83c\udf82 '+idadeTxt(p)"></div></div>
              <div><label class="lbl">Sexo</label><select class="field !min-h-0 py-1 text-sm w-full" x-model="p.sexo"><option value="">—</option><option value="M">Masculino</option><option value="F">Feminino</option></select></div>
              <div><label class="lbl">Tipo</label>
                <select class="field !min-h-0 py-1 text-sm w-full" x-model="p.tipo_id" @change="aplicarTipo(p)">
                  @foreach($tipos->where('colo', false) as $t)@php $lbl = $t->nome.(((($t->modo ?? 'valor')==='porcentagem') && !$t->eh_normal) ? ' ('.$t->percentual.'%)' : ''); @endphp<option value="{{ $t->id }}"@if($t->gratuito || $t->idoso_meia) :disabled="gratInfo && gratInfo.restante<=0" x-text="(gratInfo && gratInfo.restante<=0) ? @js($lbl.' · esgotado') : @js($lbl)"@elseif($t->pcd) :disabled="pcdInfo && pcdInfo.restante<=0" x-text="(pcdInfo && pcdInfo.restante<=0) ? @js($lbl.' · esgotado') : @js($lbl)"@endif>{{ $lbl }}</option>@endforeach
                </select></div>
              <div class="text-right"><label class="lbl" x-text="p.modo==='manual' ? 'Valor (digite)' : 'Valor'"></label>
                <div x-show="p.modo!=='manual'" class="text-base font-extrabold leading-tight py-1" style="color:var(--cor1)" x-text="FBRE.moeda(paxTotal(p))"></div>
                <input x-show="p.modo==='manual'" x-money="p.valor_passagem" inputmode="decimal" placeholder="0,00" class="field !min-h-0 py-1 text-sm w-full text-right font-extrabold" style="color:var(--cor1)" x-cloak></div>
            </div>
            {{-- linha 3: documento + poltrona --}}
            <div class="flex flex-col sm:flex-row sm:items-end gap-2 mt-1.5">
              <div class="flex-1 min-w-0"><label class="lbl">Documento</label>
                <div class="flex gap-1.5">
                  <select class="field !min-h-0 py-1 text-sm !w-[86px] sm:!w-auto !px-2 sm:!px-3 shrink-0" x-model="p.doc_tipo" title="Tipo de documento">
                    <option value="">Tipo</option>
                    <option value="RG">RG</option>
                    <option value="CPF">CPF</option>
                    <option value="CNH">CNH</option>
                    <option value="Certidão">Certidão nasc.</option>
                    <option value="Passaporte">Passaporte</option>
                  </select>
                  <input class="field !min-h-0 py-1 text-sm flex-1 min-w-0 w-full" :class="{'!border-red-400 !ring-1 !ring-red-300':!FBRE.docOk(p.documento)}" placeholder="número do documento" x-model="p.documento" title="Número do documento. Se for CPF, precisa ser válido.">
                </div>
                <div x-show="!FBRE.docOk(p.documento)" x-cloak class="text-[10px] text-red-500 mt-0.5">CPF inválido</div></div>
              <div x-show="usaPoltrona" x-cloak class="shrink-0">
                <div class="sm:hidden"><label class="lbl">Poltrona</label></div>
                <div class="flex gap-1.5">
                <button type="button" class="flex-1 sm:flex-none rounded-lg border-2 px-3 py-2 sm:py-1.5 text-sm font-bold whitespace-nowrap" :style="!p.livre ? 'border-color:var(--cor1);background:var(--cor1);color:#fff' : 'border-color:#cbd5e1;color:#94a3b8'" @click="p.livre=false; abrirMapa(i)" title="Escolher poltrona (F2)">
                  <span x-text="(!p.livre && p.poltrona) ? ('💺 '+p.poltrona) : '💺'"></span><span class="sm:hidden" x-text="(!p.livre && p.poltrona) ? ' · trocar' : ' Escolher poltrona'"></span>
                </button>
                <button type="button" class="rounded-lg border-2 px-4 sm:px-3 py-2 sm:py-1.5 text-sm font-extrabold whitespace-nowrap" :style="p.livre ? 'border-color:#059669;background:#059669;color:#fff' : 'border-color:#cbd5e1;color:#94a3b8'" @click="marcarLivre(i)" title="Poltrona livre (sem assento fixo)">L<span class="sm:hidden font-bold"> · livre</span></button>
                </div>
              </div>
            </div>
            {{-- linha final: telefone + nacionalidade + check-in/bebê + anexos --}}
            <div class="grid grid-cols-2 sm:flex sm:items-end gap-2 mt-2 pt-1.5 border-t border-slate-100">
              <div class="sm:flex-1 min-w-0"><label class="lbl">Telefone</label><input class="field !min-h-0 py-1 text-sm w-full" x-model="p.telefone" @input="p.telefone=FBRE.foneMask($event.target.value)" inputmode="tel" maxlength="16" placeholder="(00) 00000-0000"></div>
              <div class="sm:flex-1 min-w-0"><label class="lbl">Nacionalidade</label><input class="field !min-h-0 py-1 text-sm w-full" x-model="p.nacionalidade" placeholder="Brasileira"></div>
              <div class="col-span-2 sm:col-span-1 flex items-center justify-between sm:contents gap-2 pt-1 sm:pt-0">
              <div class="flex flex-row sm:flex-col gap-4 sm:gap-1 shrink-0 sm:pb-1">
                <label class="flex items-center gap-1.5 sm:gap-1 text-[13px] sm:text-[11px] font-semibold whitespace-nowrap cursor-pointer"><input type="checkbox" x-model="p.checkin" class="w-5 h-5 sm:w-4 sm:h-4 rounded"> Check-in</label>
                <label class="flex items-center gap-1.5 sm:gap-1 text-[13px] sm:text-[11px] font-semibold text-sky-700 whitespace-nowrap cursor-pointer"><input type="checkbox" x-model="p.temColo" class="w-5 h-5 sm:w-4 sm:h-4 rounded"> 👶 Bebê de colo</label>
              </div>
              <label class="shrink-0 sm:pb-1 text-[13px] sm:text-[12px] font-semibold flex items-center gap-1 cursor-pointer whitespace-nowrap" style="color:var(--cor1)" title="Anexar documentos">📎 Anexos<input type="file" accept="image/*,application/pdf,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv" multiple class="hidden" @change="anexarDocs(i,$event)"></label>
              </div>
            </div>

            {{-- bebê de colo: carrega logo após a nacionalidade --}}
            <div x-show="p.temColo" x-cloak class="mt-1.5 rounded-xl border border-sky-200 bg-sky-50 p-2 space-y-1.5">
              <div class="text-[12px] font-bold text-sky-700">👶 Bebê de colo (grátis · não ocupa poltrona · não é passageiro separado)</div>
              <input class="field !min-h-0 py-1 text-sm w-full" x-model="p.bebeColo.nome" placeholder="Nome do bebê">
              <div class="grid grid-cols-2 gap-2">
                <input class="field !min-h-0 py-1 text-sm w-full" x-model="p.bebeColo.documento" placeholder="Documento (opcional)">
                <input class="field !min-h-0 py-1 text-sm w-full" inputmode="numeric" maxlength="10" x-model="p.bebeColo.nasc_br" @input="mascaraNascColo(p)" placeholder="Nascimento dd/mm/aaaa">
              </div>
            </div>

            {{-- anexos (previews) --}}
            <div x-show="p.documentos && p.documentos.length" x-cloak class="flex flex-wrap gap-1.5 mt-1.5">
              <template x-for="(d,j) in p.documentos" :key="j">
                <div class="relative inline-flex items-center gap-1.5 rounded-lg border bg-white pl-1 pr-2 py-1">
                  <template x-if="ehImgDoc(d)"><img :src="docSrc(d)" class="h-10 w-10 object-cover rounded-md cursor-pointer" @click="abrirDoc(d)" title="ver"></template>
                  <template x-if="!ehImgDoc(d)"><span class="h-10 w-10 rounded-md bg-slate-100 flex items-center justify-center text-lg cursor-pointer" @click="abrirDoc(d)" x-text="iconeDoc(d)"></span></template>
                  <span class="text-[11px] font-semibold text-slate-600 truncate max-w-[84px]" x-text="docNome(d)" :title="docNome(d)"></span>
                  <button type="button" @click="removerDoc(i,j)" class="bg-red-500 text-white rounded-full w-4 h-4 text-[11px] leading-none flex items-center justify-center shrink-0">×</button>
                </div>
              </template>
            </div>
          </div>
        </template>
        <div x-show="!passageiros.length" class="text-center py-10 text-slate-400">
          <div class="text-3xl mb-1">🎫</div><div class="text-sm">Nenhum passageiro. Toque em “＋ passageiro”.</div>
        </div>
      </div>
      <input class="field" placeholder="Observação (opcional)" x-model="observacao">
      <div class="mt-2 rounded-xl border border-slate-200 p-2.5">
        <div class="flex items-center justify-between gap-2">
          <div class="text-[12px] font-bold" style="color:var(--cor1)">🗂️ Documento geral (da passagem)</div>
          <label class="text-[12px] font-semibold flex items-center gap-1 cursor-pointer shrink-0" style="color:var(--cor1)">📎 Anexar<input type="file" accept="image/*,application/pdf,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv" multiple class="hidden" @change="anexarGerais($event)"></label>
        </div>
        <p class="text-[11px] text-slate-400 mt-0.5">Foto, PDF ou arquivo que vale pra viagem toda (lista, autorização, manifesto...).</p>
        <div x-show="documentosGerais.length" x-cloak class="flex flex-wrap gap-1.5 mt-1.5">
          <template x-for="(d,j) in documentosGerais" :key="j">
            <div class="relative inline-flex items-center gap-1.5 rounded-lg border bg-white pl-1 pr-2 py-1">
              <template x-if="ehImgDoc(d)"><img :src="docSrc(d)" class="h-10 w-10 object-cover rounded-md cursor-pointer" @click="abrirDoc(d)" title="ver"></template>
              <template x-if="!ehImgDoc(d)"><span class="h-10 w-10 rounded-md bg-slate-100 flex items-center justify-center text-lg cursor-pointer" @click="abrirDoc(d)" x-text="iconeDoc(d)"></span></template>
              <span class="text-[11px] font-semibold text-slate-600 truncate max-w-[90px]" x-text="docNome(d)" :title="docNome(d)"></span>
              <button type="button" @click="documentosGerais.splice(j,1)" class="bg-red-500 text-white rounded-full w-4 h-4 text-[11px] leading-none flex items-center justify-center shrink-0">×</button>
            </div>
          </template>
        </div>
      </div>
    </div>
  </section>

  {{-- COLUNA 3: pagamento --}}
  <div class="lg:col-span-3 space-y-2">
    <section class="sec shrink-0">
      <div class="sec-h"><span class="n">4</span><span class="ic">💳</span><span class="t">Pagamento</span></div>
      <div class="sec-b">
        {{-- TIPO de pagamento (1 linha) --}}
        <div class="grid grid-cols-4 gap-1" x-ref="fForma">
          <button type="button" class="forma !py-1.5 !text-[12px]" :class="tipoPag==='balcao' && 'on'" @click="setTipo('balcao')">🏬 Balcão</button>
          @if(empty($soAvista) && ($agentes ?? collect())->count())
          <button type="button" class="forma !py-1.5 !text-[12px]" :class="tipoPag==='agente' && 'on'" @click="setTipo('agente')">🤝 Agente</button>
          @else
          <button type="button" class="forma !py-1.5 !text-[12px] opacity-30 pointer-events-none">🤝 Agente</button>
          @endif
          @if(empty($soAvista) && in_array('faturado', $formasPermitidas ?? []))
          <button type="button" class="forma !py-1.5 !text-[12px]" :class="tipoPag==='faturado' && 'on'" @click="setTipo('faturado')">🧾 Faturado</button>
          @else
          <button type="button" class="forma !py-1.5 !text-[12px] opacity-30 pointer-events-none">🧾 Faturado</button>
          @endif
          @if(empty($soAvista) && in_array('online', $formasPermitidas ?? []))
          <button type="button" class="forma !py-1.5 !text-[12px]" :class="tipoPag==='online' && 'on'" @click="setTipo('online')">🌐 Online</button>
          @else
          <button type="button" class="forma !py-1.5 !text-[12px] opacity-30 pointer-events-none">🌐 Online</button>
          @endif
        </div>

        {{-- AGENTE --}}
        @if(($agentes ?? collect())->count())
        <div x-show="tipoPag==='agente'" x-cloak class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-1.5 space-y-1">
          <select x-model="agenteId" class="field w-full"><option value="">— escolha o agente —</option>@foreach($agentes as $ag)<option value="{{ $ag->id }}">{{ $ag->name }}</option>@endforeach</select>
          <p class="text-[10px] text-indigo-500 px-0.5">Agente externo: sempre <b>sem acerto</b> — o proprietário acerta direto com o agente.</p>
        </div>
        @endif

        {{-- BALCÃO: maquineta --}}
        <div x-show="tipoPag==='balcao'" class="flex items-center gap-1">
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 shrink-0">Maquin.</span>
          <button type="button" class="forma flex-1 !py-1 !text-[11px]" :class="!proprietarioMaq && 'on'" @click="proprietarioMaq=false">🏢 Empresa</button>
          <button type="button" class="forma flex-1 !py-1 !text-[11px]" :class="proprietarioMaq && 'on'" @click="proprietarioMaq=true">🚤 Proprietário</button>
        </div>

        {{-- MÉTODOS --}}
        <div x-show="tipoPag!=='faturado'">
          <div class="flex items-center justify-between mb-0.5">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Forma</span>
            <button type="button" @click="toggleDividir()" class="text-[11px] font-bold px-1.5 py-0.5 rounded-lg border" :class="dividir ? 'border-[var(--cor1)] text-[var(--cor1)] bg-[color-mix(in_srgb,var(--cor1)_10%,white)]' : 'border-slate-200 text-slate-500'" x-text="dividir ? '✓ Dividindo' : '🔀 Dividir'"></button>
          </div>
          <div class="grid grid-cols-5 gap-1">
            <template x-for="m in metodosChips()" :key="m.k">
              <button type="button" class="forma !py-1.5 !px-0.5 !text-[10.5px] leading-tight" :class="metodoOn(m.k) && 'on'" @click="toggleMetodo(m.k)" x-text="m.l"></button>
            </template>
          </div>

          {{-- valores por forma (só no modo dividir) --}}
          <div x-show="dividir" x-cloak class="mt-1 space-y-1">
            <template x-for="k in metodosAtivos" :key="k">
              <div class="flex items-center gap-1">
                <span class="text-[11px] font-semibold text-slate-600 w-20 shrink-0 truncate" x-text="rotuloMetodo(k)"></span>
                <input class="field text-right font-semibold flex-1 !min-h-[32px] !py-1" x-money="pag[k]">
                <button type="button" @click="preencherRestante(k)" title="Lançar o que falta" class="shrink-0 rounded-lg border-2 w-9 h-8 flex items-center justify-center font-extrabold" style="border-color:var(--cor1);color:var(--cor1)">=</button>
              </div>
            </template>
            <div class="flex justify-between text-[11px] font-bold px-0.5" :class="devido>0.009 ? 'text-amber-600' : 'text-emerald-600'">
              <span x-text="devido>0.009 ? 'Falta lançar' : 'Fechado ✓'"></span>
              <span x-show="devido>0.009" x-text="FBRE.moeda(devido)"></span>
            </div>
          </div>

          {{-- parcelas (crédito parcelado) --}}
          <div x-show="metodoOn('credito_parcelado')" x-cloak class="flex items-center gap-2 mt-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 shrink-0">Parcelas</span>
            <select x-model.number="parcelas" class="field w-auto font-semibold !min-h-[32px] !py-1"><template x-for="n in 12" :key="n"><option :value="n" x-text="n+'x'"></option></template></select>
          </div>

          {{-- PIX QR --}}
          <div x-show="metodoOn('pix') && (pixDinamico || pixEstatico)" x-cloak class="mt-1">
            <button type="button" @click="pixEstatico ? gerarPixEstatico() : gerarPixDinamico()" class="w-full rounded-lg border-2 py-1 text-[11px] font-bold" style="border-color:var(--cor1);color:var(--cor1)">💠 Gerar QR PIX</button>
          </div>

          @if(auth()->user()?->podeDesconto())
          <div class="flex items-center gap-2 mt-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 shrink-0">Desconto</span>
            <input class="field text-right flex-1 !min-h-[32px] !py-1" x-money="desconto">
          </div>
          @endif
        </div>

        @if(!empty($soAvista))
        <p class="text-[11px] text-slate-500">Revendedor: venda à vista. O acerto é entre você e a empresa.</p>
        @endif

        <div x-show="forma==='faturado'" x-cloak class="rounded-xl border border-amber-200 bg-amber-50/70 p-2 space-y-2">
          <div class="text-[13px] font-semibold text-amber-800">🧾 Cliente faturado (a receber)</div>
          <template x-if="fatSel">
            <div class="flex items-center justify-between gap-2 rounded-lg bg-white border border-amber-200 px-2 py-1.5">
              <div class="min-w-0">
                <div class="font-semibold text-sm truncate" x-text="fatSel.nome + (fatSel.empresa ? ' · '+fatSel.empresa : '')"></div>
                <div class="text-[11px] text-slate-500" x-text="((fatSel.doc_tipo||'')+' '+(fatSel.documento||'')).trim() || 'sem documento'"></div>
              </div>
              <button type="button" class="text-rose-600 text-[12px] font-semibold shrink-0" @click="fatLimpar()">trocar</button>
            </div>
          </template>
          <div x-show="!fatSel" class="space-y-2">
            <input type="text" x-model="fatBusca" @input.debounce.300ms="fatBuscar()" placeholder="Buscar cliente (nome, empresa ou documento)" class="field w-full">
            <template x-if="fatResultados.length">
              <div class="rounded-lg border border-slate-200 bg-white divide-y max-h-40 overflow-auto">
                <template x-for="c in fatResultados" :key="c.id">
                  <button type="button" class="block w-full text-left px-2 py-1.5 hover:bg-amber-50 text-sm" @click="fatSelecionar(c)">
                    <span class="font-semibold" x-text="c.nome"></span><span class="text-slate-500" x-text="c.empresa ? ' · '+c.empresa : ''"></span>
                    <span class="text-[11px] text-slate-400 block" x-text="((c.doc_tipo||'')+' '+(c.documento||'')).trim()"></span>
                  </button>
                </template>
              </div>
            </template>
            <div class="rounded-lg border border-dashed border-amber-300 p-2 space-y-1.5">
              <div class="text-[11px] font-semibold text-amber-700 uppercase">Novo cliente</div>
              <input type="text" x-model="fatNome" placeholder="Nome / responsável" class="field w-full">
              <input type="text" x-model="fatEmpresa" placeholder="Empresa (razão social) — opcional" class="field w-full">
              <div class="flex items-center gap-2">
                <input type="text" x-model="fatDoc" @input="fatDocTipoCalc()" inputmode="numeric" placeholder="CPF ou CNPJ" class="field flex-1">
                <span class="text-[11px] font-bold px-2 py-1 rounded shrink-0" :class="fatDocTipo==='CNPJ'?'bg-indigo-100 text-indigo-700':(fatDocTipo==='CPF'?'bg-emerald-100 text-emerald-700':'bg-slate-100 text-slate-400')" x-text="fatDocTipo || '—'"></span>
              </div>
              <button type="button" class="w-full rounded-lg bg-amber-600 text-white py-2 text-sm font-bold disabled:opacity-40" :disabled="!fatNome.trim()" @click="fatCriar()">+ Cadastrar e usar</button>
            </div>
          </div>
          <div class="flex items-center justify-between gap-2 rounded-lg bg-white border border-amber-200 px-2 py-1.5">
            <label class="text-[12px] font-semibold text-amber-800">⏰ Lembrar de cobrar em</label>
            <div class="flex items-center gap-1.5">
              <input type="number" x-model.number="cobrarDias" min="1" max="365" class="field !w-16 text-center" title="Dias até lembrar a cobrança (padrão 30)">
              <span class="text-[12px] text-amber-700">dias</span>
            </div>
          </div>
          <p class="text-[11px] text-amber-700/80">Faturado: nada é recebido agora. Fica a receber no nome do cliente acima.</p>
        </div>

        <div class="rounded-xl p-2 text-white" style="background: linear-gradient(160deg, var(--cor1), color-mix(in srgb, var(--cor1) 75%, black))">
          <div class="flex justify-between text-[10px] opacity-70 leading-tight"><span x-text="'Tarifa '+FBRE.moeda(subPassagem)+'  ·  Alim/Adic '+FBRE.moeda(subAlim+subTaxa)"></span><span x-show="desconto>0" x-text="'Desc - '+FBRE.moeda(desconto)"></span></div>
          <div class="flex justify-between items-center mt-0.5">
            <div class="flex items-baseline gap-1.5"><span class="text-xs font-semibold opacity-90">Total</span><span class="text-xl font-extrabold tracking-tight" x-text="FBRE.moeda(total)"></span></div>
            <div class="flex items-center gap-1.5 text-[11px]">
              <span class="opacity-80" x-text="'Pago '+FBRE.moeda(pago)"></span>
              <span class="rounded-lg px-2 py-0.5 font-bold" :class="devido>0.009 ? 'bg-amber-400 text-slate-900' : 'bg-emerald-400 text-slate-900'" x-text="devido>0.009 ? ('Falta '+FBRE.moeda(devido)) : 'Pago ✓'"></span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="sec shrink-0">
      <div class="p-2.5 space-y-2">
        @unless(auth()->user()->ehRevendedor())
        <div class="flex gap-x-3 text-[13px] text-slate-600" x-show="!edMode">
          <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" value="direto" x-model="imprimir" class="accent-[var(--cor1)]"> Imprimir</label>
          <label class="flex items-center gap-1.5 cursor-pointer"><input type="radio" value="nao" x-model="imprimir" class="accent-[var(--cor1)]"> Não imprimir</label>
        </div>
        @endunless

        <div class="hidden lg:block">
          <button x-show="total>0 || forma!=='avista' || abas.length>1" class="w-full rounded-xl py-3 text-base font-extrabold text-slate-900 shadow-lg transition hover:brightness-105 active:scale-[.98] disabled:opacity-40" style="background:var(--cor2)" :disabled="salvando || (!passageiros.length && abas.length<=1)">
            <span x-show="!salvando" x-text="edMode ? (edFila>0?'✏️ Atualizar e ir pra próxima':'✏️ Atualizar passagem') : (abas.length>1 ? ('✅ Finalizar '+abas.length+' vendas') : '💾 Salvar passagem')"></span><span x-show="salvando">Salvando…</span>
          </button>
        </div>
        <p class="text-[11px] text-slate-400 text-center" x-show="!passageiros.length">Adicione ao menos um passageiro.</p>
      </div>
    </section>
  </div>
</div>

{{-- MAPA DE POLTRONAS --}}
<style>
  /* Poltrona com cara de poltrona: encosto (topo) + braços (laterais) */
  .poltrona{position:relative;flex:none;width:34px;height:37px;border-radius:10px 10px 5px 5px;padding:0;line-height:1;display:flex;align-items:center;justify-content:center}
  .poltrona::before{content:"";position:absolute;top:3px;left:4px;right:4px;height:6px;border-radius:5px;background:currentColor;opacity:.15;pointer-events:none}
  .poltrona::after{content:"";position:absolute;bottom:4px;left:-3px;right:-3px;height:14px;border-left:4px solid currentColor;border-right:4px solid currentColor;border-radius:4px;opacity:.22;pointer-events:none}
  .poltrona.text-white{background:var(--cor1);border-color:var(--cor1)}
  .poltrona-larga{width:auto;padding:0 7px}
  .poltrona-fila{display:grid;justify-content:center;column-gap:16px;min-height:37px}
</style>
<div x-show="mapaAberto" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-3" @click="mapaAberto=false">
  <div class="bg-white rounded-2xl p-4 w-full max-w-md max-h-[85vh] overflow-auto" @click.stop>
    <div class="flex items-center justify-between mb-3">
      <div class="font-bold">Escolha a poltrona</div><button type="button" class="text-xl px-2" @click="mapaAberto=false">×</button>
    </div>
    <div class="flex gap-3 text-xs mb-2">
      <span class="chip bg-slate-100 text-slate-600">livre</span>
      <span class="chip bg-red-100 text-red-700">ocupada</span>
      <span class="chip bg-amber-100 text-amber-700">reservando agora</span>
      <span class="chip" style="background:color-mix(in srgb,var(--cor1) 15%,white);color:var(--cor1)">selecionada</span>
    </div>
    <div class="flex flex-wrap items-center gap-1.5 text-xs mb-2" x-show="temLayout" x-cloak>
      <span class="chip bg-emerald-100 text-emerald-700 font-bold" x-text="'✅ Livres: '+contPoltronas.livre+' de '+contPoltronas.total"></span>
      <span class="chip bg-slate-100 text-slate-600" x-show="contPoltronas.janela>0" x-text="'🪟 janela '+contPoltronas.janela"></span>
      <span class="chip bg-slate-100 text-slate-600" x-show="contPoltronas.corredor>0" x-text="'🚶 corredor '+contPoltronas.corredor"></span>
      <span class="chip bg-slate-100 text-slate-600" x-show="contPoltronas.meio>0" x-text="'↔️ meio '+contPoltronas.meio"></span>
    </div>
    <div class="text-[11px] text-amber-700 mb-2 leading-snug" x-show="listaReservas.length" x-cloak>
      🟡 <span class="font-semibold">Comprando agora:</span>
      <template x-for="(r,ri) in listaReservas" :key="ri"><span x-text="r.pol+' — '+r.nome+(ri<listaReservas.length-1?' · ':'')"></span></template>
    </div>
    <template x-if="temLayout">
      <div class="mx-auto" style="max-width:360px">
        <div class="text-center text-[11px] font-bold text-slate-400 mb-1">⬆ PROA (frente)</div>
        <div class="rounded-t-[44px] rounded-b-2xl border-2 px-2 py-3" style="border-color:var(--cor1);background:linear-gradient(180deg,color-mix(in srgb,var(--cor1) 10%,white),#eef2f7)">
          <template x-for="(bloco,bi) in blocosPoltrona" :key="bi">
            <div :class="bi>0 ? 'mt-2.5 pt-2.5 border-t border-dashed border-slate-300 space-y-2' : 'space-y-2'">
              <template x-for="(f,ri) in bloco.rows" :key="ri">
                <div class="poltrona-fila" :style="polGrid(bloco)">
                  <template x-for="ci in bloco.colMax.length" :key="ci">
                    <div class="flex gap-1 whitespace-nowrap" :class="(ci>1 && ci===bloco.colMax.length) ? 'justify-end' : ''">
                      <template x-for="n in (f.cols[ci-1]||[])" :key="n">
                        <button type="button" class="poltrona border-2 text-[11px] font-bold" :class="[assentoClasse(n), String(n).length>4 ? 'poltrona-larga' : '']" :disabled="(estaOcupada(n)||estaReservada(n)) && !poltronaMinha(n)" @click="escolherPoltrona(n)" :title="tituloAssento(n)" x-text="n"></button>
                      </template>
                    </div>
                  </template>
                </div>
              </template>
            </div>
          </template>
        </div>
        <div class="text-center text-[11px] font-bold text-slate-400 mt-1">POPA (fundo) ⬇</div>
      </div>
    </template>
    <div x-show="!temLayout" class="mx-auto" style="max-width:260px">
      <div class="text-center text-[11px] font-bold text-slate-400 mb-1">⬆ PROA (frente)</div>
      <div class="rounded-t-[36px] rounded-b-2xl border-2 border-slate-300 bg-slate-50 px-2 py-3 space-y-1.5">
        <template x-for="(f,ri) in fileiras" :key="ri">
          <div class="flex items-center justify-center gap-1">
            <div class="flex gap-1.5"><template x-for="n in f.esq" :key="n">
              <button type="button" class="w-9 h-10 rounded-t-xl rounded-b-md border-2 text-xs font-bold" :class="assentoClasse(n)" :disabled="(estaOcupada(n)||estaReservada(n)) && !poltronaMinha(n)" @click="escolherPoltrona(n)" :title="tituloAssento(n)" x-text="n"></button>
            </template></div>
            <div class="w-6 text-center text-[9px] text-slate-300 select-none">║</div>
            <div class="flex gap-1.5"><template x-for="n in f.dir" :key="n">
              <button type="button" class="w-9 h-10 rounded-t-xl rounded-b-md border-2 text-xs font-bold" :class="assentoClasse(n)" :disabled="(estaOcupada(n)||estaReservada(n)) && !poltronaMinha(n)" @click="escolherPoltrona(n)" :title="tituloAssento(n)" x-text="n"></button>
            </template></div>
          </div>
        </template>
      </div>
      <div class="text-center text-[11px] font-bold text-slate-400 mt-1">POPA (fundo) ⬇</div>
    </div>
  </div>
</div>
  {{-- MODAL WEBCAM / ARQUIVO (desktop) --}}
  <div x-show="camAberta" x-cloak class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4" @click.self="fecharCam()">
    <div class="bg-white rounded-2xl p-4 w-full max-w-md space-y-3">
      <div class="font-extrabold text-lg" style="color:var(--cor1)">📷 Foto do documento</div>
      <div class="rounded-xl overflow-hidden bg-black aspect-[4/3] flex items-center justify-center">
        <video id="cam-video" playsinline autoplay muted class="w-full h-full object-cover"></video>
      </div>
      <div class="text-sm text-center min-h-[20px]" :class="camLendo?'text-slate-500':'text-amber-700'" x-text="camStatus"></div>
      <div class="grid grid-cols-2 gap-2">
        <button type="button" @click="trocarCamera()" class="rounded-xl border-2 font-bold py-2.5 text-sm" style="border-color:var(--cor1);color:var(--cor1)">🔄 Virar câmera</button>
        <label class="rounded-xl border-2 font-bold py-2.5 text-sm text-center cursor-pointer" style="border-color:var(--cor1);color:var(--cor1)">📁 Selecionar arquivo
          <input type="file" accept="image/*" class="hidden" @change="selecionarArquivo($event)"></label>
      </div>
      <button type="button" @click="capturarCam()" :disabled="camLendo" class="w-full rounded-xl text-white font-extrabold py-3 text-lg disabled:opacity-50" style="background:var(--cor2)" x-text="camLendo?'Lendo...':'📸 Tirar foto e ler'"></button>
      <button type="button" @click="fecharCam()" class="w-full text-slate-500 text-sm py-1">Fechar</button>
    </div>
  </div>

  {{-- MODAL CAPTURA POR FOTO --}}
  <div x-show="capturaAberta" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="fecharCaptura()">
    <div class="bg-white rounded-2xl p-5 w-full max-w-sm text-center space-y-3">
      <div class="font-extrabold text-lg" style="color:var(--cor1)">📷 Captura por foto</div>
      <p class="text-sm text-slate-500">Neste aparelho: toque em <b>“Abrir”</b>. Em outro: aponte a câmera pro QR. Tire a foto do documento — o passageiro é preenchido aqui sozinho.</p>
      <a :href="capturaUrl" x-show="capturaUrl" x-cloak target="_blank" rel="noopener" class="block w-full rounded-xl text-white font-extrabold py-3 text-base active:scale-[.98] transition" style="background:var(--cor2)">📷 Abrir</a>
      <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">ou escaneie o QR de outro aparelho</div>
      <div id="qr-captura" class="inline-block p-3 bg-white rounded-xl border border-slate-200 mx-auto"></div>
      <div class="text-sm font-semibold" style="color:var(--cor1)" x-text="capturaStatus"></div>
      <a :href="capturaUrl" x-show="capturaUrl" x-cloak target="_blank" rel="noopener" class="block text-[11px] text-slate-400 break-all underline" x-text="capturaUrl"></a>
      <button type="button" @click="fecharCaptura()" class="w-full rounded-xl border-2 font-bold py-2.5" style="border-color:var(--cor1);color:var(--cor1)">Fechar</button>
    </div>
  </div>
  <div x-show="pixModal" x-cloak class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl p-5 max-w-sm w-full text-center">
      <div class="font-bold text-lg">💠 PIX dinâmico</div>
      <div class="text-xs text-slate-500 mb-3">Mostre o QR pro cliente pagar. A tela confirma sozinha e só então cadastra.</div>
      <template x-if="pixQr">
        <img :src="pixQr.startsWith('data:')?pixQr:('data:image/png;base64,'+pixQr)" class="mx-auto rounded" style="width:220px;height:220px">
      </template>
      <template x-if="!pixQr">
        <div class="mx-auto flex items-center justify-center text-slate-300" style="width:220px;height:220px">
          <svg class="animate-spin w-10 h-10" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
        </div>
      </template>
      <div class="font-extrabold text-xl my-2">R$ <span x-text="fmtPix(pixValor)"></span></div>
      <div class="flex items-center justify-center gap-2 text-amber-600 text-sm font-semibold mb-3">
        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
        <span x-text="pixQr ? 'Aguardando pagamento…' : 'Gerando PIX…'"></span>
      </div>
      <button type="button" x-show="pixCobId" @click="imprimirPix(pixCobId)" class="btn btn-ghost btn-sm w-full mb-2">🖨 Imprimir QR</button>
      <button type="button" x-show="pixQr" @click="copiarPix()" class="btn btn-ghost btn-sm w-full mb-2">📋 Copiar código PIX</button>
      <button type="button" @click="cancelarPix()" class="btn btn-ghost btn-sm w-full text-slate-500">Cancelar</button>
    </div>
  </div>

  <div x-show="pixEstModal" x-cloak class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4" @click="pixEstModal=false">
    <div class="bg-white rounded-2xl p-5 max-w-sm w-full text-center" @click.stop>
      <div class="font-bold text-lg">💠 PIX — meu QR</div>
      <div class="text-xs text-slate-500 mb-3">Mostre pro cliente pagar. O valor cai direto na sua chave. Confira o recebimento no seu banco.</div>
      <div id="qr-pix-est" class="inline-block p-2 bg-white rounded-xl border mx-auto"></div>
      <div class="font-extrabold text-xl my-2">R$ <span x-text="fmtPix(pixValor)"></span></div>
      <button type="button" @click="copiarPixEst()" class="btn btn-ghost btn-sm w-full mb-2">📋 Copiar código PIX</button>
      <button type="button" @click="pixEstModal=false" class="btn btn-ghost btn-sm w-full text-slate-500">Fechar</button>
    </div>
  </div>

  {{-- BARRA FIXA (celular): resumo + confirmar, sempre visível --}}
  <div class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur border-t border-slate-200 px-4 pt-2.5 shadow-[0_-10px_30px_-12px_rgba(15,23,42,.3)]" style="padding-bottom:max(0.75rem,env(safe-area-inset-bottom))">
    <div class="flex items-end justify-between mb-2">
      <div>
        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 leading-none">Valor final</div>
        <div class="text-[26px] font-extrabold tracking-tight leading-tight" style="color:var(--cor1)" x-text="FBRE.moeda(total)"></div>
      </div>
      <div class="text-right pb-1">
        <template x-if="devido>0.009">
          <div><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Devido</span>
            <div class="text-sm font-extrabold text-amber-600 leading-none" x-text="FBRE.moeda(devido)"></div></div>
        </template>
        <template x-if="total>0 && devido<=0.009">
          <span class="text-[12px] font-bold rounded-lg px-2 py-1 bg-emerald-100 text-emerald-700">Pago ✓</span>
        </template>
        <div class="text-[11px] text-slate-400 font-semibold" x-text="passageiros.length+(passageiros.length===1?' passageiro':' passageiros')"></div>
      </div>
    </div>
    <button type="submit" x-show="total>0 || forma!=='avista' || abas.length>1" class="w-full rounded-2xl py-3.5 text-base font-extrabold text-white shadow-lg transition active:scale-[.98] disabled:opacity-40" style="background:var(--cor2)" :disabled="salvando || (!passageiros.length && abas.length<=1)">
      <span x-show="!salvando" x-text="edMode ? (edFila>0?'✏️ Atualizar e próxima':'✏️ Atualizar venda') : (abas.length>1 ? ('✅ Finalizar '+abas.length+' vendas') : '✅ Confirmar venda')"></span><span x-show="salvando">Salvando…</span>
    </button>
    <p class="text-[11px] text-slate-400 text-center mt-1" x-show="!passageiros.length">Adicione ao menos um passageiro.</p>
  </div>
</form>
