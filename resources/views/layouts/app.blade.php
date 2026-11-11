@php
    $empresa = \App\Support\Tenant::empresa();
    $u = auth()->user();
    $previewAs = (int) request('preview_as');
    $cor1 = $empresa?->cor_primaria ?? '#0b1a33';
    $cor2 = $empresa?->cor_secundaria ?? '#00a8e8';
    // módulo atual (pela rota) -> cor do topo acompanha o módulo
    $moduloAtual = match (true) {
        request()->routeIs('passagens.camarote.*', 'passagens.camarotes', 'admin.camarotes', 'admin.camarotes.*') => 'camarote',
        request()->routeIs('passagens.*') => 'passagem',
        request()->routeIs('fretes.*') => 'frete',
        request()->routeIs('lanchonete.*') => 'lanchonete',
        request()->routeIs('wifi.*') => 'wifi',
        default => null,
    };
    $coresPorModulo = (bool) ($empresa?->config('cores_por_modulo', true) ?? true);
    // cor de cada módulo: a da empresa (config cores_modulo) ou a cor padrão do módulo
    $corModulo = fn ($m) => ($empresa?->config('cores_modulo.'.$m)) ?: \App\Support\Modulos::hex($m);
    // módulos que a empresa usa (config modulos_ativos); vazio/ausente = todos
    $modAtivos = $empresa?->config('modulos_ativos');
    $modOn = fn ($m) => empty($modAtivos) || in_array($m, (array) $modAtivos, true);
    $headerCor = ($moduloAtual && $coresPorModulo) ? $corModulo($moduloAtual) : $cor1;
    $headerGrad = 'linear-gradient(100deg, '.$headerCor.', color-mix(in srgb, '.$headerCor.' 70%, #000))';
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="{{ $headerCor }}">
<title>@yield('titulo', 'GERATEC') · {{ $empresa?->nomeExibicao() ?? 'GERATEC' }}</title>
<link rel="manifest" href="/manifest.webmanifest">
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
<script>
// Mascara de dinheiro (R$) estilo caixa: digita e preenche a partir dos centavos.
document.addEventListener('alpine:init', () => {
  const fmt = n => (Number(n)||0).toLocaleString('pt-BR',{minimumFractionDigits:2, maximumFractionDigits:2});
  Alpine.directive('money', (el, { expression }, { evaluateLater, effect, cleanup }) => {
    el.type = 'text'; el.setAttribute('inputmode','numeric');
    // campo com name (POST normal): move o name pra um hidden numerico
    let hidden = null; const nm = el.getAttribute('name');
    if (nm) { hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = nm; el.removeAttribute('name'); el.insertAdjacentElement('afterend', hidden); }
    const hasExpr = expression && expression.trim();
    const getVal = hasExpr ? evaluateLater(expression) : null;
    const setLater = hasExpr ? evaluateLater(expression + ' = __money') : null;
    const setVal = v => { if (hasExpr) { try { setLater(()=>{}, { scope: { __money: v } }); } catch(e){} } else { el._m = v; } };
    const read = cb => { if (hasExpr) getVal(cb); else cb(el._m || 0); };
    const syncHidden = v => { if (hidden) hidden.value = (Number(v)||0).toFixed(2); };
    const render = () => read(v => { if (document.activeElement !== el) el.value = fmt(v); syncHidden(v); });
    if (hasExpr) effect(render);
    const onInput = () => {
      const digits = (el.value || '').replace(/\D/g,'');
      const num = digits ? parseInt(digits,10)/100 : 0;
      setVal(num); el.value = fmt(num); syncHidden(num);
      try { el.setSelectionRange(el.value.length, el.value.length); } catch(e){}
    };
    const onFocus = () => { el.value = ''; };
    const onBlur = () => read(v => { el.value = fmt(v); syncHidden(v); });
    el.addEventListener('input', onInput);
    el.addEventListener('focus', onFocus);
    el.addEventListener('blur', onBlur);
    cleanup(() => { el.removeEventListener('input', onInput); el.removeEventListener('focus', onFocus); el.removeEventListener('blur', onBlur); });
    read(v => { el.value = fmt(v); syncHidden(v); });
  });
});
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<style type="text/tailwindcss">
  :root { --cor1: {{ $cor1 }}; --cor2: {{ $cor2 }}; }
  html { -webkit-tap-highlight-color: transparent; }
  html, body { overflow-x: hidden; max-width: 100%; }
  body { background: #eef2f7; }
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
  $semBemVindo = $u->ehDonoPuro();
  $g = [];
  if ($u->permite('encomenda','cadastra')) $g[] = ['r'=>route('encomendas.create'), 'l'=>'Nova encomenda', 'i'=>'➕'];
  if ($u->permite('encomenda','ver')) $g[] = ['r'=>route('encomendas.index'), 'l'=>'Encomendas / baixa', 'i'=>'📦'];
  if ($u->permite('encomenda','precadastro')) $g[] = ['r'=>route('pre.index'), 'l'=>'Pré-cadastros', 'i'=>'🪧', 'b'=>(\App\Models\PreCadastro::where('status','pendente')->count() ?: null)];
  if ($g && $modOn('encomenda')) $grupos['Encomendas'] = $g;
  // Passagem (só aparece se o operador tiver o módulo liberado)
  $g = [];
  if ($u->permite('passagem','cadastra')) $g[] = ['r'=>route('passagens.create'), 'l'=>'Nova passagem', 'i'=>'🎫'];
  if ($u->permite('passagem','ver'))  $g[] = ['r'=>route('passagens.index'), 'l'=>'Passagens / embarque', 'i'=>'🧾'];
  if ($u->permite('passagem','baixa')) $g[] = ['r'=>route('passagens.scanner'), 'l'=>'Check-in / embarque', 'i'=>'📷'];
  if ($u->permite('passagem','ver') && $u->revendedorDono() !== null) $g[] = ['r'=>route('passagens.minhas-vendas'), 'l'=>'Minhas vendas', 'i'=>'📊'];
  if ($u->ehRevendedor()) $g[] = ['r'=>route('meus-acertos'), 'l'=>'Acertos', 'i'=>'🤝'];
  if ($g && $modOn('passagem')) $grupos['Passagens'] = $g;
  // Camarote / Suítes (módulo próprio, permissão separada)
  $g = [];
  if ($u->permite('camarote','cadastra')) $g[] = ['r'=>route('passagens.camarote.create'), 'l'=>'Vender suíte/camarote', 'i'=>'🛏️'];
  if ($u->temModulo('camarote')) $g[] = ['r'=>route('passagens.camarote.lista'), 'l'=>'Camarotes / embarque', 'i'=>'🧾'];
  if ($u->temModulo('camarote')) $g[] = ['r'=>route('passagens.camarotes'), 'l'=>'Ocupação / reservas', 'i'=>'🗺️'];
  if ($g && $modOn('camarote')) $grupos['Camarotes'] = $g;
  // Frete
  $g = [];
  if ($u->permite('frete','cadastra')) $g[] = ['r'=>route('fretes.create'), 'l'=>'Novo frete', 'i'=>'🚢'];
  if ($u->permite('frete','ver'))      $g[] = ['r'=>route('fretes.index'), 'l'=>'Fretes / entrega', 'i'=>'📦'];
  if ($g && $modOn('frete')) $grupos['Fretes'] = $g;
  $g = [];
  if ($u->podeVerCaixa())  $g[] = ['r'=>route('caixa.index'), 'l'=>$u->caixaTodos()?'Caixa geral':'Meu caixa', 'i'=>'💰'];
  if ($u->podeRelatorio()) $g[] = ['r'=>route('relatorio.meu'), 'l'=>$u->relatorioTodos()?'Relatório geral':'Meu relatório', 'i'=>'📋'];
  if ($u->podeAcerto() && $u->temModulo('passagem')) {
      // Acerto Agentes/Transporte sairam do menu: agente paga direto o dono e o dono acerta sozinho no "Meu transporte".
      $g[] = ['r'=>route('admin.acertos', ['aba'=>'operadores']), 'l'=>'Acerto Operadores', 'i'=>'💵'];
  }
  if ($g) $grupos['Caixa'] = $g;
  if ($u->ehDonoTransporte()) $grupos['Meu transporte'] = [['r'=>route('meu-transporte'), 'l'=>'Meu transporte', 'i'=>'🚤']];
  if ($u->isAdmin() || $u->isSuper()) {
      $adm = [
          ['r'=>route('admin.dashboard'), 'l'=>'Dashboard', 'i'=>'📊'],
          ['r'=>route('simulacao.telas'), 'l'=>'Simulação (demo)', 'i'=>'🧪'],
          ['r'=>route('admin.usuarios.index'), 'l'=>'Usuários', 'i'=>'👥'],
          ['r'=>route('admin.transportes'), 'l'=>'Transportes', 'i'=>'⛴️'],
          ['r'=>route('admin.cadastros'), 'l'=>'Cadastros gerais', 'i'=>'🗂️'],
      ];
      if ($u->temModulo('passagem')) array_splice($adm, 4, 0, [['r'=>route('admin.rotas'), 'l'=>'Rotas e horários', 'i'=>'🗺️']]);
      if ($modOn('camarote')) $adm[] = ['r'=>route('admin.camarotes'), 'l'=>'Suítes / Camarotes', 'i'=>'🛏️'];
      $adm[] = ['r'=>route('admin.whatsapp'), 'l'=>'WhatsApp', 'i'=>'💬'];
      if ($u->temModulo('passagem')) $adm[] = ['r'=>route('atendimento.index'), 'l'=>'Atendimento (WhatsApp)', 'i'=>'💬', 'b'=>(\App\Models\Conversa::where('status','aguardando')->count() ?: null)];
      $adm[] = ['r'=>route('admin.pagamentos'), 'l'=>'Pagamentos (APIs)', 'i'=>'💳'];
      $adm[] = ['r'=>route('admin.llm'), 'l'=>'Provedores de IA', 'i'=>'🧠'];
      $adm[] = ['r'=>route('admin.agentes'), 'l'=>'Agentes IA', 'i'=>'🤖'];
      $adm[] = ['r'=>route('admin.empresa'), 'l'=>'Minha empresa', 'i'=>'🏢'];
      $grupos['Administração'] = $adm;
  }
  if ($u->ehRevendedor() && \App\Support\Tenant::empresa()?->config('revendedor_multi')) $grupos['Revendedor'] = [['r'=>route('filiados.meus'), 'l'=>'Meus filiados', 'i'=>'🤝']];
  if ($u->isSuper() && ! config('fbre.single')) $grupos['GERATEC'] = [['r'=>route('super.empresas'), 'l'=>'Empresas (clientes)', 'i'=>'🛠️']];
  if ($u->ehDonoPuro()) $grupos = ['Meu transporte' => [['r'=>route('meu-transporte'), 'l'=>'Meu transporte', 'i'=>'🚤']]];
  if ($u->ehRevendedor()) {
      $itensAg = [
          ['r'=>route('passagens.create'), 'l'=>'Nova passagem', 'i'=>'🎫'],
          ['r'=>route('passagens.minhas-vendas'), 'l'=>'Relatório', 'i'=>'📋'],
      ];
      // "Meus filiados" so aparece se o recurso multiagente estiver habilitado pra empresa
      if (\App\Support\Tenant::empresa()?->config('revendedor_multi')) $itensAg[] = ['r'=>route('filiados.meus'), 'l'=>'Meus filiados', 'i'=>'🤝'];
      $grupos = ['Passagens' => $itensAg];
      $semBemVindo = true;
  }
  // cor e ícone de cada grupo do menu (identidade visual por módulo)
  $metaGrupo = [
    'Encomendas'    => ['📦', $corModulo('encomenda')],
    'Passagens'     => ['🎫', $corModulo('passagem')],
    'Camarotes'     => ['🛏️', $corModulo('camarote')],
    'Fretes'        => ['🚢', $corModulo('frete')],
    'Lanchonete'    => ['🍔', $corModulo('lanchonete')],
    'WiFi'          => ['📶', $corModulo('wifi')],
    'Caixa'         => ['💰', '#0b1a33'],
    'Revendedor'    => ['🤝', '#0e7490'],
    'Meu transporte' => ['🚤', '#0e7490'],
    'Administração' => ['⚙️', '#334155'],
    'GERATEC'       => ['🛠️', '#334155'],
  ];
  if (! $coresPorModulo) { foreach ($metaGrupo as $k => $v) { $metaGrupo[$k][1] = $cor1; } }
  $ativo = function ($r) {
      if (request()->url() !== strtok($r, '?')) return false;
      parse_str(parse_url($r, PHP_URL_QUERY) ?: '', $qq);
      foreach ($qq as $k => $v) { if ((string) request()->input($k) !== (string) $v) return false; }
      return true;
  };
@endphp

{{-- ===== BARRA DA EMPRESA ===== --}}
<header class="no-print sticky top-0 z-30 text-white shadow-md transition-colors duration-300" style="background: {{ $headerGrad }}">
  <div class="mx-auto max-w-[1440px] flex items-center gap-3 px-3 sm:px-4 h-14">
    <button class="lg:hidden p-2 -ml-2 rounded-lg hover:bg-white/10" @click="menu=!menu" aria-label="Menu">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <a href="{{ route('inicio') }}" class="flex items-center gap-3 min-w-0 flex-1 hover:opacity-90" title="Início">
      @if($empresa?->logo)
        <img onerror="this.style.display='none'" src="/{{ $empresa->logo }}" alt="" class="h-9 w-9 rounded-lg object-cover bg-white p-0.5 shrink-0">
      @endif
      <div class="min-w-0">
        <div class="font-bold leading-tight truncate">{{ $empresa?->nomeExibicao() ?? 'GERATEC' }}</div>
        <div class="text-[11px] opacity-70 leading-tight truncate">{{ $u->name }} · {{ $u->ehDonoPuro() ? 'Dono' : ($u->setor ?: 'Operador') }}</div>
      </div>
    </a>
    @if($u->isSuper() && session('empresa_ativa') && ! config('fbre.single'))
      <form method="post" action="{{ route('super.empresas.sair') }}">@csrf
        <button class="text-[11px] rounded-lg bg-white/15 px-2 py-1 hover:bg-white/25">sair da empresa</button>
      </form>
    @endif
    <button type="button" id="fbre-net" class="flex items-center gap-1.5 rounded-lg px-2 py-1 text-[11px] font-semibold bg-white/10" title="Estado da conexão"><span class="inline-block w-2 h-2 rounded-full bg-white/60"></span>…</button>
    @if(!$previewAs)<form method="post" action="{{ route('logout') }}" class="hidden sm:block">@csrf
      <button class="text-xs rounded-lg bg-white/10 px-3 py-1.5 hover:bg-white/20">Sair</button>
    </form>@endif
  </div>

  {{-- ===== MENU HORIZONTAL (desktop) ===== --}}
  <nav class="hidden lg:block bg-white border-b border-slate-200" x-data="{ aberto:null }" @click.outside="aberto=null">
    <div class="mx-auto max-w-[1440px] px-4 flex items-stretch h-12 gap-1">
      @if(!$semBemVindo)<a href="{{ route('inicio') }}"
         class="flex items-center gap-2 rounded-lg px-3 py-2 text-[13px] font-semibold whitespace-nowrap transition hover:bg-slate-50"
         style="{{ $ativo(route('inicio')) ? 'color:'.$cor1.';background:color-mix(in srgb,'.$cor1.' 12%,white);box-shadow:inset 0 -3px 0 '.$cor1 : 'color:#334155' }}">
        <span class="text-base">👋</span>Bem Vindo
      </a>@endif
      @foreach($grupos as $titulo => $itens)
        @php [$gi,$gc] = $metaGrupo[$titulo] ?? ['•','#475569']; $temAtivo = collect($itens)->contains(fn($it)=>$ativo($it['r'])); @endphp
        <div class="relative flex items-center">
          <button type="button" @click="aberto = (aberto==='{{ $titulo }}' ? null : '{{ $titulo }}')"
            class="flex items-center gap-2 rounded-lg px-3 py-2 text-[13px] font-semibold whitespace-nowrap transition hover:bg-slate-50"
            :class="aberto==='{{ $titulo }}' ? 'bg-slate-50' : ''"
            style="{{ $temAtivo ? 'color:'.$gc.';background:color-mix(in srgb,'.$gc.' 12%,white);box-shadow:inset 0 -3px 0 '.$gc : 'color:#334155' }}">
            <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $gc }}"></span>
            <span class="text-base">{{ $gi }}</span>{{ $titulo }}
            @php $badge = collect($itens)->sum(fn($it)=>(int)($it['b'] ?? 0)); @endphp
            @if($badge)<span class="min-w-[18px] text-center rounded-full bg-red-500 text-white text-[10px] font-bold px-1 py-0.5">{{ $badge }}</span>@endif
            <svg class="w-3.5 h-3.5 opacity-50 transition" :class="aberto==='{{ $titulo }}'?'rotate-180':''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
          </button>
          <div x-show="aberto==='{{ $titulo }}'" x-cloak x-transition.origin.top.left
               class="absolute left-0 top-full mt-1 z-50 w-60 rounded-xl bg-white shadow-xl border border-slate-100 p-1.5">
            <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider" style="color:{{ $gc }}">{{ $titulo }}</div>
            @foreach($itens as $it)
              <a href="{{ $it['r'] }}" @if(!empty($it['blank'])) target="_blank" @endif
                 class="flex items-center gap-2.5 rounded-lg px-2.5 py-2.5 text-sm font-medium transition
                        {{ $ativo($it['r']) ? 'text-white' : 'text-slate-700 hover:bg-slate-50' }}"
                 @if($ativo($it['r'])) style="background:{{ $gc }}" @endif>
                <span class="text-lg w-6 text-center">{{ $it['i'] }}</span>{{ $it['l'] }}
                @if(!empty($it['b']))<span class="ml-auto min-w-[18px] text-center rounded-full bg-red-500 text-white text-[10px] font-bold px-1 py-0.5">{{ $it['b'] }}</span>@endif
              </a>
            @endforeach
          </div>
        </div>
      @endforeach
    </div>
  </nav>
