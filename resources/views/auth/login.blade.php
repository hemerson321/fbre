<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar · FBRE</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>body{font-family:Inter,system-ui,sans-serif}</style>
</head>
<body class="min-h-screen bg-[#0b1a33] flex items-center justify-center p-4">
  <div class="w-full max-w-sm">
    <div class="text-center mb-6">
      <img src="/img/logo-geratec.png" alt="GERATEC" class="h-16 mx-auto mb-3 bg-white rounded-xl p-2">
      <h1 class="text-white text-2xl font-extrabold tracking-tight">FBRE</h1>
      <p class="text-white/60 text-sm">Encomendas e caixa para transporte fluvial</p>
    </div>
    <form method="post" action="/login" class="bg-white rounded-2xl shadow-xl p-6 space-y-4">
      @csrf
      @if($errors->any())<div class="rounded-lg bg-red-50 text-red-700 text-sm px-3 py-2">{{ $errors->first() }}</div>@endif
      <div>
        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">E-mail</label>
        <input name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
               class="w-full rounded-xl border border-slate-300 px-3 py-3 text-base focus:outline-none focus:ring-2 focus:ring-[#00a8e8]">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Senha</label>
        <input name="password" type="password" required autocomplete="current-password"
               class="w-full rounded-xl border border-slate-300 px-3 py-3 text-base focus:outline-none focus:ring-2 focus:ring-[#00a8e8]">
      </div>
      <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="lembrar" value="1" class="rounded"> Manter conectado</label>
      <button class="w-full rounded-xl bg-[#00a8e8] text-white font-bold py-3.5 text-base hover:brightness-110 active:scale-[.98]">Entrar</button>
    </form>
    <p class="text-center text-white/40 text-xs mt-6">fbre.com.br · GERATEC Soluções</p>
  </div>
</body>
</html>
