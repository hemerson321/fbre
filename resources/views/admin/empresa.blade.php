@extends('layouts.app')
@section('titulo','Minha empresa')
@section('conteudo')
<form method="post" enctype="multipart/form-data" class="max-w-2xl mx-auto card p-5 space-y-3">@csrf
<h1 class="text-xl font-bold">Dados da empresa</h1>
<p class="text-xs text-slate-500">Aparecem no topo do sistema e nos comprovantes/etiquetas.</p>
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
<button class="btn btn-accent w-full">Salvar</button>
</form>
@endsection
