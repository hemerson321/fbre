@extends('layouts.app')
@section('titulo','Minha empresa')
@section('conteudo')
<form method="post" enctype="multipart/form-data" class="max-w-2xl mx-auto card p-5 space-y-3">@csrf
<h1 class="text-xl font-bold">Dados da empresa</h1>
<p class="text-xs text-slate-500">Aparecem no topo do sistema, na tela de login e nos comprovantes/etiquetas.</p>
<div class="rounded-lg bg-slate-50 border border-slate-200 p-3 text-sm">Endereço de login da sua equipe: <a href="{{ url('/'.$empresa->slug) }}" target="_blank" class="font-mono font-semibold text-[var(--cor2)]">{{ url('/'.$empresa->slug) }}</a></div>
<div class="grid sm:grid-cols-2 gap-3">
  <div class="sm:col-span-2"><label class="label">Razão social</label><input name="nome" class="input" required value="{{ old('nome',$empresa->nome) }}"></div>
  <div><label class="label">Nome fantasia (aparece nas impressões)</label><input name="nome_fantasia" class="input" value="{{ old('nome_fantasia',$empresa->nome_fantasia) }}"></div>
  <div><label class="label">CNPJ</label><input name="cnpj" class="input" value="{{ old('cnpj',$empresa->cnpj) }}"></div>
  <div><label class="label">Telefone</label><input name="telefone" class="input" value="{{ old('telefone',$empresa->telefone) }}"></div>
  <div><label class="label">WhatsApp</label><input name="whatsapp" class="input" value="{{ old('whatsapp',$empresa->whatsapp) }}"></div>
  <div class="sm:col-span-2"><label class="label">Endereço</label><input name="endereco" class="input" value="{{ old('endereco',$empresa->endereco) }}"></div>
  <div><label class="label">Cidade</label><input name="cidade" class="input" value="{{ old('cidade',$empresa->cidade) }}"></div>
  <div><label class="label">UF</label><input name="uf" class="input" maxlength="2" value="{{ old('uf',$empresa->uf) }}"></div>
  <div><label class="label">🕐 Fuso-horário (cidade onde opera)</label>
    @php $fz = $empresa->config('fuso','America/Manaus'); $opts = ['America/Manaus'=>'Manaus / AM, RR (GMT-4)','America/Boa_Vista'=>'Boa Vista / RR (GMT-4)','America/Porto_Velho'=>'Porto Velho / RO (GMT-4)','America/Cuiaba'=>'Cuiabá / MT (GMT-4)','America/Rio_Branco'=>'Rio Branco / AC (GMT-5)','America/Belem'=>'Belém / PA, AP (GMT-3)','America/Sao_Paulo'=>'Brasília / SP, DF (GMT-3)','America/Fortaleza'=>'Fortaleza / CE (GMT-3)','America/Recife'=>'Recife / PE (GMT-3)']; @endphp
    <select name="fuso" class="input">
      @foreach($opts as $k=>$v)<option value="{{ $k }}" @selected($fz===$k)>{{ $v }}</option>@endforeach
    </select>
    <p class="text-[11px] text-slate-400 mt-0.5">Usado pra decidir se a lancha já saiu (devolução). Cada empresa/cidade usa o seu.</p>
  </div>
  <div class="sm:col-span-2"><label class="label">Chave PIX (impressa no comprovante)</label><input name="pix" class="input" value="{{ old('pix',$empresa->pix) }}"></div>
  <div class="sm:col-span-2"><label class="label">Rodapé do comprovante</label><textarea name="rodape_comprovante" rows="2" class="input !min-h-[60px] py-2">{{ old('rodape_comprovante',$empresa->rodape_comprovante) }}</textarea></div>
  <div><label class="label">Cor principal</label><input name="cor_primaria" type="color" class="input !p-1 h-12" value="{{ old('cor_primaria',$empresa->cor_primaria) }}"></div>
  <div><label class="label">Cor de destaque</label><input name="cor_secundaria" type="color" class="input !p-1 h-12" value="{{ old('cor_secundaria',$empresa->cor_secundaria) }}"></div>
  <div class="sm:col-span-2 flex items-center gap-4"><div>@if($empresa->logo)<img src="/{{ $empresa->logo }}" class="h-16 rounded-lg border">@endif</div><div class="flex-1"><label class="label">Logo (PNG/JPG)</label><input name="logo" type="file" accept="image/*" class="input !py-2.5"></div></div>
