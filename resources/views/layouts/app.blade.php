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
@php
  // ---------- menu por módulo (uma definição, usada no topo e na gaveta) ----------
  $grupos = [];
  $g = [];
  if ($u->podeCadastrar()) $g[] = ['r'=>route('encomendas.create'), 'l'=>'Nova encomenda', 'i'=>'➕'];
  if ($u->podeFiltrar())   { $g[] = ['r'=>route('encomendas.index'), 'l'=>'Encomendas / baixa', 'i'=>'📦'];
                             $g[] = ['r'=>route('imprimir.protocolo', ['inicio'=>now()->toDateString(),'fim'=>now()->toDateString()]), 'l'=>'Protocolo do dia', 'i'=>'🖨', 'blank'=>true]; }
  if ($g) $grupos['Encomendas'] = $g;
  $g = [];
  if ($u->podeVerCaixa())  $g[] = ['r'=>route('caixa.index'), 'l'=>'Caixa geral', 'i'=>'💰'];
  if ($u->podeRelatorio()) $g[] = ['r'=>route('relatorio.meu'), 'l'=>'Meu relatório', 'i'=>'📋'];
  if ($g) $grupos['Caixa'] = $g;
  if ($u->isAdmin() || $u->isSuper()) $grupos['Administração'] = [
      ['r'=>route('admin.usuarios.index'), 'l'=>'Usuários', 'i'=>'👥'],
      ['r'=>route('admin.cadastros'), 'l'=>'Barcos, locais, produtos', 'i'=>'🚢'],
      ['r'=>route('admin.empresa'), 'l'=>'Minha empresa', 'i'=>'🏢'],
  ];
  if ($u->isSuper() && ! config('fbre.single')) $grupos['GERATEC'] = [['r'=>route('super.empresas'), 'l'=>'Empresas (clientes)', 'i'=>'🛠️']];
  $ativo = fn($r) => request()->url() === strtok($r, '?');
@endphp

{{-- ===== BARRA DA EMPRESA ===== --}}
<header class="no-print sticky top-0 z-30 text-white shadow-md" style="background: var(--cor1)">
  <div class="mx-auto max-w-[1600px] flex items-center gap-3 px-3 sm:px-4 h-14">
    <button class="lg:hidden p-2 -ml-2 rounded-lg hover:bg-white/10" @click="menu=!menu" aria-label="Menu">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    @if($empresa?->logo)
      <img src="/{{ $empresa->logo }}" alt="" class="h-9 w-9 rounded-lg object-cover bg-white/90 p-0.5">
    @endif
    <div class="min-w-0 flex-1">
      <div class="font-bold leading-tight truncate">{{ $empresa?->nomeExibicao() ?? 'FBRE' }}</div>
      <div class="text-[11px] opacity-70 leading-tight truncate">{{ $u->name }} · {{ $u->setor ?: 'Operador' }}</div>
    </div>
    @if($u->isSuper() && session('empresa_ativa') && ! config('fbre.single'))
      <form method="post" action="{{ route('super.empresas.sair') }}">@csrf
        <button class="text-[11px] rounded-lg bg-white/15 px-2 py-1 hover:bg-white/25">sair da empresa</button>
      </form>
    @endif
    <form method="post" action="{{ route('logout') }}" class="hidden sm:block">@csrf
      <button class="text-xs rounded-lg bg-white/10 px-3 py-1.5 hover:bg-white/20">Sair</button>
    </form>
  </div>

  {{-- ===== MENU HORIZONTAL (desktop) ===== --}}
  <nav class="hidden lg:block bg-white text-slate-700 border-b border-slate-200">
    <div class="mx-auto max-w-[1600px] px-4 flex items-stretch h-11 overflow-x-auto">
      @foreach($grupos as $titulo => $itens)
        <div class="flex items-center gap-0.5 pr-3 mr-3 border-r border-slate-200 last:border-0 last:mr-0 last:pr-0">
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mr-1.5 whitespace-nowrap">{{ $titulo }}</span>
          @foreach($itens as $it)
            <a href="{{ $it['r'] }}" @if(!empty($it['blank'])) target="_blank" @endif
               class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[13px] font-medium whitespace-nowrap transition
                      {{ $ativo($it['r']) ? 'text-white shadow-sm' : 'hover:bg-slate-100' }}"
               @if($ativo($it['r'])) style="background: var(--cor2)" @endif>
              <span class="text-sm">{{ $it['i'] }}</span>{{ $it['l'] }}
            </a>
          @endforeach
        </div>
      @endforeach
    </div>
  </nav>
</header>

{{-- ===== GAVETA (mobile) ===== --}}
<aside class="no-print fixed inset-y-0 left-0 z-40 w-72 bg-white shadow-2xl transform transition lg:hidden"
       :class="menu ? 'translate-x-0' : '-translate-x-full'" x-cloak>
  <div class="flex items-center justify-between p-4 border-b">
    <span class="font-bold">Menu</span>
    <button @click="menu=false" class="p-2">✕</button>
  </div>
  <nav class="p-3 space-y-3 text-sm">
    @foreach($grupos as $titulo => $itens)
      <div>
        <div class="px-3 pb-1 text-[10.5px] font-bold uppercase tracking-wider text-slate-400">{{ $titulo }}</div>
        @foreach($itens as $it)
          <a href="{{ $it['r'] }}" @if(!empty($it['blank'])) target="_blank" @endif class="flex items-center gap-3 rounded-xl px-3 py-3 font-medium {{ $ativo($it['r']) ? 'bg-slate-100 text-[var(--cor1)] font-semibold' : 'text-slate-700' }}">
            <span class="text-lg w-6 text-center">{{ $it['i'] }}</span>{{ $it['l'] }}</a>
        @endforeach
      </div>
    @endforeach
    <form method="post" action="{{ route('logout') }}" class="pt-2 border-t">@csrf
      <button class="w-full text-left flex items-center gap-3 rounded-xl px-3 py-3 font-medium text-red-600"><span class="w-6 text-center">⏻</span>Sair</button>
    </form>
  </nav>
</aside>
<div class="no-print fixed inset-0 z-30 bg-black/40 lg:hidden" x-show="menu" @click="menu=false" x-cloak></div>

{{-- ===== CONTEÚDO ===== --}}
<main class="mx-auto max-w-[1600px] p-3 sm:p-4 lg:p-5 pb-24 lg:pb-6">
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

{{-- ===== BARRA INFERIOR (mobile / POS) ===== --}}
<nav class="no-print fixed bottom-0 inset-x-0 z-30 bg-white border-t border-slate-200 lg:hidden safe-b">
  <div class="grid grid-cols-4 text-[11px] font-semibold text-slate-500">
    @php $tab = fn($r,$l,$i) => '<a href="'.$r.'" class="flex flex-col items-center gap-0.5 py-2 '.($ativo($r)?'text-[var(--cor1)]':'').'"><span class="text-xl">'.$i.'</span>'.$l.'</a>';
      $tabs = [];
      if($u->podeCadastrar()) $tabs[] = $tab(route('encomendas.create'), 'Nova', '➕');
      if($u->podeFiltrar()) $tabs[] = $tab(route('encomendas.index'), 'Encomendas', '📦');
      if($u->podeVerCaixa()) $tabs[] = $tab(route('caixa.index'), 'Caixa', '💰');
      if($u->podeRelatorio() && count($tabs) < 3) $tabs[] = $tab(route('relatorio.meu'), 'Relatório', '📋');
      foreach(array_slice($tabs,0,3) as $t) echo $t; @endphp
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
