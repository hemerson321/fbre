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
</div>

<div class="border-t pt-3 mt-1" x-data="{ts:{{ $empresa->config('usa_taxa_embarque', false) ? 'true':'false' }}, al:{{ $empresa->config('usa_alimentacao', true) ? 'true':'false' }}}">
  <h2 class="font-bold text-sm text-slate-500 uppercase mb-1">Componentes da passagem (opcional)</h2>
  <p class="text-xs text-slate-500 mb-2">Ligue só o que você cobra. Ficam separados da tarifa no cadastro e no comprovante.</p>
  <label class="flex items-center gap-2 py-1.5 cursor-pointer"><input type="checkbox" name="usa_taxa_embarque" value="1" x-model="ts" class="w-5 h-5 rounded"> <span class="font-semibold">Cobrar taxa de embarque</span></label>
  <div x-show="ts" x-cloak class="pl-7 pb-2"><label class="label !text-xs">Nome que aparece</label><input name="label_taxa_embarque" class="input !min-h-[38px]" value="{{ $empresa->config('label_taxa_embarque') ?: 'Taxa de embarque' }}" maxlength="40"><p class="text-xs text-slate-400 mt-1">Defina os valores em <b>Cadastros gerais → Taxa de embarque</b>.</p></div>
  <label class="flex items-center gap-2 py-1.5 cursor-pointer"><input type="checkbox" name="usa_alimentacao" value="1" x-model="al" class="w-5 h-5 rounded"> <span class="font-semibold">Cobrar alimentação</span></label>
  <div x-show="al" x-cloak class="pl-7 pb-1"><label class="label !text-xs">Nome que aparece</label><input name="label_alimentacao" class="input !min-h-[38px]" value="{{ $empresa->config('label_alimentacao') ?: 'Alimentação' }}" maxlength="40"></div>
  <p class="text-xs text-slate-400 mt-1">Depois é só definir os valores de cada trecho em <b>Cadastros gerais → rotas</b>.</p>
</div>

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
      <input name="ia_modelo" class="input" value="{{ $empresa->config('ia_modelo') }}" :placeholder="prov==='openai' ? 'gpt-4o-mini' : 'claude-sonnet-4-20250514'"></div>
    <div class="sm:col-span-2"><label class="label">Token da API</label>
      <input name="ia_token" class="input" value="{{ $empresa->config('ia_token') }}" :placeholder="prov==='openai' ? 'sk-...' : 'sk-ant-...'" autocomplete="off"></div>
  </div>
</div>

<button class="btn btn-accent w-full">Salvar</button>
</form>
@endsection