</header>

{{-- ===== GAVETA (mobile) ===== --}}
<aside class="no-print fixed inset-y-0 left-0 z-40 w-72 bg-white shadow-2xl transform transition lg:hidden flex flex-col"
       :class="menu ? 'translate-x-0' : '-translate-x-full'" x-cloak>
  <div class="flex items-center justify-between p-4 border-b">
    <span class="font-bold">Menu</span>
    <button @click="menu=false" class="p-2">✕</button>
  </div>
  <nav class="p-3 space-y-3 text-sm flex-1 overflow-y-auto overscroll-contain pb-8">
    @if(!$semBemVindo)<a href="{{ route('inicio') }}" @if($ativo(route('inicio'))) style="border-left:4px solid var(--cor1)" @endif class="flex items-center gap-3 rounded-xl px-3 py-3 font-medium {{ $ativo(route('inicio')) ? 'bg-slate-100 text-[var(--cor1)] font-semibold' : 'text-slate-700' }}">
      <span class="text-lg w-6 text-center">👋</span>Bem Vindo
    </a>@endif
    @foreach($grupos as $titulo => $itens)
      <div>
        <div class="px-3 pb-1 text-[10.5px] font-bold uppercase tracking-wider text-slate-400">{{ $titulo }}</div>
        @foreach($itens as $it)
          <a href="{{ $it['r'] }}" @if(!empty($it['blank'])) target="_blank" @endif @if($ativo($it['r'])) style="border-left:4px solid var(--cor1)" @endif class="flex items-center gap-3 rounded-xl px-3 py-3 font-medium {{ $ativo($it['r']) ? 'bg-slate-100 text-[var(--cor1)] font-semibold' : 'text-slate-700' }}">
            <span class="text-lg w-6 text-center">{{ $it['i'] }}</span>{{ $it['l'] }}
            @if(!empty($it['b']))<span class="ml-auto min-w-[20px] text-center rounded-full bg-red-500 text-white text-[11px] font-bold px-1.5 py-0.5">{{ $it['b'] }}</span>@endif</a>
        @endforeach
      </div>
    @endforeach
    @if(!$previewAs)<form method="post" action="{{ route('logout') }}" class="pt-2 border-t">@csrf
      <button class="w-full text-left flex items-center gap-3 rounded-xl px-3 py-3 font-medium text-red-600"><span class="w-6 text-center">⏻</span>Sair</button>
    </form>@endif
  </nav>