</div>
<div class="border-t pt-3 mt-1">
  <h2 class="font-bold text-sm text-slate-500 uppercase mb-1">Aparência</h2>
  <label class="flex items-start gap-2 py-1 cursor-pointer">
    <input type="checkbox" name="cores_por_modulo" value="1" @checked($empresa->config('cores_por_modulo', true)) class="w-5 h-5 rounded mt-0.5">
    <span><span class="font-semibold text-sm">Cada módulo com sua cor</span><br><span class="text-xs text-slate-500">Ligado: o topo e o menu mudam de cor por módulo (encomenda azul, passagem verde, frete laranja...). Desligado: tudo na cor da empresa.</span></span>
  </label>

  <div class="mt-3">
    <div class="font-semibold text-sm">Cor de cada módulo</div>
    <p class="text-xs text-slate-500 mb-2">Escolha a cor de cada módulo pra combinar com a sua logo. Quais módulos a empresa usa é definido no contrato (fale com o suporte da GERATEC).</p>
    @php $modAtivos = $empresa->config('modulos_ativos'); $todosMods = empty($modAtivos); @endphp
    <div class="grid sm:grid-cols-2 gap-2">
      @foreach(\App\Support\Modulos::LISTA as $mk => $info)
        @continue(! $todosMods && ! in_array($mk, (array) $modAtivos, true))
        @php [$rotulo,$icone,$tw,$hex] = $info; $corAtual = $empresa->config('cores_modulo.'.$mk) ?: $hex; @endphp
        <label class="flex items-center gap-2 border rounded-lg px-2 py-1.5">
          <span class="text-base">{{ $icone }}</span>
          <span class="flex-1 text-sm font-semibold truncate">{{ $rotulo }}</span>
          <input type="color" name="cores_modulo[{{ $mk }}]" value="{{ $corAtual }}" class="w-9 h-8 rounded border p-0.5 shrink-0" title="Cor do módulo {{ $rotulo }}">
        </label>
      @endforeach
    </div>
  </div>
</div>

