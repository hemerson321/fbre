@extends('layouts.app')
@section('titulo','Início')
@section('conteudo')
@php
  use App\Support\Modulos;
  $u = auth()->user();
  $empresa = \App\Support\Tenant::empresa();
  $h = now()->hour;
  $saud = $h < 12 ? 'Bom dia' : ($h < 18 ? 'Boa tarde' : 'Boa noite');
  $primeiro = \Illuminate\Support\Str::of($u->name)->explode(' ')->first();

  // Cards GRANDES por módulo (só os que já existem e que o operador pode usar)
  $rotaCriar = [
    'encomenda' => fn() => route('encomendas.create'),
    'passagem'  => fn() => route('passagens.create'),
    'frete'     => fn() => route('fretes.create'),
  ];
  $desc = [
    'encomenda' => 'Cadastrar encomenda, comprovante e etiqueta.',
    'passagem'  => 'Vender passagem por tipo de passageiro.',
    'frete'     => 'Em breve.',
    'camarote'  => 'Em breve.',
    'lanchonete'=> 'Em breve.',
    'wifi'      => 'Em breve.',
    'avulso'    => 'Em breve.',
    'recibo'    => 'Em breve.',
  ];
  $coresPorModulo = (bool) (\App\Support\Tenant::empresa()?->config('cores_por_modulo', true) ?? true);
  $corEmp = \App\Support\Tenant::empresa()?->cor_primaria ?? '#0b1a33';
  $mods = [];
  foreach (Modulos::LISTA as $k => [$rot,$ic,$cor,$hex]) {
    if (! $u->temModulo($k)) continue;      // sem o módulo, nem aparece
    if (! isset($rotaCriar[$k])) continue;  // módulo ainda não construído: não mostra "Em breve"
    $mods[] = ['k'=>$k, 't'=>$rot, 'i'=>$ic, 'hex'=>($coresPorModulo ? $hex : $corEmp), 'r'=>$rotaCriar[$k](), 'd'=>$desc[$k] ?? ''];
  }

  // Ferramentas (caixa, relatório, admin)
  $tools = [];
  if ($u->temModulo('encomenda') || $u->podeFiltrar()) $tools[] = ['r'=>route('encomendas.index'), 'i'=>'📦', 't'=>'Encomendas / baixa'];
  if ($u->temModulo('passagem')) $tools[] = ['r'=>route('passagens.index'), 'i'=>'🧾', 't'=>'Passagens / embarque'];
  if ($u->temModulo('frete')) $tools[] = ['r'=>route('fretes.index'), 'i'=>'📦', 't'=>'Fretes / entrega'];
  if ($u->podeVerCaixa())  $tools[] = ['r'=>route('caixa.index'), 'i'=>'💰', 't'=>$u->caixaTodos()?'Caixa geral':'Meu caixa'];
  if ($u->podeRelatorio()) $tools[] = ['r'=>route('relatorio.meu'), 'i'=>'📋', 't'=>$u->relatorioTodos()?'Relatório geral':'Meu relatório'];
  if ($u->isAdmin() || $u->isSuper()) {
    $tools[] = ['r'=>route('admin.usuarios.index'), 'i'=>'👥', 't'=>'Usuários'];
    $tools[] = ['r'=>route('admin.cadastros'), 'i'=>'🗂️', 't'=>'Cadastros gerais'];
    $tools[] = ['r'=>route('admin.empresa'), 'i'=>'🏢', 't'=>'Minha empresa'];
  }
  if ($u->isSuper() && ! config('fbre.single')) $tools[] = ['r'=>route('super.empresas'), 'i'=>'🛠️', 't'=>'Empresas'];
@endphp

<div class="max-w-5xl mx-auto space-y-5">

  {{-- Boas-vindas --}}
  <div class="rounded-2xl p-5 text-white shadow-md" style="background: linear-gradient(120deg, var(--cor1), color-mix(in srgb, var(--cor1) 65%, var(--cor2)))">
    <div class="flex items-center gap-4">
      @if($empresa?->logo)<img src="/{{ $empresa->logo }}" alt="" class="h-14 w-14 rounded-xl object-cover bg-white/90 p-1 shrink-0">@endif
      <div class="min-w-0">
        <div class="text-sm opacity-80">{{ $saud }},</div>
        <h1 class="text-2xl font-extrabold leading-tight truncate">{{ $primeiro }} 👋</h1>
        <div class="text-sm opacity-80 truncate">{{ $empresa?->nomeExibicao() ?? 'FBRE' }} · {{ $u->setor ?: 'Operador' }}</div>
      </div>
    </div>
  </div>

  {{-- MÓDULOS (cards grandes coloridos) --}}
  @if(count($mods))
  <div>
    <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">O que você quer fazer?</div>
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
      @foreach($mods as $m)
        @if($m['r'])
          <a href="{{ $m['r'] }}" class="rounded-2xl p-5 text-white shadow-sm hover:shadow-lg hover:scale-[1.02] transition flex flex-col gap-2 min-h-[120px]" style="background:{{ $m['hex'] }}">
            <div class="text-3xl">{{ $m['i'] }}</div>
            <div class="font-extrabold text-lg leading-tight">{{ $m['t'] }}</div>
            <div class="text-xs text-white/80">{{ $m['d'] }}</div>
          </a>
        @else
          <div class="rounded-2xl p-5 shadow-sm flex flex-col gap-2 min-h-[120px] opacity-60 border-2 border-dashed" style="border-color:{{ $m['hex'] }}40">
            <div class="text-3xl grayscale">{{ $m['i'] }}</div>
            <div class="font-bold text-lg leading-tight" style="color:{{ $m['hex'] }}">{{ $m['t'] }}</div>
            <div class="text-xs text-slate-400">Em breve</div>
          </div>
        @endif
      @endforeach
    </div>
  </div>
  @endif

  {{-- FERRAMENTAS --}}
  @if(count($tools))
  <div>
    <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Consultas e gestão</div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5">
      @foreach($tools as $t)
        <a href="{{ $t['r'] }}" class="card p-4 flex items-center gap-3 hover:shadow-md transition">
          <span class="text-2xl">{{ $t['i'] }}</span>
          <span class="font-semibold text-sm" style="color:var(--cor1)">{{ $t['t'] }}</span>
        </a>
      @endforeach
    </div>
  </div>
  @endif

  <p class="text-xs text-slate-400 text-center">Dúvida? Fale com o administrador ou com o suporte da GERATEC.</p>
</div>
@endsection
