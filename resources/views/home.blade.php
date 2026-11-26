@extends('layouts.app')
@section('titulo','Início')
@section('conteudo')
@php
  $u = auth()->user();
  $empresa = \App\Support\Tenant::empresa();
  $h = now()->hour;
  $saud = $h < 12 ? 'Bom dia' : ($h < 18 ? 'Boa tarde' : 'Boa noite');
  $primeiro = \Illuminate\Support\Str::of($u->name)->explode(' ')->first();
  $nomeEmp = $empresa?->nomeExibicao() ?? 'FBRE';
  $cidadeUf = collect([$empresa?->cidade, $empresa?->uf])->filter()->implode(' / ');
  $fone = $empresa?->whatsapp ?: $empresa?->telefone;
@endphp

<div class="max-w-3xl mx-auto space-y-4">

  {{-- HERO de boas-vindas --}}
  <div class="rounded-3xl overflow-hidden shadow-lg">
    <div class="px-6 py-9 sm:py-11 text-white text-center" style="background:linear-gradient(135deg, var(--cor1), color-mix(in srgb, var(--cor1) 55%, var(--cor2)))">
      @if($empresa?->logo)
        <img onerror="this.style.display='none'" src="/{{ $empresa->logo }}" alt="{{ $nomeEmp }}" class="h-20 w-20 sm:h-24 sm:w-24 mx-auto rounded-2xl bg-white p-1.5 shadow-md object-contain mb-4">
      @endif
      <div class="text-[12px] font-semibold uppercase tracking-[0.2em] text-white/70">{{ $saud }}, {{ $primeiro }}</div>
      <h1 class="text-2xl sm:text-3xl font-extrabold mt-1.5 leading-tight text-balance">Bem-vindo(a) à {{ $nomeEmp }}</h1>
      <p class="text-white/85 mt-3 max-w-xl mx-auto leading-relaxed">Use o menu no topo para acessar as áreas liberadas para o seu perfil. Cada usuário enxerga apenas o que tem permissão — vender passagens, dar baixa, conferir o caixa e muito mais.</p>
      <div class="mt-5 inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-1.5 text-[12px] font-semibold text-white/90">
        <span class="w-2 h-2 rounded-full" style="background:var(--cor3)"></span>{{ $u->setor ?: 'Operador' }} · {{ $u->name }}
      </div>
    </div>
    <div style="height:5px;background:var(--cor3)"></div>
  </div>

  {{-- Empresa + Suporte --}}
  <div class="grid sm:grid-cols-2 gap-3">
    <div class="card p-4">
      <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2 flex items-center gap-1.5"><span style="color:var(--cor3)">●</span> Empresa</div>
      <div class="font-extrabold text-slate-800">{{ $empresa?->nome ?: $nomeEmp }}</div>
      <div class="mt-2 space-y-1 text-[13px] text-slate-500">
        @if($empresa?->cnpj)<div><span class="text-slate-400">CNPJ:</span> {{ $empresa->cnpj }}</div>@endif
        @if($empresa?->endereco)<div><span class="text-slate-400">Endereço:</span> {{ $empresa->endereco }}</div>@endif
        @if($cidadeUf)<div><span class="text-slate-400">Cidade:</span> {{ $cidadeUf }}</div>@endif
        @if($fone)<div><span class="text-slate-400">Contato:</span> {{ $fone }}</div>@endif
        @if($empresa?->email)<div><span class="text-slate-400">E-mail:</span> {{ $empresa->email }}</div>@endif
        @if(! $empresa?->cnpj && ! $empresa?->endereco && ! $cidadeUf && ! $fone)<div class="text-slate-400">Complete os dados da empresa em <a href="{{ route('admin.empresa') }}" class="underline">Minha empresa</a>.</div>@endif
      </div>
    </div>
    <div class="card p-4">
      <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2 flex items-center gap-1.5"><span style="color:var(--cor3)">●</span> Suporte técnico</div>
      <div class="font-extrabold text-slate-800">GERATEC</div>
      <div class="mt-2 text-[13px] text-slate-500 leading-relaxed">Problema no sistema ou dúvida? Fale com o administrador da sua empresa ou com o suporte técnico da GERATEC.</div>
      <div class="mt-3 inline-flex items-center gap-2 rounded-xl px-3 py-2 text-[13px] font-semibold" style="background:color-mix(in srgb, var(--cor1) 8%, white);color:var(--cor1)">🛠️ Suporte GERATEC</div>
    </div>
  </div>

  <p class="text-[11px] text-slate-400 text-center pt-1">{{ $nomeEmp }} · sistema GERATEC</p>
</div>
@endsection
