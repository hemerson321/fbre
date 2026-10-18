@php
  $cor1 = $empresa->cor_primaria ?? '#0b1a33';
  $cor2 = $empresa->cor_secundaria ?? '#00a8e8';
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Código gerado · {{ $empresa->nomeExibicao() }}</title>
<link rel="icon" href="{{ $empresa->logo ? '/'.$empresa->logo : '/img/icon.png' }}">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
<style type="text/tailwindcss">:root{--cor1:{{ $cor1 }};--cor2:{{ $cor2 }}} body{background:#eef2f7}</style>
</head>
<body class="font-sans text-slate-800 min-h-screen flex items-center">
<div class="max-w-md mx-auto p-5 w-full">
  <div class="rounded-3xl p-7 text-white shadow-xl text-center" style="background:linear-gradient(140deg,var(--cor1),color-mix(in srgb,var(--cor1) 65%,var(--cor2)))">
    @if($empresa->logo)<img src="/{{ $empresa->logo }}" class="h-14 w-14 rounded-xl object-cover bg-white/90 p-1 mx-auto mb-3">@endif
    <div class="text-sm opacity-85">Pronto, {{ \Illuminate\Support\Str::of($pre->remetente_nome)->explode(' ')->first() }}! Mostre este código no balcão:</div>
    <div class="my-4 rounded-2xl bg-white/15 py-5">
      <div class="text-5xl font-black tracking-[0.2em]">{{ $pre->codigo }}</div>
    </div>
    <div class="text-sm opacity-90">{{ $pre->origem }} → {{ $pre->destino }}</div>
    <div class="text-xs opacity-75 mt-1">em nome de <b>{{ $pre->remetente_nome }}</b></div>
  </div>

  <div class="mt-4 bg-white rounded-2xl border border-slate-100 p-4 text-center text-sm text-slate-600">
    Chegando no balcão, diga o <b>código</b> ou o <b>seu nome</b> que o atendente finaliza na hora. O pagamento é feito lá.
  </div>

  <a href="{{ route('pre.form', $slug) }}" class="block text-center mt-4 text-sm font-semibold" style="color:var(--cor1)">+ Fazer outro pré-cadastro</a>
</div>
</body>
</html>
