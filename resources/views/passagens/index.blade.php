@extends('layouts.app')
@section('titulo','Passagens')
@php $__e=\App\Support\Tenant::empresa(); $cor = (($__e?->config('cores_por_modulo', true) ?? true) ? \App\Support\Modulos::hex('passagem') : ($__e?->cor_primaria ?? '#0b1a33')); @endphp
@section('conteudo')
<div class="space-y-4 max-w-5xl mx-auto">
  <div class="rounded-2xl px-4 py-3 text-white flex items-center gap-3" style="background:{{ $cor }}">
    <span class="text-2xl">🧾</span><div class="font-bold">Passagens / embarque</div>
    @can('cadastra', null)@endcan
    <div class="relative ml-auto" x-data="{o:false}">
      <button type="button" @click="o=!o" class="btn btn-sm bg-white/20 text-white">📋 Listas (PDF) ▾</button>
      <div x-show="o" x-cloak @click.outside="o=false" class="absolute right-0 mt-1 w-64 bg-white rounded-xl shadow-xl border z-30 overflow-hidden text-slate-700">
        <div class="px-3 py-1.5 text-[10px] uppercase tracking-wide text-slate-400 bg-slate-50 border-b">Filtre por transporte e viagem</div>
        <a href="{{ route('passagens.manifesto', array_merge(request()->query(), ['tipo'=>'trecho','print'=>1])) }}" target="_blank" class="block px-3 py-2 text-sm hover:bg-slate-50 border-b">🧾 Por trecho <div class="text-[11px] text-slate-400">RG · nascimento · nacionalidade</div></a>
        <a href="{{ route('passagens.manifesto', array_merge(request()->query(), ['tipo'=>'poltrona','print'=>1])) }}" target="_blank" class="block px-3 py-2 text-sm hover:bg-slate-50 border-b">💺 Por poltrona <div class="text-[11px] text-slate-400">mapa de assentos · checkin</div></a>
        <a href="{{ route('passagens.manifesto', array_merge(request()->query(), ['tipo'=>'federal','print'=>1])) }}" target="_blank" class="block px-3 py-2 text-sm hover:bg-slate-50 border-b">🛂 Órgão federal <div class="text-[11px] text-slate-400">nome · nascimento · sexo (bilíngue)</div></a>
        <a href="{{ route('passagens.manifesto', array_merge(request()->query(), ['print'=>1])) }}" target="_blank" class="block px-3 py-2 text-sm hover:bg-slate-50">📋 Lista geral</a>
        <a href="{{ route('passagens.caixa-viagem', request()->query()) }}" target="_blank" class="block px-3 py-2 text-sm hover:bg-slate-50 border-t bg-emerald-50">💰 Caixa por viagem <div class="text-[11px] text-slate-500">vendedor · valor · forma · totais do caixa</div></a>
      </div>
    </div>
    <a href="{{ route('passagens.create') }}" class="btn btn-sm bg-white/20 text-white">+ Nova</a>
  </div>

  {{-- filtro --}}
  <form method="get" class="card p-3 grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
    <input name="busca" value="{{ $f['busca'] ?? '' }}" placeholder="Nome, telefone ou nº" class="input col-span-2">
    <input type="date" name="inicio" value="{{ $f['inicio'] ?? '' }}" class="input">
    <input type="date" name="fim" value="{{ $f['fim'] ?? '' }}" class="input">
    <select name="status_pagamento" class="input">
      <option value="">Pagamento: todos</option>
      <option value="pago" @selected(($f['status_pagamento'] ?? '')==='pago')>Pago</option>
      <option value="parcial" @selected(($f['status_pagamento'] ?? '')==='parcial')>Parcial</option>
      <option value="pendente" @selected(($f['status_pagamento'] ?? '')==='pendente')>Não pago</option>
    </select>
    <select name="forma" class="input">
      <option value="">Forma: todas</option>
      @foreach(\App\Models\Passagem::FORMAS as $k=>$l)
      <option value="{{ $k }}" @selected(($f['forma'] ?? '')===$k)>{{ $l }}</option>
      @endforeach
    </select>
    @if(($clientesFaturado ?? collect())->count())
    <select name="cliente_faturado_id" class="input" onchange="this.form.submit()">
      <option value="">Faturado: todos</option>
      @foreach($clientesFaturado as $cf)<option value="{{ $cf->id }}" @selected((string)($f['cliente_faturado_id'] ?? '')===(string)$cf->id)>🧾 {{ $cf->nome }}{{ $cf->empresa ? ' · '.$cf->empresa : '' }}</option>@endforeach
    </select>
    @endif
    <select name="embarcou" class="input">
      <option value="">Embarque: todos</option>
      <option value="1" @selected(($f['embarcou'] ?? '')==='1')>Embarcou</option>
      <option value="0" @selected(($f['embarcou'] ?? '')==='0')>Não embarcou</option>
    </select>
    <select name="embarcacao_id" class="input" onchange="this.form.submit()">
      <option value="">Transporte: todos</option>
      @foreach($embarcacoes as $e)<option value="{{ $e->id }}" @selected((string)($f['embarcacao_id'] ?? '')===(string)$e->id)>🚤 {{ $e->nome }}</option>@endforeach
    </select>
    @if(($datasViagem ?? collect())->count())
    <select name="data_viagem" class="input" onchange="this.form.submit()">
      <option value="">Viagem: todas as datas</option>
      @foreach($datasViagem as $dv)@php $dvs=\Illuminate\Support\Carbon::parse($dv)->toDateString(); @endphp<option value="{{ $dvs }}" @selected((string)($f['data_viagem'] ?? '')===$dvs)>📅 {{ \Illuminate\Support\Carbon::parse($dv)->format('d/m/Y') }}</option>@endforeach
    </select>
    @endif
    @if($verTodos)
    <select name="escopo" class="input">
      <option value="todos" @selected(($escopo ?? 'todos')==='todos')>Ver: todos</option>
      <option value="meu" @selected(($escopo ?? '')==='meu')>Ver: só os meus</option>
    </select>
    @endif
    <button class="btn text-white col-span-2 sm:col-span-1" style="background:{{ $cor }}">Filtrar</button>
  </form>

  {{-- totais --}}
  @php $verCanc = ($f['cancelada'] ?? '')==='1'; @endphp
  <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
    <div class="card p-3"><div class="text-xs text-slate-500">Vendas</div><div class="text-lg font-bold">{{ $totais['qtd'] }}</div></div>
    <div class="card p-3"><div class="text-xs text-slate-500">Passageiros</div><div class="text-lg font-bold">{{ $totais['passageiros'] }}</div></div>
    <div class="card p-3"><div class="text-xs text-slate-500">Recebido</div><div class="text-lg font-bold text-emerald-600">R$ {{ number_format($totais['recebido'],2,',','.') }}</div></div>
    <div class="card p-3"><div class="text-xs text-slate-500">A receber</div><div class="text-lg font-bold text-red-600">R$ {{ number_format($totais['a_receber'],2,',','.') }}</div></div>
    <a href="{{ request()->fullUrlWithQuery(['cancelada'=> $verCanc ? null : '1']) }}" class="card p-3 transition hover:bg-slate-50 {{ $verCanc ? 'ring-2 ring-slate-400 bg-slate-50' : '' }}">
      <div class="text-xs text-slate-500">❌ Cancelados {{ $verCanc ? '· vendo' : '' }}</div>
      <div class="text-lg font-bold text-slate-500">{{ $totais['cancelados'] }}</div>
      <div class="text-[11px] text-slate-400">R$ {{ number_format($totais['cancelados_valor'] ?? 0,2,',','.') }}</div>
    </a>
  </div>
  @if($verCanc)
  <div class="text-xs text-amber-600 -mt-2">Mostrando só as passagens <b>canceladas</b> do período. <a href="{{ request()->fullUrlWithQuery(['cancelada'=>null]) }}" class="underline">voltar pras ativas</a></div>
  @endif

  @php $podeEditar = auth()->user()->podeEditarPassagem(); $podeExcluir = auth()->user()->podeExcluirPassagem(); @endphp
  {{-- lista --}}
  <div class="card overflow-hidden"@if($podeEditar) x-data="editarMassa()"@endif>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-[11px] uppercase text-slate-500"><tr>
        @if($podeEditar)<th class="p-2 w-8"></th>@endif
        <th class="p-2 text-left">Ticket</th>
        <th class="p-2 text-left">Trecho</th>
        <th class="p-2 text-left">Transporte</th>
        <th class="p-2 text-left">Passageiros</th>
        <th class="p-2 text-left">Vendedor</th>
        <th class="p-2 text-right">Valor</th>
        <th class="p-2 text-left">Status</th>
        <th class="p-2 text-center">Ações</th>
      </tr></thead>
      <tbody class="divide-y">
      @forelse($passagens as $p)
        @php
          $pagLista = $p->passageiros->filter(fn($x)=>($x->papel ?? '')!=='colo');
          $nCanc = $pagLista->filter(fn($x)=>$x->cancelado)->count();
          $paxResumo = $pagLista->map(function($x){ $t = e(trim((string)$x->nome)).($x->poltrona ? ' · '.e($x->poltrona) : ''); return $x->cancelado ? '<span class="line-through text-rose-500">'.$t.' ✖</span>' : $t; })->filter()->implode('  |  ');
          $corP = null;
          if ($p->cancelada) $corP='#fee2e2';
          elseif ($p->forma==='faturado') $corP='#dbeafe';
          elseif ($pagLista->contains(fn($x)=> mb_stripos((string)$x->tipo,'pcd')!==false || mb_stripos((string)$x->tipo,'defic')!==false)) $corP='#fef9c3';
          elseif ($pagLista->contains(fn($x)=> mb_stripos((string)$x->tipo,'idoso')!==false)) $corP='#dcfce7';
        @endphp
        <tr class="align-top hover:brightness-95"@if($corP) style="background:{{ $corP }}"@endif>
          @if($podeEditar)<td class="p-2 text-center">@if(!$p->cancelada)<input type="checkbox" @change="toggle({{ $p->id }})" :checked="sel.includes({{ $p->id }})" class="w-5 h-5 rounded cursor-pointer" title="Selecionar p/ editar em massa">@endif</td>@endif
          <td class="p-2 whitespace-nowrap"><a href="{{ route('passagens.show',$p) }}" class="font-semibold text-slate-800 hover:underline">@if($p->tipo==='camarote')🛏️ @endif#{{ $p->numeroFormatado() }}</a></td>
          <td class="p-2 whitespace-nowrap">{{ $p->origem }} → {{ $p->destino }}</td>
          <td class="p-2 whitespace-nowrap text-xs text-slate-500">🚤 {{ $p->embarcacao?->nome ?? '—' }}@if($p->data_viagem)<br>📅 {{ \Illuminate\Support\Carbon::parse($p->data_viagem)->format('d/m/Y') }}@endif</td>
          <td class="p-2 min-w-[180px]">
            @if($paxResumo)<div class="text-slate-700 font-medium leading-snug">👤 {!! $paxResumo !!}@if($nCanc) <span class="inline-block rounded px-1.5 py-0.5 text-[10px] font-bold bg-rose-100 text-rose-700">✖ {{ $nCanc }}</span>@endif</div>@endif
            <div class="text-[11px] text-slate-400">{{ $p->qtd_passageiros }} pax · {{ $p->comprador_nome ?: '—' }}@if($p->embarcou) · <span class="text-emerald-600 font-semibold">✅ check-in</span>@elseif(!$p->cancelada) · <span class="text-amber-600 font-semibold">⏳ sem check-in</span>@endif</div>
          </td>
          <td class="p-2 whitespace-nowrap text-xs">{{ $p->operador?->name }}</td>
          <td class="p-2 text-right whitespace-nowrap font-bold">R$ {{ number_format($p->total,2,',','.') }}</td>
          <td class="p-2 whitespace-nowrap">@if($p->cancelada)<span class="chip font-extrabold" style="background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5">✖ CANCELADA</span>@else<span class="chip st-{{ $p->cor() }}">{{ $p->statusTexto() }}</span>@endif</td>
          <td class="p-2">
            <div class="flex gap-1 justify-center">
              @if($podeEditar && !$p->cancelada)<button type="button" @click="editarUm({{ $p->id }})" class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-[12px] font-semibold text-amber-700 hover:bg-amber-100" title="Editar tudo desta passagem">✏️</button>@endif
              <button type="button" onclick="FBRE.abrirImpressao('{{ route('passagens.comprovante',$p) }}?vias='+FBRE.perguntarVias())" class="rounded-lg border border-slate-200 px-2 py-1 text-[12px] font-semibold text-slate-600 hover:bg-slate-50" title="Imprimir comprovante">🖨</button>
              <button type="button" onclick="FBRE.copiarBilheteImg(this,'{{ route('passagens.comprovante',$p) }}')" class="rounded-lg border border-slate-200 px-2 py-1 text-[12px] font-semibold text-slate-600 hover:bg-slate-50" title="Copiar bilhete como imagem (colar no WhatsApp)">🖼️</button>
              <button type="button" onclick="window.open('{{ route('passagens.comprovante',$p) }}?modo=pdf','_blank')" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-[12px] font-semibold text-emerald-700 hover:bg-emerald-100 whitespace-nowrap" title="Baixar PDF / enviar no WhatsApp">📲 PDF</button>
              @if($podeExcluir)<form method="post" action="{{ route('passagens.excluir',$p) }}" onsubmit="return confirm('Excluir PERMANENTEMENTE a passagem #{{ $p->numeroFormatado() }}? Não dá pra desfazer.')" class="inline">@csrf<button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-[12px] font-semibold text-rose-700 hover:bg-rose-100" title="Excluir permanentemente (apagar de vez)">🗑</button></form>@endif
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="9" class="p-6 text-center text-slate-400">Nenhuma passagem no filtro.</td></tr>
      @endforelse
      </tbody>
    </table>
    </div>
    @if($podeEditar)
    <div x-show="sel.length" x-cloak style="position:fixed;left:0;right:0;bottom:0;z-index:50" class="bg-slate-900 text-white px-4 py-3 flex items-center justify-between gap-3 shadow-2xl">
      <span class="text-sm font-semibold"><span x-text="sel.length"></span> selecionada(s)</span>
      <div class="flex gap-2">
        <button type="button" @click="sel=[]" class="rounded-lg px-3 py-2 text-sm bg-white/10">Limpar</button>
        <button type="button" @click="abrir()" class="rounded-lg px-4 py-2 text-sm font-bold bg-amber-400 text-slate-900">✏️ Editar selecionadas</button>
      </div>
    </div>
    <div x-show="modal" x-cloak style="position:fixed;inset:0;z-index:60;background:rgba(0,0,0,.5)" class="flex items-center justify-center p-4" @click.self="modal=false">
      <div class="bg-white rounded-2xl w-full max-w-md p-5 space-y-3">
        <div class="font-bold text-lg">Editar <span x-text="sel.length"></span> passagem(ns)</div>
        <p class="text-xs text-slate-500">Preencha só o que quer mudar. O resto fica como está.</p>
        <div>
          <label class="text-xs font-semibold text-slate-600">Transporte</label>
          <select x-model="f.embarcacao_id" class="input w-full"><option value="">— manter —</option>@foreach($embarcacoes as $e)<option value="{{ $e->id }}">🚤 {{ $e->nome }}</option>@endforeach</select>
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-600">Data da viagem</label>
          <input type="date" x-model="f.data_viagem" class="input w-full">
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div><label class="text-xs font-semibold text-slate-600">Origem</label><input type="text" x-model="f.origem" placeholder="— manter —" class="input w-full"></div>
          <div><label class="text-xs font-semibold text-slate-600">Destino</label><input type="text" x-model="f.destino" placeholder="— manter —" class="input w-full"></div>
        </div>
        <div>
          <label class="text-xs font-semibold text-slate-600">Forma de pagamento</label>
          <select x-model="f.forma" class="input w-full"><option value="">— manter —</option>@foreach(\App\Models\Passagem::FORMAS as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="modal=false" class="rounded-lg px-4 py-2 text-sm font-semibold border">Cancelar</button>
          <button type="button" @click="aplicar()" class="rounded-lg px-4 py-2 text-sm font-bold bg-slate-900 text-white">Aplicar</button>
        </div>
      </div>
    </div>
    <div id="ed-modal-full" x-show="edModal" x-cloak style="position:fixed;inset:0;z-index:70;background:rgba(0,0,0,.55);overflow-y:auto" class="p-3" @click.self="edModal=false">
      <div class="bg-white rounded-2xl w-full max-w-2xl mx-auto my-4 shadow-2xl">
        <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b">
          <div class="font-bold text-lg">✏️ Editar passagem <span class="text-slate-400" x-text="ed.numero"></span></div>
          <button type="button" @click="edModal=false" class="text-slate-400 text-3xl leading-none hover:text-slate-600">&times;</button>
        </div>
        <div x-show="edLoading" class="py-12 text-center text-slate-400">Carregando…</div>
        <div x-show="!edLoading" class="px-5 py-4 space-y-4">
          <div>
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Viagem</div>
            <div class="grid grid-cols-2 gap-2">
              <div><label class="text-xs font-semibold text-slate-600">Transporte</label>
                <select x-model="ed.embarcacao_id" class="input w-full"><option value="">— nenhum —</option>@foreach($embarcacoes as $e)<option value="{{ $e->id }}">🚤 {{ $e->nome }}</option>@endforeach</select></div>
              <div><label class="text-xs font-semibold text-slate-600">Data da viagem</label>
                <input type="date" x-model="ed.data_viagem" class="input w-full"></div>
              <div><label class="text-xs font-semibold text-slate-600">Origem</label>
                <input type="text" x-model="ed.origem" class="input w-full"></div>
              <div><label class="text-xs font-semibold text-slate-600">Destino</label>
                <input type="text" x-model="ed.destino" class="input w-full"></div>
              <div class="col-span-2"><label class="text-xs font-semibold text-slate-600">Forma de pagamento</label>
                <select x-model="ed.forma" class="input w-full">@foreach(\App\Models\Passagem::FORMAS as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            </div>
          </div>
          <div>
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Comprador</div>
            <div class="grid grid-cols-3 gap-2">
              <div class="col-span-3 sm:col-span-1"><label class="text-xs font-semibold text-slate-600">Nome</label><input type="text" x-model="ed.comprador_nome" class="input w-full"></div>
              <div><label class="text-xs font-semibold text-slate-600">Telefone</label><input type="text" x-model="ed.comprador_telefone" class="input w-full"></div>
              <div><label class="text-xs font-semibold text-slate-600">Documento</label><input type="text" x-model="ed.comprador_documento" class="input w-full"></div>
            </div>
          </div>
          <div>
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Passageiros (<span x-text="ed.passageiros.length"></span>)</div>
            <div class="space-y-2">
              <template x-for="(px,i) in ed.passageiros" :key="px.id">
                <div class="border border-slate-200 rounded-xl p-3 space-y-2">
                  <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                      <span class="text-xs font-bold text-slate-400" x-text="(i+1)+'.'"></span>
                      <select x-show="px.papel!=='colo'" x-model="px.tipo" x-init="$nextTick(()=>{ $el.value=px.tipo; })" class="input !min-h-0 h-8 py-0 text-xs font-semibold" style="width:auto">
                        <template x-for="t in (px._tipos||tiposPax)" :key="t"><option :value="t" x-text="t"></option></template>
                      </select>
                      <span x-show="px.papel==='colo'" class="text-xs font-bold text-slate-500">Bebê de colo</span>
                    </div>
                    <span class="text-[11px] text-slate-400 shrink-0" x-show="px.poltrona" x-text="'Poltrona '+px.poltrona"></span>
                  </div>
                  <input type="text" x-model="px.nome" placeholder="Nome do passageiro" class="input w-full">
                  <div class="grid grid-cols-2 gap-2">
                    <input type="text" x-model="px.documento" placeholder="Documento" class="input w-full">
                    <input type="text" x-model="px.poltrona" placeholder="Poltrona" class="input w-full">
                    <input type="date" x-model="px.nascimento" class="input w-full">
                    <select x-model="px.sexo" class="input w-full"><option value="">Sexo</option><option value="M">Masculino</option><option value="F">Feminino</option></select>
                    <input type="text" x-model="px.telefone" placeholder="Telefone" class="input w-full col-span-2">
                  </div>
                  <div class="grid grid-cols-3 gap-2" x-show="px.papel!=='colo'">
                    <div><label class="text-[11px] font-semibold text-slate-500">Tarifa</label><input type="number" step="0.01" min="0" x-model.number="px.valor_passagem" class="input w-full"></div>
                    <div><label class="text-[11px] font-semibold text-slate-500">Taxa</label><input type="number" step="0.01" min="0" x-model.number="px.valor_taxa" class="input w-full"></div>
                    <div><label class="text-[11px] font-semibold text-slate-500">Alimentação</label><input type="number" step="0.01" min="0" x-model.number="px.valor_alimentacao" class="input w-full"></div>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
        <div x-show="!edLoading" class="flex justify-end gap-2 px-5 py-4 border-t sticky bottom-0 bg-white rounded-b-2xl">
          <button type="button" @click="edModal=false" class="rounded-lg px-4 py-2 text-sm font-semibold border">Cancelar</button>
          <button type="button" @click="salvarUm()" :disabled="edSalvando" class="rounded-lg px-5 py-2 text-sm font-bold bg-slate-900 text-white disabled:opacity-50"><span x-show="!edSalvando">Salvar tudo</span><span x-show="edSalvando">Salvando…</span></button>
        </div>
      </div>
    </div>
    <script>
    function editarMassa(){
      return {
        sel: [], modal: false,
        f: { embarcacao_id:'', data_viagem:'', origem:'', destino:'', forma:'' },
        edModal:false, edLoading:false, edSalvando:false, edId:null,
        ed:{numero:'',embarcacao_id:'',data_viagem:'',origem:'',destino:'',forma:'avista',comprador_nome:'',comprador_telefone:'',comprador_documento:'',observacao:'',passageiros:[]},
        tiposPax: @json(($tiposPax ?? collect())->pluck('nome')->values()),
        toggle(id){ const i=this.sel.indexOf(id); if(i>=0) this.sel.splice(i,1); else this.sel.push(id); },
        abrir(){ if(!this.sel.length) return; if(this.sel.length===1){ this.editarUm(this.sel[0]); return; } this.f={embarcacao_id:'',data_viagem:'',origem:'',destino:'',forma:''}; this.modal=true; },
        async editarUm(id){
          this.edId=id; this.edLoading=true; this.edModal=true;
          try{
            const r=await fetch('/passagens/'+id+'/editar-dados',{headers:{'Accept':'application/json','X-CSRF-TOKEN':FBRE.csrf}});
            const j=await r.json().catch(()=>null);
            if(r.ok && j && j.ok){ this.ed=j; (this.ed.passageiros||[]).forEach(p=>{ p._tipos = (p.tipo && !this.tiposPax.includes(p.tipo)) ? [p.tipo].concat(this.tiposPax) : this.tiposPax.slice(); }); } else { alert((j&&j.erro)||'Nao foi possivel carregar.'); this.edModal=false; }
          }catch(e){ alert('Erro: '+e.message); this.edModal=false; }
          this.edLoading=false;
        },
        async salvarUm(){
          if(!(this.ed.origem||'').trim() || !(this.ed.destino||'').trim()){ alert('Origem e destino sao obrigatorios.'); return; }
          if(this.ed.passageiros.some(p=>!(p.nome||'').trim())){ alert('Todo passageiro precisa de nome.'); return; }
          this.edSalvando=true;
          try{
            const r=await fetch('/passagens/'+this.edId+'/atualizar',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json'},body:JSON.stringify(this.ed)});
            const j=await r.json().catch(()=>null);
            if(r.ok && j && j.ok){ location.reload(); }
            else if(r.status===422 && j && j.errors){ alert('Confira os campos: '+Object.values(j.errors).flat().join(' ')); }
            else { alert((j&&(j.erro||j.message))||'Nao foi possivel salvar.'); }
          }catch(e){ alert('Erro: '+e.message); }
          this.edSalvando=false;
        },
        async aplicar(){
          const body={ids:this.sel};
          ['embarcacao_id','data_viagem','origem','destino','forma'].forEach(k=>{ if(this.f[k]!=='') body[k]=this.f[k]; });
          if(Object.keys(body).length<2){ alert('Escolha pelo menos um campo pra alterar.'); return; }
          try{
            const r=await fetch('{{ route('passagens.editar-massa') }}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':FBRE.csrf,'Accept':'application/json'},body:JSON.stringify(body)});
            const j=await r.json().catch(()=>null);
            if(r.ok && j && j.ok){ location.reload(); } else { alert((j&&j.erro)||'Não foi possível editar.'); }
          }catch(e){ alert('Erro: '+e.message); }
        }
      };
    }
    </script>
    @endif
  </div>
  <div>{{ $passagens->links() }}</div>
</div>
@endsection