<div class="border-t pt-3 mt-1" x-data="{ts:{{ $empresa->config('usa_taxa_embarque', false) ? 'true':'false' }}, al:{{ $empresa->config('usa_alimentacao', true) ? 'true':'false' }}}">
  <h2 class="font-bold text-sm text-slate-500 uppercase mb-1">Componentes da passagem (opcional)</h2>
  <p class="text-xs text-slate-500 mb-2">Ligue só o que você cobra. Ficam separados da tarifa no cadastro e no comprovante.</p>
  <label class="flex items-center gap-2 py-1.5 cursor-pointer"><input type="checkbox" name="comprador_passagem" value="1" @checked($empresa->config('comprador_passagem', true)) class="w-5 h-5 rounded"> <span class="font-semibold">Mostrar “Comprador” na venda (quem está comprando)</span></label>
  <div class="border-t pt-2 mt-2" x-data="{cc:{{ $empresa->config('usa_cancel_transporte', false) ? 'true':'false' }}}">
    <label class="flex items-center gap-2 py-1.5 cursor-pointer"><input type="checkbox" name="usa_cancel_transporte" value="1" x-model="cc" class="w-5 h-5 rounded"> <span class="font-semibold">Política de cancelamento por transporte</span></label>
    <div x-show="cc" x-cloak class="grid grid-cols-2 gap-2 pl-7">
      <div><label class="label !text-xs">Retém % se cancelar ANTES da viagem</label><input name="cancel_antes_pct" type="number" min="0" max="100" value="{{ $empresa->config('cancel_antes_pct', 10) }}" class="input !min-h-[38px]"></div>
      <div><label class="label !text-xs">Retém % se cancelar DEPOIS da viagem</label><input name="cancel_depois_pct" type="number" min="0" max="100" value="{{ $empresa->config('cancel_depois_pct', 30) }}" class="input !min-h-[38px]"></div>
    </div>
    <p x-show="cc" x-cloak class="text-[11px] text-slate-400 pl-7 mt-0.5">Padrão da empresa. Cada transporte pode ter % próprias no cadastro de embarcações. Aparece no bilhete.</p>
  </div>
  <label class="flex items-center gap-2 py-1.5 cursor-pointer"><input type="checkbox" name="usa_taxa_embarque" value="1" x-model="ts" class="w-5 h-5 rounded"> <span class="font-semibold">Cobrar taxa de embarque</span></label>
  <div x-show="ts" x-cloak class="pl-7 pb-2"><label class="label !text-xs">Nome que aparece</label><input name="label_taxa_embarque" class="input !min-h-[38px]" value="{{ $empresa->config('label_taxa_embarque') ?: 'Taxa de embarque' }}" maxlength="40"><p class="text-xs text-slate-400 mt-1">Defina os valores em <b>Cadastros gerais → Taxa de embarque</b>.</p></div>
  <label class="flex items-center gap-2 py-1.5 cursor-pointer"><input type="checkbox" name="usa_alimentacao" value="1" x-model="al" class="w-5 h-5 rounded"> <span class="font-semibold">Cobrar alimentação</span></label>
  <div x-show="al" x-cloak class="pl-7 pb-1"><label class="label !text-xs">Nome que aparece</label><input name="label_alimentacao" class="input !min-h-[38px]" value="{{ $empresa->config('label_alimentacao') ?: 'Alimentação' }}" maxlength="40"></div>
  <div class="border-t pt-2 mt-2">
    <label class="flex items-center gap-2 py-1.5 cursor-pointer"><input type="checkbox" name="usa_tarifa_transporte" value="1" @checked($empresa->config('usa_tarifa_transporte', true)) class="w-5 h-5 rounded"> <span class="font-semibold">Cobrar com tarifa de transporte</span></label>
    <label class="flex items-center gap-2 py-1.5 cursor-pointer"><input type="checkbox" name="usa_desconto_tabela" value="1" @checked($empresa->config('usa_desconto_tabela', true)) class="w-5 h-5 rounded"> <span class="font-semibold">Usar preço de tabela com desconto</span></label>
    <p class="text-[11px] text-slate-400 pl-7">Se desmarcados, esses campos não aparecem ao cadastrar os preços das rotas.</p>
  </div>
  <p class="text-xs text-slate-400 mt-1">Depois é só definir os valores de cada trecho em <b>Cadastros gerais → rotas</b>.</p>
</div>

