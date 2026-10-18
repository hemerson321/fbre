@php
    $empresa = \App\Support\Tenant::empresa();
    $u = auth()->user();
    $cor1 = $empresa?->cor_primaria ?? '#0b1a33';
    $cor2 = $empresa?->cor_secundaria ?? '#00a8e8';
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="{{ $cor1 }}">
<title>@yield('titulo', 'FBRE') · {{ $empresa?->nomeExibicao() ?? 'FBRE' }}</title>
<link rel="manifest" href="/manifest.json">
<link rel="icon" href="{{ $empresa?->logo ? '/'.$empresa->logo : '/img/icon.png' }}">
<link rel="apple-touch-icon" href="{{ $empresa?->logo ? '/'.$empresa->logo : '/img/icon.png' }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { theme: { extend: {
  fontFamily: { sans: ['Inter','system-ui','sans-serif'] },
  colors: { brand: { DEFAULT: '{{ $cor1 }}', accent: '{{ $cor2 }}' } }
}}}
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<style type="text/tailwindcss">
  :root { --cor1: {{ $cor1 }}; --cor2: {{ $cor2 }}; }
  html { -webkit-tap-highlight-color: transparent; }
  body { background: #f3f5f8; }
  .btn { @apply inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 font-semibold text-sm transition active:scale-[.98] disabled:opacity-50; min-height: 48px; }
  .btn-primary { background: var(--cor1); color: #fff; }
  .btn-accent { background: var(--cor2); color: #fff; }
  .btn-ghost { @apply bg-white border border-slate-200 text-slate-700 hover:bg-slate-50; }
  .btn-danger { @apply bg-red-600 text-white; }
  .btn-sm { min-height: 38px; @apply px-3 py-2 text-xs; }
  .input { @apply w-full rounded-xl border border-slate-300 bg-white px-3 text-[15px] focus:outline-none focus:ring-2; min-height: 48px; }
  .input:focus { --tw-ring-color: var(--cor2); border-color: var(--cor2); }
  .label { @apply block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1; }
  .card { @apply bg-white rounded-2xl shadow-sm border border-slate-100; }
  .chip { @apply inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold; }
  .st-verde { @apply bg-emerald-100 text-emerald-800; }
  .st-amarelo { @apply bg-amber-100 text-amber-800; }
  .st-vermelho { @apply bg-red-100 text-red-800; }
  .st-cinza { @apply bg-slate-200 text-slate-600; }
  .row-verde { border-left: 5px solid #10b981; }
  .row-amarelo { border-left: 5px solid #f59e0b; }
  .row-vermelho { border-left: 5px solid #ef4444; }
  .row-cinza { border-left: 5px solid #94a3b8; }
  [x-cloak] { display: none !important; }
  .safe-b { padding-bottom: env(safe-area-inset-bottom); }
  @media print { .no-print { display: none !important; } body { background: #fff; } }
</style>
@stack('head')
</head>
<body class="font-sans text-slate-800 min-h-screen" x-data="{ menu:false }">

{{-- ===== TOPO ===== --}}
<header class="no-print sticky top-0 z-30 text-white shadow-md" style="background: var(--cor1)">
  <div class="mx-auto max-w-7xl flex items-center gap-3 px-3 sm:px-4 h-14">
    <button class="lg:hidden p-2 -ml-2 rounded-lg hover:bg-white/10" @click="menu=!menu" aria-label="Menu">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    @if($empresa?->logo)
      <img src="/{{ $empresa->logo }}" alt="" class="h-9 w-9 rounded-lg object-cover bg-white/90 p-0.5">
    @endif
    <div class="min-w-0 flex-1">
      <div class="font-bold leading-tight truncate">{{ $empresa?->nomeExibicao() ?? 'FBRE' }}</div>
      <div class="text-[11px] opacity-70 leading-tight truncate">Encomendas · {{ $u->name }}</div>
    </div>
    @if($u->isSuper() && session('empresa_ativa'))
      <form method="post" action="{{ route('super.empresas.sair') }}">@csrf
        <button class="text-[11px] rounded-lg bg-white/15 px-2 py-1 hover:bg-white/25">sair da empresa</button>
      </form>
    @endif
    <form method="post" action="{{ route('logout') }}" class="hidden sm:block">@csrf
      <button class="text-xs rounded-lg bg-white/10 px-3 py-1.5 hover:bg-white/20">Sair</button>
    </form>
  </div>
</header>

<div class="mx-auto max-w-7xl lg:grid lg:grid-cols-[240px_1fr]">

  {{-- ===== MENU LATERAL (desktop) / GAVETA (mobile) ===== --}}
  <aside class="no-print fixed inset-y-0 left-0 z-40 w-72 bg-white shadow-2xl transform transition lg:static lg:w-auto lg:shadow-none lg:transform-none lg:bg-transparent lg:pt-4 lg:pl-4"
         :class="menu ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'" x-cloak>
    <div class="flex items-center justify-between p-4 lg:hidden border-b">
      <span class="font-bold">Menu</span>
      <button @click="menu=false" class="p-2">✕</button>
    </div>
    <nav class="p-3 lg:p-0 space-y-2 text-sm">
      @php
        $item = fn($r,$l,$i) => '<a href="'.$r.'" class="flex items-center gap-2.5 rounded-lg px-3 py-2 font-medium hover:bg-white '.(request()->url()===$r?'bg-white shadow-sm text-[var(--cor1)] font-semibold':'text-slate-600').'"><span class="text-base w-5 text-center">'.$i.'</span>'.$l.'</a>';
        $grupo = fn($t) => '<div class="flex items-center gap-2 px-3 pt-1 pb-1 text-[10.5px] font-bold tracking-wider text-slate-400 uppercase"><span class="h-px flex-1 bg-slate-200"></span>'.$t.'<span class="h-px flex-1 bg-slate-200"></span></div>';
      @endphp

      {{-- MÓDULO ENCOMENDAS --}}
      <div class="rounded-xl bg-slate-100/70 p-1.5">
        {!! $grupo('Encomendas') !!}
        @if($u->podeCadastrar()) {!! $item(route('encomendas.create'), 'Nova encomenda', '➕') !!} @endif
        {!! $item(route('encomendas.index'), 'Encomendas / baixa', '📦') !!}
        <a target="_blank" href="{{ route('imprimir.protocolo', ['inicio'=>now()->toDateString(),'fim'=>now()->toDateString()]) }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 font-medium hover:bg-white text-slate-600"><span class="text-base w-5 text-center">🖨</span>Protocolo do dia</a>
      </div>

      {{-- MÓDULO CAIXA --}}
      <div class="rounded-xl bg-slate-100/70 p-1.5">
        {!! $grupo('Caixa') !!}
        @if($u->podeVerCaixa()) {!! $item(route('caixa.index'), 'Caixa geral', '💰') !!} @endif
        {!! $item(route('relatorio.meu'), 'Meu relatório', '📋') !!}
      </div>

      @if($u->isAdmin() || $u->isSuper())
      <div class="rounded-xl bg-slate-100/70 p-1.5">
        {!! $grupo('Administração') !!}
        {!! $item(route('admin.usuarios.index'), 'Usuários e permissões', '👥') !!}
        {!! $item(route('admin.cadastros'), 'Barcos, locais, produtos', '🚢') !!}
        {!! $item(route('admin.empresa'), 'Minha empresa', '🏢') !!}
      </div>
      @endif

      @if($u->isSuper())
      <div class="rounded-xl p-1.5" style="background: color-mix(in srgb, var(--cor2) 12%, white)">
        {!! $grupo('GERATEC') !!}
        {!! $item(route('super.empresas'), 'Empresas (clientes)', '🛠️') !!}
      </div>
      @endif

      <form method="post" action="{{ route('logout') }}" class="sm:hidden pt-2">@csrf
        <button class="w-full text-left flex items-center gap-3 rounded-xl px-3 py-3 font-medium text-red-600"><span class="w-6 text-center">⏻</span>Sair</button>
      </form>
    </nav>
  </aside>
  <div class="no-print fixed inset-0 z-30 bg-black/40 lg:hidden" x-show="menu" @click="menu=false" x-cloak></div>

  {{-- ===== CONTEÚDO ===== --}}
  <main class="p-3 sm:p-4 lg:p-6 pb-24 lg:pb-8">
    @if(session('ok'))
      <div class="mb-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm no-print">{{ session('ok') }}</div>
    @endif
    @if($errors->any())
      <div class="mb-3 rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm no-print">
        <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif
    @yield('conteudo')
  </main>
</div>

{{-- ===== BARRA INFERIOR (mobile / POS) ===== --}}
<nav class="no-print fixed bottom-0 inset-x-0 z-30 bg-white border-t border-slate-200 lg:hidden safe-b">
  <div class="grid grid-cols-4 text-[11px] font-semibold text-slate-500">
    @php $tab = fn($r,$l,$i) => '<a href="'.$r.'" class="flex flex-col items-center gap-0.5 py-2 '.(request()->url()===$r?'text-[var(--cor1)]':'').'"><span class="text-xl">'.$i.'</span>'.$l.'</a>'; @endphp
    {!! $tab(route('encomendas.index'), 'Encomendas', '📦') !!}
    @if($u->podeCadastrar()) {!! $tab(route('encomendas.create'), 'Nova', '➕') !!} @else {!! $tab(route('relatorio.meu'), 'Relatório', '📋') !!} @endif
    @if($u->podeVerCaixa()) {!! $tab(route('caixa.index'), 'Caixa', '💰') !!} @else {!! $tab(route('relatorio.meu'), 'Relatório', '📋') !!} @endif
    <button @click="menu=true" class="flex flex-col items-center gap-0.5 py-2"><span class="text-xl">☰</span>Menu</button>
  </div>
</nav>

<script>
  window.FBRE = {
    csrf: document.querySelector('meta[name=csrf-token]').content,
    moeda: v => (Number(v)||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'}),
    abrirImpressao: url => { const w = window.open(url, 'fbre_print', 'width=420,height=640'); if (w) w.focus(); },
  };
</script>
@stack('scripts')
</body>
</html>
