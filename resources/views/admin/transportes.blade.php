@extends('layouts.app')
@section('titulo','Transportes')
@section('conteudo')
<div class="max-w-3xl mx-auto space-y-4">
  <div>
    <h1 class="text-xl font-bold">Transportes / embarcações</h1>
    <p class="text-xs text-slate-500">Cadastre os barcos/lanchas: poltronas, dono, comissão da empresa, política de cancelamento e rastreio.</p>
  </div>
  {{-- TRANSPORTES / EMBARCAÇÕES com config de poltrona --}}
  <div class="card p-4 space-y-2 md:col-span-2">
    <h2 class="font-bold text-sm text-slate-500 uppercase">Transportes / embarcações</h2>
    <form method="post" action="{{ route('admin.cadastros.store','embarcacoes') }}" class="flex gap-2">@csrf
      <input name="nome" class="input !min-h-[42px]" placeholder="Nome do barco/lancha" required>
      <button class="btn btn-accent btn-sm">+ Adicionar</button>
    </form>
    <div class="space-y-2">
      @forelse($embarcacoes as $e)
        <form method="post" action="{{ route('admin.embarcacoes.salvar',$e) }}" enctype="multipart/form-data" class="rounded-xl border border-slate-200 p-3 {{ $e->ativo?'':'opacity-50' }}" x-data="{ pol: {{ $e->usa_poltrona?'true':'false' }}, qtd: {{ $e->poltronas_qtd ?: 0 }}, colunas: {{ $e->poltronas_colunas ?: 4 }}, avancado: {{ trim((string)($e->poltronas_lista ?? '')) !== '' ? 'true':'false' }}, layout: {{ \Illuminate\Support\Js::from($e->poltronas_lista ?? '') }}, nomes: [], init(){ if(String(this.layout).trim()){ this.nomes=String(this.layout).split(/[\s|]+/).map(s=>s.trim()).filter(s=>s!==''); this.qtd=this.nomes.length; } else { this.sincronizar(); } }, sincronizar(){ const n=Math.max(0,Math.min(500,parseInt(this.qtd)||0)); const nv=[]; for(let i=0;i<n;i++){ nv.push((this.nomes[i]!==undefined&&String(this.nomes[i]).trim()!=='')?this.nomes[i]:String(i+1)); } this.nomes=nv; }, get esq(){ return Math.ceil(this.colunas/2); }, montar(){ if(this.avancado&&String(this.layout).trim()) return this.layout; let L=[]; for(let i=0;i<this.nomes.length;i+=this.colunas){ const f=this.nomes.slice(i,i+this.colunas); const e=f.slice(0,this.esq).join(' '); const d=f.slice(this.esq).join(' '); L.push(d?(e+' | '+d):e); } return L.join('\n'); } }">@csrf
          <div class="flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[160px]"><label class="label">Nome</label><input name="nome" class="input !min-h-[40px]" value="{{ $e->nome }}" required></div>
            <label class="flex items-center gap-2 text-sm font-medium pb-2 cursor-pointer">
              <input type="checkbox" name="usa_poltrona" value="1" class="w-5 h-5 rounded" x-model="pol" @checked($e->usa_poltrona)> 🪑 Usa poltrona
            </label>
            <div x-show="pol" x-cloak><label class="label">Qtd poltronas</label><input name="poltronas_qtd" type="number" min="0" max="500" x-model.number="qtd" x-on:input.debounce.250ms="sincronizar()" class="input !min-h-[40px] !w-28 text-center"></div>
            <div x-show="pol" x-cloak><label class="label">Assentos/fileira</label>
              <select name="poltronas_colunas" x-model.number="colunas" class="input !min-h-[40px] !w-32">
                @foreach([2=>'2 (1+1)',3=>'3 (2+1)',4=>'4 (2+2)',5=>'5 (3+2)',6=>'6 (3+3)'] as $cn=>$cl)<option value="{{ $cn }}" @selected(($e->poltronas_colunas ?: 4)==$cn)>{{ $cl }}</option>@endforeach
              </select>
            </div>
            <div x-show="pol && nomes.length>0" x-cloak class="w-full">
              <label class="label">Nome de cada poltrona <span class="text-slate-400 font-normal" x-text="'('+nomes.length+')'"></span></label>
              <div class="grid gap-1 mt-1" :style="`grid-template-columns:repeat(${colunas},minmax(0,1fr));max-width:${colunas*74}px`">
                <template x-for="(n,idx) in nomes" :key="idx">
                  <input x-model="nomes[idx]" :placeholder="idx+1" class="input !min-h-[36px] text-center text-xs px-1" style="font-family:ui-monospace,monospace" autocapitalize="characters">
                </template>
              </div>
              <p class="text-[11px] text-slate-400 mt-1">Ja vem com o numero de cada poltrona &mdash; troque so onde o nome e diferente (ex: <b>01J</b>, <b>28C</b>). O corredor fica no meio da fileira.</p>
              <details class="mt-1">
                <summary class="text-[11px] text-slate-500 cursor-pointer select-none">&#9881; Layout avancado (areas, sofa, corredor manual)</summary>
                <label class="flex items-center gap-2 text-[11px] mt-1 cursor-pointer"><input type="checkbox" x-model="avancado" class="w-4 h-4"> Usar o layout digitado abaixo (ignora as caixinhas)</label>
                <textarea x-model="layout" rows="4" class="input !min-h-[90px] text-xs mt-1" style="font-family:ui-monospace,monospace" placeholder="02J 02C | 01C 01J&#10;04J 04M 04C | 03C 03M 03J&#10;&#10;A B C | D E F"></textarea>
                <p class="text-[11px] text-slate-400 mt-0.5">Uma fileira por linha; espaco separa poltronas; <b>|</b> e o corredor; linha em branco separa blocos.</p>
              </details>
              <input type="hidden" name="poltronas_lista" :value="pol ? montar() : ''">
            </div>
            <button class="btn btn-primary btn-sm">Salvar</button>
            <a href="#" onclick="event.preventDefault(); if(confirm('Remover embarcação?')) this.closest('form').nextElementSibling.submit();" class="text-red-500 text-xs pb-2">remover</a>
          </div>
          <div class="mt-2">
            <label class="label">URL de rastreio do barco (opcional)</label>
            <input name="rastreio_url" class="input !min-h-[40px] font-mono text-xs" value="{{ $e->rastreio_url }}" placeholder="https://rionave.com/wp-json/riotag/v1/posicao/TOKEN" autocapitalize="none" inputmode="url">
            <label class="label mt-2">🖼️ Logo do transporte (opcional)</label>
            <div class="flex items-center gap-3">
              @if($e->logo)<img src="/{{ $e->logo }}" class="h-12 w-12 rounded-lg object-cover bg-white border shrink-0">@endif
              <input type="file" name="logo" accept="image/*" class="input !min-h-[40px] text-xs">
            </div>
            <p class="text-[11px] text-slate-400 mt-0.5">Aparece junto com a logo da empresa na prestação de contas e nos comprovantes. Sem logo, mostra só a da empresa.</p>
            <div class="grid grid-cols-2 gap-2 mt-2">
              <div><label class="label !text-xs">Cancel. antes da viagem %</label><input name="cancel_antes_pct" type="number" min="0" max="100" value="{{ $e->cancel_antes_pct ?? \App\Support\Tenant::empresa()?->config('cancel_antes_pct',10) }}" class="input !min-h-[40px]"></div>
              <div><label class="label !text-xs">Cancel. depois da viagem %</label><input name="cancel_depois_pct" type="number" min="0" max="100" value="{{ $e->cancel_depois_pct ?? \App\Support\Tenant::empresa()?->config('cancel_depois_pct',30) }}" class="input !min-h-[40px]"></div>
            </div>
            <div class="grid grid-cols-2 gap-2 mt-2">
              <div><label class="label !text-xs">Dono do transporte (nome)</label>
                <input name="dono_nome" class="input !min-h-[40px]" value="{{ $e->dono_nome }}" placeholder="nome do dono da lancha" maxlength="120"></div>
              <div><label class="label !text-xs">Comissão da empresa %</label><input name="comissao_empresa_pct" type="number" min="0" max="100" value="{{ $e->comissao_empresa_pct }}" class="input !min-h-[40px]" placeholder="0"></div>
            </div>
            <p class="text-[11px] text-slate-400">A empresa fica com essa % de cada passagem deste transporte; o dono recebe o resto (acerto em Acertos).</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Cole o link da API de posição deste barco. Com ele, o sistema avisa o destinatário no WhatsApp ~1h antes da encomenda chegar na cidade de destino.</p>
            <div x-data="{ open:false, trip: {{ \Illuminate\Support\Js::from($e->tripulantes ?? []) }} }" class="mt-2">
              <button type="button" @click="open=true" class="btn btn-ghost btn-sm">🚢 Ficha / tripulação da embarcação</button>
              <div x-show="open" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-start justify-center p-3 overflow-auto">
                <div @click.stop class="bg-white rounded-2xl p-5 max-w-3xl w-full my-6 space-y-3">
                  <div class="flex items-center justify-between"><h3 class="font-bold text-lg">🚢 Ficha da embarcação <span class="text-slate-400 font-normal text-sm">(opcional)</span></h3><button type="button" @click="open=false" class="text-slate-400 text-2xl leading-none">&times;</button></div>
                  <div class="grid sm:grid-cols-3 gap-2">
                    <div><label class="label !text-xs">CNPJ</label><input name="ficha[cnpj]" value="{{ $e->ficha['cnpj'] ?? '' }}" class="input !min-h-[38px]"></div>
                    <div><label class="label !text-xs">Nome oficial</label><input name="ficha[nome_oficial]" value="{{ $e->ficha['nome_oficial'] ?? '' }}" class="input !min-h-[38px]"></div>
                    <div><label class="label !text-xs">Tipo</label><input name="ficha[tipo]" value="{{ $e->ficha['tipo'] ?? '' }}" class="input !min-h-[38px]"></div>
                    <div><label class="label !text-xs">Bandeira</label><input name="ficha[bandeira]" value="{{ $e->ficha['bandeira'] ?? '' }}" class="input !min-h-[38px]"></div>
                    <div><label class="label !text-xs">Qtd vagas</label><input name="ficha[qtd_vagas]" value="{{ $e->ficha['qtd_vagas'] ?? '' }}" class="input !min-h-[38px]"></div>
                    <div><label class="label !text-xs">Valor Peso/KG</label><input name="ficha[valor_peso_kg]" value="{{ $e->ficha['valor_peso_kg'] ?? '' }}" class="input !min-h-[38px]"></div>
                    <div><label class="label !text-xs">Inscrição marítima</label><input name="ficha[inscricao_maritima]" value="{{ $e->ficha['inscricao_maritima'] ?? '' }}" class="input !min-h-[38px]"></div>
                    <div><label class="label !text-xs">Arqueação</label><input name="ficha[arqueacao]" value="{{ $e->ficha['arqueacao'] ?? '' }}" class="input !min-h-[38px]"></div>
                    <div><label class="label !text-xs">Tonelagem bruta</label><input name="ficha[tonelagem_bruta]" value="{{ $e->ficha['tonelagem_bruta'] ?? '' }}" class="input !min-h-[38px]"></div>
                    <div class="sm:col-span-3"><label class="label !text-xs">Inf. bilhete criança</label><input name="ficha[inf_bilhete_crianca]" value="{{ $e->ficha['inf_bilhete_crianca'] ?? '' }}" class="input !min-h-[38px]" placeholder="ex.: Criança 2 a 10 anos paga 70% do valor"></div>
                    <div><label class="label !text-xs">Representante</label><input name="ficha[nome_representante]" value="{{ $e->ficha['nome_representante'] ?? '' }}" class="input !min-h-[38px]"></div>
                    <div><label class="label !text-xs">CPF do representante</label><input name="ficha[cpf]" value="{{ $e->ficha['cpf'] ?? '' }}" class="input !min-h-[38px]"></div>
                    <div><label class="label !text-xs">Telefone</label><input name="ficha[telefone]" value="{{ $e->ficha['telefone'] ?? '' }}" class="input !min-h-[38px]"></div>
                    <div class="sm:col-span-2"><label class="label !text-xs">Endereço</label><input name="ficha[endereco]" value="{{ $e->ficha['endereco'] ?? '' }}" class="input !min-h-[38px]"></div>
                    <div><label class="label !text-xs">E-mail</label><input name="ficha[email]" value="{{ $e->ficha['email'] ?? '' }}" class="input !min-h-[38px]"></div>
                  </div>
                  <div class="border-t pt-2">
                    <div class="flex items-center justify-between mb-1"><span class="text-xs font-bold text-slate-600 uppercase">🧭 Tripulação (se necessário)</span><button type="button" class="btn btn-ghost btn-sm" @click="trip.push({nome:'',funcao:'',nascimento:'',cir:'',validade_cir:''})">＋ tripulante</button></div>
                    <div class="space-y-1">
                      <template x-for="(t,i) in trip" :key="i">
                        <div class="grid grid-cols-12 gap-1 items-center">
                          <input :name="`tripulantes[${i}][nome]`" x-model="t.nome" placeholder="Nome" class="input !min-h-[36px] col-span-4 text-sm">
                          <input :name="`tripulantes[${i}][funcao]`" x-model="t.funcao" placeholder="Função" class="input !min-h-[36px] col-span-2 text-sm">
                          <input :name="`tripulantes[${i}][nascimento]`" type="date" x-model="t.nascimento" class="input !min-h-[36px] col-span-2 text-xs" title="Nascimento">
                          <input :name="`tripulantes[${i}][cir]`" x-model="t.cir" placeholder="CIR" class="input !min-h-[36px] col-span-2 text-sm">
                          <input :name="`tripulantes[${i}][validade_cir]`" type="date" x-model="t.validade_cir" class="input !min-h-[36px] col-span-1 text-xs" title="Validade CIR">
                          <button type="button" @click="trip.splice(i,1)" class="col-span-1 text-red-500 text-lg">×</button>
                        </div>
                      </template>
                      <p x-show="!trip.length" class="text-[11px] text-slate-400">Nenhum tripulante. Clique em ＋ tripulante pra adicionar (Nome, Função, Nascimento, CIR, Validade).</p>
                    </div>
                  </div>
                  <div class="flex justify-end gap-2 border-t pt-2">
                    <button type="button" @click="open=false" class="btn btn-ghost btn-sm">Fechar sem salvar</button>
                    <button type="submit" class="btn btn-primary btn-sm">Salvar embarcação</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </form>
        <form method="post" action="{{ route('admin.cadastros.destroy',['embarcacoes',$e->id]) }}" class="hidden">@csrf @method('DELETE')</form>
      @empty <div class="text-slate-400 text-sm">Nenhuma embarcação.</div> @endforelse
    </div>
  </div>
</div>
@endsection