@php $tpIdade = ($tiposPassageiro ?? collect())->map(fn($t)=>['id'=>$t->id,'nome'=>$t->nome,'eh_normal'=>(bool)$t->eh_normal,'min'=>$t->idade_min,'max'=>$t->idade_max])->values(); @endphp
<div class="border-t pt-3 mt-1" x-data='regraIdadeEmpresa(@json($tpIdade))'>
  <h2 class="font-bold text-sm text-slate-500 uppercase mb-1">👶 Classificação por idade</h2>
  <p class="text-xs text-slate-500 mb-2">Na venda, quando o operador digita a <b>data de nascimento</b>, o sistema calcula a idade e já marca o tipo certo (adulto, criança, idoso...). Defina de quantos a quantos <b>anos</b> cada tipo vale. Deixe <b>em branco</b> os tipos que não dependem de idade (ex.: <b>PCD</b>) — esses continuam escolhidos na mão.</p>
  <div class="space-y-1.5">
    <div class="grid grid-cols-12 gap-2 text-[10px] font-bold text-slate-400 uppercase px-0.5">
      <div class="col-span-6">Tipo</div><div class="col-span-3 text-center">De (anos)</div><div class="col-span-3 text-center">Até (anos)</div>
    </div>
    <template x-for="t in tipos" :key="t.id">
      <div class="grid grid-cols-12 gap-2 items-center">
        <div class="col-span-6 text-sm font-semibold truncate" x-text="t.nome"></div>
        <div class="col-span-3"><input type="number" min="0" max="120" inputmode="numeric" class="input !min-h-[38px] text-center" placeholder="—" x-model="t.min" :name="`faixa[${t.id}][min]`"></div>
        <div class="col-span-3"><input type="number" min="0" max="120" inputmode="numeric" class="input !min-h-[38px] text-center" placeholder="—" x-model="t.max" :name="`faixa[${t.id}][max]`"></div>
      </div>
    </template>
  </div>
  <div class="flex flex-wrap items-center gap-2 mt-2">
    <button type="button" @click="sugerir()" class="text-xs font-semibold px-3 py-1.5 rounded-lg border" style="border-color:var(--cor2);color:var(--cor2)">✨ Usar sugestão</button>
    <button type="button" @click="limpar()" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-300 text-slate-500">Limpar tudo</button>
  </div>
  <div class="mt-3 pt-3 border-t">
    <label class="label !text-xs">🎟️ Gratuidades por viagem (padrão)</label>
    <div class="flex items-center gap-2">
      <input type="number" min="0" max="999" name="gratuito_qtd" value="{{ $empresa->config('gratuito_qtd', 2) }}" class="input !min-h-[38px] !w-24 text-center">
      <span class="text-[11px] text-slate-400">Máx. de passageiros <b>gratuitos</b> (ex.: idoso gratuito) por viagem. Marque quais tipos contam como gratuito em <b>Cadastros gerais → Tipos de passageiro</b>. Cada transporte pode ter a sua própria cota.</span>
    </div>
  </div>
  <div class="mt-2">
    <label class="label !text-xs">♿ PCD por viagem (padrão)</label>
    <div class="flex items-center gap-2">
      <input type="number" min="0" max="999" name="pcd_qtd" value="{{ $empresa->config('pcd_qtd', 2) }}" class="input !min-h-[38px] !w-24 text-center">
      <span class="text-[11px] text-slate-400">Máx. de passageiros <b>PCD</b> por viagem. Marque quais tipos contam como PCD em <b>Cadastros gerais → Tipos de passageiro</b>. Cada transporte pode ter a sua própria cota.</span>
    </div>
  </div>
  <p class="text-[11px] text-slate-400 mt-1.5">Sugestão: bebê de colo <b>0 a 1</b>, criança <b>2 a 10</b>, adulto <b>11 a 59</b>, idoso <b>60</b> em diante (deixe o "até" em branco). Esta é a regra <b>padrão</b> — cada transporte pode ter a sua própria no cadastro de transportes.</p>
</div>
@push('scripts')
<script>
function regraIdadeEmpresa(tipos){ return {
  tipos:(tipos||[]).map(t=>({id:t.id,nome:t.nome,eh_normal:!!t.eh_normal,min:(t.min===null||t.min===undefined)?'':t.min,max:(t.max===null||t.max===undefined)?'':t.max})),
  sugerir(){ this.tipos.forEach(t=>{ const n=(t.nome||'').toLowerCase();
    if(/beb|colo/.test(n)){ t.min=0; t.max=1; }
    else if(/crian/.test(n)){ t.min=2; t.max=10; }
    else if(/idoso|melhor idade/.test(n)){ t.min=60; t.max=''; }
    else if(/pcd|defici|especial|cadeir/.test(n)){ t.min=''; t.max=''; }
    else if(t.eh_normal||/adulto|inteir|normal/.test(n)){ t.min=11; t.max=59; }
  }); },
  limpar(){ this.tipos.forEach(t=>{ t.min=''; t.max=''; }); },
}; }
</script>
@endpush