</aside>
<div class="no-print fixed inset-0 z-30 bg-black/40 lg:hidden" x-show="menu" @click="menu=false" x-cloak></div>

{{-- ===== CONTEÚDO ===== --}}
<main class="mx-auto max-w-[1440px] p-3 sm:p-4 lg:p-4 pb-24 lg:pb-4">
  @if(session('ok'))
    <div class="mb-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm no-print whitespace-pre-wrap">{{ session('ok') }}</div>
  @endif
  @if(session('erro'))
    <div class="mb-3 rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm no-print whitespace-pre-wrap">{{ session('erro') }}</div>
  @endif
  @if($errors->any())
    <div class="mb-3 rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm no-print">
      <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif
  @if(session('simula_admin_id'))
    <div class="mb-3 rounded-xl bg-amber-100 border border-amber-300 text-amber-900 px-4 py-2.5 text-sm no-print flex items-center justify-between gap-3 flex-wrap">
      <span>🧪 <b>Simulação</b> — você está entrando como <b>{{ auth()->user()->name }}</b>. É só uma demonstração.</span>
      <form method="post" action="{{ route('simulacao.sair') }}">@csrf<button class="btn btn-sm text-white" style="background:#b45309">↩️ Sair da simulação</button></form>
    </div>
  @endif
  @yield('conteudo')
  {{-- versão do sistema (atualiza a cada deploy — serve pra conferir se a atualização entrou) --}}
  <div class="no-print text-center text-[10px] text-slate-400 py-4 mb-20 lg:mb-2"><a href="https://geratec.com.br/" target="_blank" rel="noopener" class="hover:underline">GERATEC</a> · <span class="select-all">{{ \App\Support\Versao::ATUAL }}</span></div>
