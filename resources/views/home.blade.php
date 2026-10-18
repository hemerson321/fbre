@extends('layouts.app')
@section('titulo','Início')
@section('conteudo')
@php
  $u = auth()->user();
  $empresa = \App\Support\Tenant::empresa();
  $h = now()->hour;
  $saud = $h < 12 ? 'Bom dia' : ($h < 18 ? 'Boa tarde' : 'Boa noite');
  $primeiro = \Illuminate\Support\Str::of($u->name)->explode(' ')->first();

  $cards = [];
  if ($u->podeCadastrar()) $cards[] = ['r'=>route('encomendas.create'), 'i'=>'➕', 't'=>'Nova encomenda', 'd'=>'Cadastrar encomenda, imprimir comprovante e etiqueta.'];
  if ($u->podeFiltrar())   $cards[] = ['r'=>route('encomendas.index'), 'i'=>'📦', 't'=>'Encomendas / baixa', 'd'=>'Buscar, dar baixa (pagamento e entrega), protocolo e listagem.'];
  if ($u->podeVerCaixa())  $cards[] = ['r'=>route('caixa.index'), 'i'=>'💰', 't'=>$u->caixaTodos()?'Caixa geral':'Meu caixa', 'd'=>'Entradas por meio de pagamento e operador, por período ou viagem.'];
  if ($u->podeRelatorio()) $cards[] = ['r'=>route('relatorio.meu'), 'i'=>'📋', 't'=>$u->relatorioTodos()?'Relatório geral':'Meu relatório', 'd'=>'O que você cadastrou, recebeu e entregou no período.'];
  if ($u->isAdmin() || $u->isSuper()) {
    $cards[] = ['r'=>route('admin.usuarios.index'), 'i'=>'👥', 't'=>'Usuários', 'd'=>'Criar operadores, permissões e tipo (operador, agência, gerente).'];
    $cards[] = ['r'=>route('admin.cadastros'), 'i'=>'🗂️', 't'=>'Cadastros gerais', 'd'=>'Barcos, setores, produtos, rotas e estações de impressão.'];
    $cards[] = ['r'=>route('admin.empresa'), 'i'=>'🏢', 't'=>'Minha empresa', 'd'=>'Logo, cores e dados que saem no comprovante.'];
  }
  if ($u->isSuper() && ! config('fbre.single')) $cards[] = ['r'=>route('super.empresas'), 'i'=>'🛠️', 't'=>'Empresas (clientes)', 'd'=>'Ativar/bloquear empresas e validade de contrato.'];
@endphp

<div class="max-w-5xl mx-auto space-y-5">

  {{-- Boas-vindas --}}
  <div class="rounded-2xl p-6 text-white shadow-md" style="background: linear-gradient(120deg, var(--cor1), color-mix(in srgb, var(--cor1) 65%, var(--cor2)))">
    <div class="flex items-center gap-4">
      @if($empresa?->logo)
        <img src="/{{ $empresa->logo }}" alt="" class="h-14 w-14 rounded-xl object-cover bg-white/90 p-1 shrink-0">
      @endif
      <div class="min-w-0">
        <div class="text-sm opacity-80">{{ $saud }},</div>
        <h1 class="text-2xl font-extrabold leading-tight truncate">{{ $primeiro }} 👋</h1>
        <div class="text-sm opacity-80 truncate">{{ $empresa?->nomeExibicao() ?? 'FBRE' }} · {{ $u->setor ?: 'Operador' }}@if($u->papel) · {{ $u->papelNome() }}@endif</div>
      </div>
    </div>
    <p class="mt-4 text-sm text-white/85">
      Escolha um módulo abaixo ou use o <b>menu por módulo</b> aqui em cima. Tudo que você pode acessar aparece nos dois lugares.
    </p>
  </div>

  {{-- Módulos --}}
  <div>
    <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Módulos</div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
      @foreach($cards as $c)
        <a href="{{ $c['r'] }}" class="card p-4 flex items-start gap-3 hover:shadow-md transition group">
          <div class="w-11 h-11 rounded-xl flex items-center justify-center text-xl shrink-0" style="background: color-mix(in srgb, var(--cor2) 18%, white)">{{ $c['i'] }}</div>
          <div class="min-w-0">
            <div class="font-bold group-hover:underline" style="color:var(--cor1)">{{ $c['t'] }}</div>
            <div class="text-xs text-slate-500 mt-0.5">{{ $c['d'] }}</div>
          </div>
        </a>
      @endforeach
    </div>
  </div>

  <p class="text-xs text-slate-400 text-center">Dúvida? Fale com o administrador da empresa ou com o suporte da GERATEC.</p>
</div>
@endsection
