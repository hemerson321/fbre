@php
  $cor1 = $empresa?->cor_primaria ?? '#0b1a33';
  $cor2 = $empresa?->cor_secundaria ?? '#00a8e8';
  $logo = $empresa?->logo ? '/'.$empresa->logo : '/img/logo-geratec.png';
  $nome = $empresa?->nomeExibicao() ?? 'FBRE';
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="{{ $cor1 }}">
<title>Entrar · {{ $nome }}</title>
<link rel="icon" href="{{ $logo }}">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body{font-family:Inter,system-ui,sans-serif}
  .bg{background: radial-gradient(1200px 600px at 20% -10%, {{ $cor2 }}33, transparent 60%), radial-gradient(900px 500px at 110% 110%, {{ $cor2 }}22, transparent 60%), {{ $cor1 }};}
  .focus-ring:focus{outline:none;box-shadow:0 0 0 3px {{ $cor2 }}55;border-color:{{ $cor2 }}}
</style>
</head>
<body class="min-h-screen bg flex items-center justify-center p-4">
  <div class="w-full max-w-sm">
    <div class="text-center mb-6">
      <div class="inline-flex items-center justify-center bg-white rounded-2xl p-3 shadow-xl mb-4">
        <img src="{{ $logo }}" alt="{{ $nome }}" class="h-20 w-auto max-w-[220px] object-contain">
      </div>
      <h1 class="text-white text-xl font-extrabold tracking-tight leading-tight">{{ $nome }}</h1>
      <p class="text-white/60 text-xs mt-1">Sistema de encomendas</p>
    </div>

    <form method="post" action="/login" class="bg-white rounded-2xl shadow-2xl p-6 space-y-4">
      @csrf
      @if($empresa)<input type="hidden" name="empresa" value="{{ $empresa->slug }}">@endif
      @if($errors->any())<div class="rounded-lg bg-red-50 text-red-700 text-sm px-3 py-2">{{ $errors->first() }}</div>@endif
      <div>
        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Usuário</label>
        <input name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false"
               class="focus-ring w-full rounded-xl border border-slate-300 px-3 py-3 text-base">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Senha</label>
        <input name="password" type="password" required autocomplete="current-password"
               class="focus-ring w-full rounded-xl border border-slate-300 px-3 py-3 text-base">
      </div>
      <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="lembrar" value="1" class="rounded"> Manter conectado</label>
      <button class="w-full rounded-xl text-white font-bold py-3.5 text-base hover:brightness-110 active:scale-[.98] transition" style="background: {{ $cor2 }}">Entrar</button>
    </form>

    <p class="text-center text-white/40 text-[11px] mt-6">
      @if($empresa && ! config('fbre.single'))<a href="/login" class="hover:text-white/70">Entrar em outra empresa</a> · @endif
      FBRE · GERATEC Soluções
    </p>
  </div>
</body>
</html>