<div class="border-t pt-3 mt-1">
  <h2 class="font-bold text-sm text-slate-500 uppercase mb-1">WhatsApp por serviço</h2>
  <p class="text-xs text-slate-500 mb-2">Escolha qual conexão de WhatsApp cada serviço usa. Cadastre as conexões no menu <b>WhatsApp</b>.</p>
  @php $conexoesWa = $conexoesWa ?? collect(); @endphp
  @if($conexoesWa->isEmpty())
    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2">Nenhuma conexão cadastrada ainda. Vá em <b>WhatsApp</b> e cadastre uma (oficial ou não oficial).</p>
  @else
    <p class="text-xs text-slate-500 mb-2">Cada módulo pode usar um WhatsApp diferente.</p>
    <div class="grid sm:grid-cols-2 gap-3">
      @foreach(\App\Support\Modulos::LISTA as $mk => [$mrot,$mic,$mcor,$mhex])
        <div><label class="label">{{ $mic }} {{ $mrot }}</label>
          <select name="wa_conexao[{{ $mk }}]" class="input"><option value="">— não usar —</option>
            @foreach($conexoesWa as $c)<option value="{{ $c->id }}" @selected((int)$empresa->config('wa_conexao_'.$mk)===$c->id)>{{ $c->rotulo() }}</option>@endforeach
          </select></div>
      @endforeach
    </div>
  @endif
</div>

<div class="border-t pt-3 mt-1" x-data="{prov:'{{ $empresa->config('ia_provider','claude') }}'}">
  <h2 class="font-bold text-sm text-slate-500 uppercase mb-1">Leitura de documentos (IA)</h2>
  <p class="text-xs text-slate-500 mb-2">Usada na captura por foto do RG (passagem). Cole o token da sua IA.</p>
  <div class="grid sm:grid-cols-2 gap-3">
    <div><label class="label">Provedor</label>
      <select name="ia_provider" x-model="prov" class="input">
        <option value="claude">Claude (Anthropic)</option>
        <option value="openai">OpenAI</option>
      </select></div>
    <div><label class="label">Modelo</label>
      <input name="ia_modelo" class="input" list="ia-modelos" value="{{ $empresa->config('ia_modelo') }}" :placeholder="prov==='openai' ? 'gpt-4o-mini' : 'claude-3-5-sonnet-20241022'" autocomplete="off">
      <datalist id="ia-modelos">
        <template x-if="prov==='openai'">
          <template x-for="m in ['gpt-4o-mini','gpt-4o','gpt-4.1-mini','gpt-4.1']" :key="m"><option :value="m"></option></template>
        </template>
        <template x-if="prov!=='openai'">
          <template x-for="m in ['claude-3-5-sonnet-20241022','claude-3-5-haiku-20241022','claude-3-5-sonnet-latest','claude-sonnet-4-20250514']" :key="m"><option :value="m"></option></template>
        </template>
      </datalist>
      <p class="text-xs text-slate-400 mt-1">Pode digitar outro modelo. Em branco usa o padrão (<span x-text="prov==='openai' ? 'gpt-4o-mini' : 'claude-3-5-sonnet-20241022'"></span>).</p></div>
    <div class="sm:col-span-2"><label class="label">Token da API</label>
      <input name="ia_token" class="input" value="{{ $empresa->config('ia_token') }}" :placeholder="prov==='openai' ? 'sk-...' : 'sk-ant-...'" autocomplete="off"></div>
  </div>
</div>

<div class="border-t pt-3 mt-1">
  <h2 class="font-bold text-sm text-slate-500 uppercase mb-1">Nota fiscal (frete)</h2>
  <label class="label">Api-Key meudanfe</label>
  <input name="meudanfe_token" class="input" value="{{ $empresa->config('meudanfe_token') }}" placeholder="Cole aqui a Api-Key da meudanfe" autocomplete="off">
  <p class="text-xs text-slate-400 mt-1">Com a chave preenchida, o frete busca automaticamente remetente, destinatário e produtos pela chave da NF-e. Cada consulta custa R$ 0,03 na meudanfe. O servidor precisa ter <b>api.meudanfe.com.br</b> liberado.</p>
</div>

@if($empresa->config('usa_revendedores'))
<div class="border-t pt-3 mt-1">
  <h2 class="font-bold text-sm text-slate-500 uppercase mb-1">Revendedores / Afiliados</h2>
  <p class="text-xs text-slate-500">Recurso <b>liberado</b> para esta empresa{{ $empresa->config('revendedor_multi') ? ' (com multiagente/filiados)' : '' }}. Para liberar ou bloquear, fale com o suporte da GERATEC.</p>
</div>
@endif

<button class="btn btn-accent w-full">Salvar</button>
</form>
@endsection
