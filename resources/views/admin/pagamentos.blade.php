@extends('layouts.app')
@section('titulo','Pagamentos (APIs)')
@section('conteudo')
<div class="max-w-3xl mx-auto space-y-5">

  <div>
    <h1 class="text-xl font-bold">APIs de pagamento</h1>
    <p class="text-sm text-slate-500">Cadastre o Mercado Pago e escolha qual módulo recebe em qual conta. Com isso cada módulo gera <b>PIX dinâmico</b> e o sistema confirma o pagamento automaticamente.</p>
  </div>

  {{-- APIs cadastradas --}}
  @foreach($apis as $api)
  <div class="card p-4 space-y-3">
    <div class="flex items-center justify-between gap-2">
      <div class="font-bold">{{ $api->rotulo() }} @if(!$api->ativo)<span class="text-xs text-slate-400">(inativa)</span>@endif</div>
      @if($api->provider !== 'pix_estatico')
      <form method="post" action="{{ route('admin.pagamentos.testar',$api) }}">@csrf
        <button class="btn btn-ghost btn-sm">🔌 Testar conexão</button>
      </form>
      @endif
    </div>
    @if($api->provider !== 'pix_estatico')
    <div class="rounded-lg bg-slate-50 border p-2.5 text-xs space-y-1">
      <div class="font-semibold text-slate-600">Webhook do Mercado Pago — cole esta URL no MP (campo “URL de produção”):</div>
      <div class="flex gap-2">
        <input class="input !min-h-[34px] font-mono text-[11px]" readonly onclick="this.select()" value="{{ route('mp.webhook',$api) }}">
        <button type="button" class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText('{{ route('mp.webhook',$api) }}')">copiar</button>
      </div>
      <div class="text-slate-400">No MP marque o evento <b>Pagamentos</b>. Depois copie a <b>assinatura secreta</b> que o MP gerar e cole aqui embaixo em “Webhook secret”.</div>
    </div>
    @endif
    <form method="post" action="{{ route('admin.pagamentos.salvar',$api) }}" class="grid sm:grid-cols-2 gap-3" x-data="{prov:'{{ $api->provider }}'}">@csrf
      <div><label class="label">Nome</label><input name="nome" class="input" value="{{ $api->nome }}" required maxlength="80"></div>
      <div><label class="label">Tipo</label>
        <select name="provider" x-model="prov" class="input">@foreach(\App\Models\PagamentoApi::PROVIDERS as $k=>$l)<option value="{{ $k }}" @selected($api->provider==$k)>{{ $l }}</option>@endforeach</select>
      </div>
      <template x-if="prov==='mercadopago'">
        <div class="sm:col-span-2 grid sm:grid-cols-2 gap-3">
          <div class="sm:col-span-2"><label class="label">Access Token (secreto)</label>
            <input name="access_token" type="password" class="input font-mono text-sm" placeholder="•••• deixe em branco para manter o atual" autocomplete="off">
            <p class="text-[11px] text-slate-400 mt-0.5">Mercado Pago → Suas integrações → Credenciais de produção → Access Token.</p>
          </div>
          <div><label class="label">Public Key (opcional)</label><input name="public_key" class="input font-mono text-sm" value="{{ $api->public_key }}" autocomplete="off"></div>
          <div><label class="label">Webhook secret (opcional)</label><input name="webhook_secret" class="input font-mono text-sm" value="{{ $api->webhook_secret }}" autocomplete="off"></div>
        </div>
      </template>
      <template x-if="prov==='pix_estatico'">
        <div class="sm:col-span-2 grid sm:grid-cols-2 gap-3">
          <div><label class="label">Tipo de chave</label>
            <select name="pix_tipo" class="input">
              <option value="aleatoria" @selected($api->pixTipo()==='aleatoria')>Aleatória (EVP)</option>
              <option value="celular" @selected($api->pixTipo()==='celular')>Celular</option>
              <option value="cpf" @selected($api->pixTipo()==='cpf')>CPF</option>
              <option value="cnpj" @selected($api->pixTipo()==='cnpj')>CNPJ</option>
              <option value="email" @selected($api->pixTipo()==='email')>E-mail</option>
            </select>
          </div>
          <div><label class="label">Chave PIX</label><input name="pix_chave" class="input" value="{{ $api->pixChave() }}" placeholder="a chave" autocapitalize="none"></div>
          <div class="sm:col-span-2 text-[11px] text-slate-400 -mt-1">Celular: com DDD (vira +55…). CPF/CNPJ: só números. E-mail: igual ao cadastrado no banco.</div>
          <div><label class="label">Nome do recebedor</label><input name="pix_recebedor" class="input" value="{{ $api->pixRecebedor() }}" maxlength="25"></div>
          <div><label class="label">Cidade</label><input name="pix_cidade" class="input" value="{{ $api->pixCidade() }}" maxlength="15"></div>
        </div>
      </template>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="ativo" value="1" @checked($api->ativo) class="w-5 h-5 rounded"> Ativa</label>
      <div class="sm:col-span-2 flex gap-2 justify-end border-t pt-3">
        <button class="btn btn-primary btn-sm">Salvar</button>
      </div>
    </form>
    <form method="post" action="{{ route('admin.pagamentos.excluir',$api) }}" onsubmit="return confirm('Remover esta API?')">@csrf @method('delete')
      <button class="text-xs text-red-500 hover:underline">excluir API</button>
    </form>
  </div>
  @endforeach

  {{-- Nova API --}}
  <div class="card p-4 space-y-3">
    <div class="font-bold text-sm text-slate-600 uppercase">➕ Nova API</div>
    <form method="post" action="{{ route('admin.pagamentos.salvar') }}" class="grid sm:grid-cols-2 gap-3" x-data="{prov:'mercadopago'}">@csrf
      <div><label class="label">Nome</label><input name="nome" class="input" placeholder="Ex.: PIX da empresa / Mercado Pago" required maxlength="80"></div>
      <div><label class="label">Tipo</label>
        <select name="provider" x-model="prov" class="input">@foreach(\App\Models\PagamentoApi::PROVIDERS as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
      </div>
      <template x-if="prov==='mercadopago'">
        <div class="sm:col-span-2"><label class="label">Access Token (secreto)</label><input name="access_token" type="password" class="input font-mono text-sm" placeholder="APP_USR-..." autocomplete="off"></div>
      </template>
      <template x-if="prov==='pix_estatico'">
        <div class="sm:col-span-2 grid sm:grid-cols-2 gap-3">
          <div><label class="label">Tipo de chave</label>
            <select name="pix_tipo" class="input">
              <option value="aleatoria">Aleatória (EVP)</option>
              <option value="celular">Celular</option>
              <option value="cpf">CPF</option>
              <option value="cnpj">CNPJ</option>
              <option value="email">E-mail</option>
            </select>
          </div>
          <div><label class="label">Chave PIX</label><input name="pix_chave" class="input" placeholder="a chave" autocapitalize="none"></div>
          <div class="sm:col-span-2 text-[11px] text-slate-400 -mt-1">Celular: com DDD (vira +55…). CPF/CNPJ: só números. E-mail: igual ao cadastrado no banco.</div>
          <div><label class="label">Nome do recebedor</label><input name="pix_recebedor" class="input" maxlength="25"></div>
          <div><label class="label">Cidade</label><input name="pix_cidade" class="input" maxlength="15"></div>
        </div>
      </template>
      <div class="sm:col-span-2 flex justify-end"><button class="btn btn-accent btn-sm">Criar</button></div>
    </form>
  </div>

  {{-- Atrelar por módulo --}}
  <div class="card p-4 space-y-3">
    <div class="font-bold text-sm text-slate-600 uppercase">Pagamento por módulo</div>
    <p class="text-xs text-slate-500">Escolha qual conta recebe o PIX de cada módulo. Marque “PIX” pra habilitar a cobrança dinâmica nele.</p>
    <form method="post" action="{{ route('admin.pagamentos.atribuir') }}" class="space-y-2">@csrf
      <div class="divide-y">
        @foreach($modulos as $mk=>$md)
        <div class="flex items-center gap-3 py-2">
          <div class="w-32 shrink-0 text-sm font-semibold">{{ $md[1] }} {{ $md[0] }}</div>
          <select name="pgto_api[{{ $mk }}]" class="input !min-h-[38px] flex-1">
            <option value="">— sem pagamento online —</option>
            @foreach($apis as $api)<option value="{{ $api->id }}" @selected($empresa->config('pgto_api_'.$mk)==$api->id)>{{ $api->nome }}</option>@endforeach
          </select>
          <label class="flex items-center gap-1.5 text-sm whitespace-nowrap"><input type="checkbox" name="pix[{{ $mk }}]" value="1" @checked($empresa->config('pix_'.$mk)) class="w-5 h-5 rounded"> PIX</label>
        </div>
        @endforeach
      </div>
      <div class="flex justify-end"><button class="btn btn-primary btn-sm">Salvar</button></div>
    </form>
  </div>

</div>
@endsection