</main>

{{-- ===== BARRA INFERIOR (mobile / POS) ===== --}}
<nav class="no-print fixed bottom-0 inset-x-0 z-30 bg-white border-t border-slate-200 lg:hidden safe-b">
  <div class="grid grid-cols-4 text-[11px] font-semibold text-slate-500">
    @php $tab = fn($r,$l,$i) => '<a href="'.$r.'" class="flex flex-col items-center gap-0.5 py-2 '.($ativo($r)?'text-[var(--cor1)]':'').'"><span class="text-xl">'.$i.'</span>'.$l.'</a>';
      $tabs = [];
      if(!$semBemVindo) $tabs[] = $tab(route('inicio'), 'Início', '🏠');
      if($u->podeVerCaixa()) $tabs[] = $tab(route('caixa.index'), 'Caixa', '💰');
      if($u->podeRelatorio()) $tabs[] = $tab(route('relatorio.meu'), 'Relatório', '📋');
      foreach(array_slice($tabs,0,3) as $t) echo $t; @endphp
    <button @click="menu=true" class="flex flex-col items-center gap-0.5 py-2"><span class="text-xl">☰</span>Menu</button>
  </div>
</nav>

@if($previewAs)
<script>
(function(){
  var pid = {{ $previewAs }};
  var _f = window.fetch ? window.fetch.bind(window) : null;
  if(_f){
    window.fetch = function(input, init){
      try{
        var url = (typeof input==='string') ? input : (input && input.url);
        if(url){
          var u = new URL(url, location.origin);
          if(u.origin===location.origin && !u.searchParams.has('preview_as')){
            u.searchParams.set('preview_as', pid);
            if(typeof input==='string'){ input = u.pathname+u.search+u.hash; }
            else { input = new Request(u.toString(), input); }
          }
        }
      }catch(e){}
      return _f(input, init);
    };
  }
  function withPreview(href){
    try{ var u=new URL(href, location.origin); if(u.origin!==location.origin) return href; u.searchParams.set('preview_as', pid); return u.pathname+u.search+u.hash; }catch(e){ return href; }
  }
  document.addEventListener('click', function(ev){
    var a = ev.target.closest ? ev.target.closest('a[href]') : null; if(!a) return;
    var href = a.getAttribute('href'); if(!href || href[0]==='#' || /^(javascript:|mailto:|tel:)/i.test(href)) return;
    if(a.target && a.target!=='_self') return;
    a.setAttribute('href', withPreview(a.href));
  }, true);
  document.addEventListener('submit', function(ev){
    var f = ev.target; if(!f || !f.tagName || f.tagName.toLowerCase()!=='form') return;
    if(!f.querySelector('input[name=preview_as]')){ var i=document.createElement('input'); i.type='hidden'; i.name='preview_as'; i.value=pid; f.appendChild(i); }
  }, true);
})();
</script>
@endif
<script src="/js/fbre-offline.js?v={{ @filemtime(public_path('js/fbre-offline.js')) ?: '1' }}"></script>
<script>
  window.FBRE = {
    csrf: document.querySelector('meta[name=csrf-token]').content,
    moeda: v => (Number(v)||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'}),
    fmtMoney: v => (Number(v)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}),
    parseMoney: s => { const d=String(s||'').replace(/\D/g,''); return d ? parseInt(d,10)/100 : 0; },
    abrirImpressao: url => { const w = window.open(url, 'fbre_print', 'width=420,height=640'); if (w) w.focus(); },
    // true se for um CPF válido (11 dígitos + dígitos verificadores). Documento vazio ou que não tenha 11 dígitos (ex.: RG) não é CPF.
    soDigitos: s => String(s||'').replace(/\D/g,''),
    pareceCpf: s => FBRE.soDigitos(s).length === 11,
    cpfValido: s => {
      const c = FBRE.soDigitos(s);
      if (c.length !== 11 || /^(\d)\1{10}$/.test(c)) return false;
      let soma = 0; for (let i = 0; i < 9; i++) soma += parseInt(c[i],10) * (10 - i);
      let d1 = (soma * 10) % 11; if (d1 === 10) d1 = 0; if (d1 !== parseInt(c[9],10)) return false;
      soma = 0; for (let i = 0; i < 10; i++) soma += parseInt(c[i],10) * (11 - i);
      let d2 = (soma * 10) % 11; if (d2 === 10) d2 = 0; return d2 === parseInt(c[10],10);
    },
    // regra do sistema: documento opcional; se o usuário digitou algo com cara de CPF (11 dígitos), tem que ser válido.
    docOk: s => { const t = String(s||'').trim(); if (!t) return true; return FBRE.pareceCpf(t) ? FBRE.cpfValido(t) : true; },
    // formata o CPF enquanto digita: 000.000.000-00 (máx 11 dígitos)
    cpfMask: s => {
      const c = FBRE.soDigitos(s).slice(0,11);
      if (c.length > 9) return c.slice(0,3)+'.'+c.slice(3,6)+'.'+c.slice(6,9)+'-'+c.slice(9);
      if (c.length > 6) return c.slice(0,3)+'.'+c.slice(3,6)+'.'+c.slice(6);
      if (c.length > 3) return c.slice(0,3)+'.'+c.slice(3);
      return c;
    },
    // formata o celular/WhatsApp enquanto digita: (92) 98530-5017 (máx 11 dígitos)
    foneMask: s => {
      const d = FBRE.soDigitos(s).slice(0,11);
      if (d.length === 0) return '';
      if (d.length <= 2) return '('+d;
      if (d.length <= 6) return '('+d.slice(0,2)+') '+d.slice(2);
      if (d.length <= 10) return '('+d.slice(0,2)+') '+d.slice(2,6)+'-'+d.slice(6);
      return '('+d.slice(0,2)+') '+d.slice(2,7)+'-'+d.slice(7);
    },
    // valida CNPJ (14 dígitos + dígitos verificadores)
    cnpjValido: s => {
      const c = FBRE.soDigitos(s);
      if (c.length !== 14 || /^(\d)\1{13}$/.test(c)) return false;
      const calc = base => {
        let soma = 0, pos = base - 7;
        for (let i = base; i >= 1; i--) { soma += parseInt(c[base - i],10) * pos--; if (pos < 2) pos = 9; }
        const r = soma % 11; return r < 2 ? 0 : 11 - r;
      };
      return calc(12) === parseInt(c[12],10) && calc(13) === parseInt(c[13],10);
    },
    // formata CPF (000.000.000-00) ou CNPJ (00.000.000/0000-00) conforme os dígitos
    docMask: s => {
      const d = FBRE.soDigitos(s).slice(0,14);
      if (d.length <= 11) return FBRE.cpfMask(d);
      return d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5,8)+'/'+d.slice(8,12)+'-'+d.slice(12);
    },
    // CPF/CNPJ opcional: vazio OK; se digitou, tem que ser CPF(11) ou CNPJ(14) válido
    cpfCnpjOk: s => {
      const d = FBRE.soDigitos(s);
      if (d.length === 0) return true;
      if (d.length === 11) return FBRE.cpfValido(d);
      if (d.length === 14) return FBRE.cnpjValido(d);
      return false;
    },
  };
</script>
@stack('scripts')
</body>
</html>
