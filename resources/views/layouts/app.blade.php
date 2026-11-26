@php
    $empresa = \App\Support\Tenant::empresa();
    $u = auth()->user();
    $previewAs = (int) request('preview_as');
    $cor1 = $empresa?->cor_primaria ?? '#0b1a33';
    $cor2 = $empresa?->cor_secundaria ?? '#00a8e8';
    $cor3 = $empresa?->cor_terciaria ?? '#f5a623';
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
  colors: { brand: { DEFAULT: '{{ $cor1 }}', accent: '{{ $cor2 }}', line: '{{ $cor3 }}' } }
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
  :root { --cor1: {{ $cor1 }}; --cor2: {{ $cor2 }}; --cor3: {{ $cor3 }}; }
  html { -webkit-tap-highlight-color: transparent; }
  html, body { overflow-x: hidden; max-width: 100%; }
  body { background: #eef2f7; }
  .btn { @apply inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 font-semibold text-sm transition active:scale-[.98] disabled:opacity-50; min-height: 48px; }
  .btn-primary { background: var(--cor2); color: #fff; }
  .btn-accent { background: var(--cor2); color: #fff; }
  .text-cor3 { color: var(--cor3); }
  .bg-cor3 { background: var(--cor3); }
  .border-cor3 { border-color: var(--cor3); }
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
  @media print { .no-print { display: none !important; } body { background: #fff; } * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; } }
</style>
@stack('head')
</head>
<body class="font-sans text-slate-800 min-h-screen" x-data="{ menu:false }">
@php $embed = request()->boolean('embed'); @endphp
@php
  // ---------- menu por módulo (uma definição, usada no topo e na gaveta) ----------
  $grupos = [];
  $semBemVindo = $u->ehDonoPuro();
  $g = [];
  if ($u->permite('encomenda','cadastra')) $g[] = ['r'=>route('encomendas.create'), 'l'=>'Nova encomenda', 'i'=>'➕'];
  if ($u->permite('encomenda','ver')) $g[] = ['r'=>route('encomendas.index'), 'l'=>'Encomendas / baixa', 'i'=>'📦'];
  if ($u->permite('encomenda','precadastro')) $g[] = ['r'=>route('pre.index'), 'l'=>'Pré-cadastros', 'i'=>'🪧', 'bk'=>'precadastro', 'b'=>(\App\Models\PreCadastro::where('status','pendente')->count() ?: null)];
  if ($g && $modOn('encomenda')) $grupos['Encomendas'] = $g;
  // Passagem (só aparece se o operador tiver o módulo liberado)
  $g = [];
  if ($u->permite('passagem','cadastra')) $g[] = ['r'=>route('passagens.create'), 'l'=>'Nova passagem', 'i'=>'🎫'];
  if ($u->permite('passagem','ver'))  $g[] = ['r'=>route('passagens.index'), 'l'=>'Passagens / embarque', 'i'=>'🧾'];
  if ($u->permite('passagem','baixa')) $g[] = ['r'=>route('passagens.scanner'), 'l'=>'Check-in / embarque', 'i'=>'📷'];
  if ($u->permite('passagem','cadastra') && Route::has('clientes-faturado')) $g[] = ['r'=>route('clientes-faturado'), 'l'=>'Clientes faturado', 'i'=>'🧾'];
  if ($u->permite('passagem','ver') && $u->revendedorDono() !== null) $g[] = ['r'=>route('passagens.minhas-vendas'), 'l'=>'Minhas vendas', 'i'=>'📊'];
  if ($u->ehRevendedor()) $g[] = ['r'=>route('meus-acertos'), 'l'=>'Acertos', 'i'=>'🤝'];
  // Caixa e Prestação de contas: direto no menu Passagens (não mais dentro do Financeiro)
  if ($u->podeVerCaixa()) $g[] = ['r'=>route('caixa.index'), 'l'=>$u->caixaTodos()?'Caixa geral':'Meu caixa', 'i'=>'💰', 'c'=>'#059669', 'sep'=>true];
  if ($u->podeVerCaixa() && $u->caixaTodos()) $g[] = ['r'=>route('caixa.index', ['foco'=>'prestacoes']), 'l'=>'Prestação de contas', 'i'=>'🧾', 'c'=>'#d97706'];
  if ($g && $modOn('passagem')) $grupos['Passagens'] = $g;
  // Ferramentas: atendimento, CRM, travar poltronas e transferir transporte (saíram do Passagens pra organizar)
  $ft = [];
  if ($u->permite('passagem','cadastra') && Route::has('orcamentos.index')) $ft[] = ['r'=>route('orcamentos.index'), 'l'=>'Orçamentos', 'i'=>'📝'];
  if ($u->permite('passagem','cadastra') && Route::has('passagens.bloqueios')) $ft[] = ['r'=>route('passagens.bloqueios'), 'l'=>'Travar poltronas', 'i'=>'🔒'];
  if (($u->isAdmin() || $u->isSuper()) && Route::has('passagens.transferencia')) $ft[] = ['r'=>route('passagens.transferencia'), 'l'=>'Transferir transporte', 'i'=>'🔄'];
  if ($u->permite('passagem','atendimento')) $ft[] = ['r'=>route('atendimento.index'), 'l'=>'Atendimento (WhatsApp)', 'i'=>'💬', 'bk'=>'atendimento', 'b'=>(\App\Models\Conversa::where('status','aguardando')->count() ?: null)];
  if ($u->permite('passagem','atendimento')) $ft[] = ['r'=>route('atendimento.kanban'), 'l'=>'CRM · Funil de clientes', 'i'=>'📋'];
  if ($ft && $modOn('passagem')) $grupos['Ferramentas'] = $ft;
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
  // Caixa, Prestação de Contas e Relatório agora ficam DENTRO do menu Passagens (ver bloco Passagem acima). Sem botões soltos no topo.
  $gruposDiretos = [];
  // Financeiro: Relatório (operação) e, pra admin/perm_financeiro, Contas a receber + Clientes faturado + Entradas e saídas. (Caixa e Prestação foram pro menu Passagens.)
  $podeFinModulo = $u->isAdmin() || $u->isSuper() || $u->perm_financeiro;
  // Cobrança vencida: faturados a receber que já passaram do prazo (created_at + cobrar_dias). Vira notificação no menu.
  $cobrancaVenc = 0;
  if ($podeFinModulo) { try { $cobrancaVenc = (int) \App\Models\Passagem::where('forma','faturado')->where('cancelada',false)->where('transferida',false)->where('devido','>',0.009)->whereRaw('DATE_ADD(created_at, INTERVAL COALESCE(cobrar_dias,30) DAY) <= NOW()')->count(); } catch (\Throwable $e) {} }
  if ($podeFinModulo || $u->podeVerCaixa() || $u->podeRelatorio()) {
    $gf = [];
    if ($podeFinModulo && Route::has('financeiro.contas-receber')) $gf[] = ['r'=>route('financeiro.contas-receber'), 'l'=>'Contas a receber', 'i'=>'💵', 'c'=>'#0d9488', 'b'=>($cobrancaVenc ?: null), 'bk'=>'cobranca'];
    if ($podeFinModulo && Route::has('clientes-faturado')) $gf[] = ['r'=>route('clientes-faturado'), 'l'=>'Clientes faturado', 'i'=>'🧾', 'c'=>'#0d9488'];
    if (($u->isAdmin() || $u->isSuper()) && Route::has('admin.movimentos')) $gf[] = ['r'=>route('admin.movimentos'), 'l'=>'Entradas e saídas', 'i'=>'↕️', 'c'=>'#0d9488', 'sep'=>! empty($gf)];
    // Relatório geral fica por último, abaixo de Entradas e saídas (pedido do cliente)
    if ($u->podeRelatorio()) $gf[] = ['r'=>route('relatorio.meu'), 'l'=>$u->relatorioTodos()?'Relatório geral':'Meu relatório', 'i'=>'📋', 'c'=>'#4f46e5', 'sep'=>! empty($gf)];
    if ($gf) $grupos['Financeiro'] = $gf;
  }
  // Desktop: Financeiro e Ferramentas também abrem como submenu (flyout) DENTRO do Passagens. No mobile continuam como grupos.
  $subPassagens = [];
  if (! empty($grupos['Financeiro'] ?? [])) $subPassagens['Financeiro'] = $grupos['Financeiro'];
  if (! empty($grupos['Ferramentas'] ?? [])) $subPassagens['Ferramentas'] = $grupos['Ferramentas'];
  if ($u->ehDonoTransporte()) $grupos['Meu transporte'] = [['r'=>route('meu-transporte'), 'l'=>'Meu transporte', 'i'=>'🚤']];
  if ($u->podeMarketing()) $grupos['Mídia Indoor'] = [
      ['r'=>route('marketing.signage'), 'l'=>'Tela indoor', 'i'=>'🖼️'],
      ['r'=>route('marketing.index'), 'l'=>'Áudio indoor', 'i'=>'🔊'],
  ];
  if ($u->isAdmin() || $u->isSuper()) {
      $subAdmin = [];
      $cadast = [];
      $cadast[] = ['r'=>route('admin.cadastros'), 'l'=>'Cadastros gerais', 'i'=>'🗂️'];
      $cadast[] = ['r'=>route('admin.transportes'), 'l'=>'Transportes', 'i'=>'⛴️'];
      if ($u->temModulo('passagem')) $cadast[] = ['r'=>route('admin.rotas'), 'l'=>'Rotas e horários', 'i'=>'🗺️'];
      if ($modOn('camarote')) $cadast[] = ['r'=>route('admin.camarotes'), 'l'=>'Suítes / Camarotes', 'i'=>'🛏️'];
      $subAdmin['Cadastros'] = $cadast;
      $subAdmin['Usuários & Empresa'] = [
          ['r'=>route('admin.usuarios.index'), 'l'=>'Usuários', 'i'=>'👥'],
          ['r'=>route('admin.empresa'), 'l'=>'Minha empresa', 'i'=>'🏢'],
      ];
      $subAdmin['Integrações'] = [
          ['r'=>route('admin.whatsapp'), 'l'=>'WhatsApp', 'i'=>'💬'],
          ['r'=>route('admin.pagamentos'), 'l'=>'Pagamentos (APIs)', 'i'=>'💳'],
          ['r'=>route('admin.llm'), 'l'=>'Provedores de IA', 'i'=>'🧠'],
          ['r'=>route('admin.agentes'), 'l'=>'Agentes IA', 'i'=>'🤖'],
      ];
      $subAdmin['Painel'] = [
          ['r'=>route('admin.dashboard'), 'l'=>'Dashboard', 'i'=>'📊'],
          ['r'=>route('simulacao.telas'), 'l'=>'Simulação (demo)', 'i'=>'🧪'],
      ];
      $admFlat = [];
      foreach ($subAdmin as $cat => $its) { $admFlat[] = ['sec'=>$cat]; foreach ($its as $it) $admFlat[] = $it; }
      $grupos['Administração'] = $admFlat;
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
    'Mídia Indoor'  => ['📡', '#be185d'],
    'GERATEC'       => ['🛠️', '#334155'],
  ];
  if (! $coresPorModulo) { foreach ($metaGrupo as $k => $v) { $metaGrupo[$k][1] = $cor1; } }
  // Cores fixas do financeiro (sempre diferentes entre si, cada uma com a sua página).
  $metaGrupo['Caixa'] = ['💰', '#059669'];
  $metaGrupo['Prestação de Contas'] = ['🧾', '#d97706'];
  $metaGrupo['Relatório'] = ['📋', '#4f46e5'];
  $metaGrupo['Financeiro'] = ['💵', '#0d9488'];
  $metaGrupo['Ferramentas'] = ['🛠️', '#7c3aed'];
  $metaGrupo['Cadastros'] = ['🗂️', '#334155'];
  $metaGrupo['Usuários & Empresa'] = ['👥', '#334155'];
  $metaGrupo['Integrações'] = ['🔌', '#334155'];
  $metaGrupo['Painel'] = ['📊', '#334155'];
  $ativo = function ($r) {
      if (request()->url() !== strtok($r, '?')) return false;
      parse_str(parse_url($r, PHP_URL_QUERY) ?: '', $qq);
      if (! isset($qq['foco']) && request()->filled('foco')) return false; // /caixa?foco=prestacoes não acende o botão Caixa
      foreach ($qq as $k => $v) { if ((string) request()->input($k) !== (string) $v) return false; }
      return true;
  };
@endphp

@unless($embed)
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

  {{-- linha da marca (cor3) dividindo o topo --}}
  <div style="height:3px;background:var(--cor3)"></div>

  {{-- ===== MENU HORIZONTAL (desktop) ===== --}}
  <nav class="hidden lg:block bg-white border-b border-slate-200" x-data="{ aberto:null, sub:null }" @click.outside="aberto=null;sub=null">
    <div class="mx-auto max-w-[1440px] px-4 flex items-stretch h-12 gap-1">
      @if(!$semBemVindo)<a href="{{ route('inicio') }}"
         class="flex items-center gap-2 rounded-lg px-3 py-2 text-[13px] font-semibold whitespace-nowrap transition hover:bg-slate-50"
         style="{{ $ativo(route('inicio')) ? 'color:'.$cor2.';background:color-mix(in srgb,'.$cor2.' 12%,white);box-shadow:inset 0 -3px 0 '.$cor2 : 'color:#334155' }}">
        <span class="text-base">👋</span>Bem Vindo
      </a>@endif
      @foreach($grupos as $titulo => $itens)
        @continue(in_array($titulo, ['Financeiro','Ferramentas'], true))
        @php [$gi,$gc] = $metaGrupo[$titulo] ?? ['•','#475569']; $chkItens = ($titulo==='Passagens' && !empty($subPassagens)) ? collect($itens)->merge(collect($subPassagens)->flatten(1)) : collect($itens); $temAtivo = $chkItens->contains(fn($it)=>!empty($it['r']) && $ativo($it['r'])); @endphp
        @if(in_array($titulo, $gruposDiretos ?? [], true) && count($itens) === 1)
        <a href="{{ $itens[0]['r'] }}"
           class="flex items-center gap-2 rounded-lg px-3 py-2 my-1 text-[13px] font-bold whitespace-nowrap transition hover:brightness-95"
           style="{{ $temAtivo ? 'color:#fff;background:'.$gc.';box-shadow:0 2px 8px -2px '.$gc : 'color:'.$gc.';background:color-mix(in srgb,'.$gc.' 10%,white)' }}">
          <span class="text-base">{{ $gi }}</span>{{ $titulo }}
        </a>
        @continue
        @endif
        <div class="relative flex items-center" @mouseenter="aberto='{{ $titulo }}'" @mouseleave="aberto=null">
          <button type="button" @click="aberto = (aberto==='{{ $titulo }}' ? null : '{{ $titulo }}')"
            class="flex items-center gap-2 rounded-lg px-3 py-2 text-[13px] font-semibold whitespace-nowrap transition hover:bg-slate-50"
            :class="aberto==='{{ $titulo }}' ? 'bg-slate-50' : ''"
            style="{{ $temAtivo ? 'color:'.$gc.';background:color-mix(in srgb,'.$gc.' 12%,white);box-shadow:inset 0 -3px 0 '.$gc : 'color:#334155' }}">
            <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $gc }}"></span>
            <span class="text-base">{{ $gi }}</span>{{ $titulo }}
            @php $subAll = ($titulo==='Passagens' && !empty($subPassagens)) ? collect($subPassagens)->flatten(1) : collect(); $badge = (int) collect($itens)->sum(fn($it)=>(int)($it['b'] ?? 0)) + (int) $subAll->sum(fn($it)=>(int)($it['b'] ?? 0)); $bkeys = collect($itens)->pluck('bk')->filter()->merge($subAll->pluck('bk')->filter())->unique()->implode(' '); @endphp
            @if($bkeys)<span data-badge-keys="{{ $bkeys }}" class="min-w-[18px] text-center rounded-full bg-red-500 text-white text-[10px] font-bold px-1 py-0.5" @if(!$badge) style="display:none" @endif>{{ $badge ?: '' }}</span>@elseif($badge)<span class="min-w-[18px] text-center rounded-full bg-red-500 text-white text-[10px] font-bold px-1 py-0.5">{{ $badge }}</span>@endif
            <svg class="w-3.5 h-3.5 opacity-50 transition" :class="aberto==='{{ $titulo }}'?'rotate-180':''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
          </button>
          <div x-show="aberto==='{{ $titulo }}'" x-cloak x-transition.origin.top.left
               class="absolute left-0 top-full mt-1 z-50 w-60 rounded-xl bg-white shadow-xl border border-slate-100 p-1.5 {{ in_array($titulo, ['Passagens','Administração'], true) ? '' : 'max-h-[calc(100vh-6rem)] overflow-y-auto' }}">
            <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider" style="color:{{ $gc }}">{{ $titulo }}</div>
            @unless($titulo==='Administração')
            @foreach($itens as $it)
              @if(!empty($it['sec']))<div class="px-2.5 pt-2.5 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $it['sec'] }}</div>@continue @endif
              @php
                $ic = $it['c'] ?? null; $at = $ativo($it['r']);
                if ($at) { $cls = 'font-bold'; $sty = 'color:'.($ic ?: $gc).';box-shadow:inset 3px 0 0 '.($ic ?: $gc); }
                elseif ($ic) { $cls = 'font-medium hover:bg-slate-50'; $sty = 'color:'.$ic; }
                else { $cls = 'text-slate-600 hover:bg-slate-50 font-medium'; $sty = ''; }
              @endphp
              @if(!empty($it['sep']))<div class="my-1 border-t border-slate-100"></div>@endif
              <a href="{{ $it['r'] }}" @if(!empty($it['blank'])) target="_blank" @endif @if($sty) style="{{ $sty }}" @endif
                 class="flex items-center gap-2.5 rounded-lg px-2.5 py-2.5 text-sm transition {{ $cls }}">
                <span class="text-lg w-6 text-center">{{ $it['i'] }}</span>{{ $it['l'] }}
                @if(!empty($it['bk']))<span data-badge-item="{{ $it['bk'] }}" class="ml-auto min-w-[18px] text-center rounded-full bg-red-500 text-white text-[10px] font-bold px-1 py-0.5" @if(empty($it['b'])) style="display:none" @endif>{{ $it['b'] ?: '' }}</span>@elseif(!empty($it['b']))<span class="ml-auto min-w-[18px] text-center rounded-full bg-red-500 text-white text-[10px] font-bold px-1 py-0.5">{{ $it['b'] }}</span>@endif
              </a>
            @endforeach
            @endunless
            @php $subsDoGrupo = $titulo==='Passagens' ? ($subPassagens ?? []) : ($titulo==='Administração' ? ($subAdmin ?? []) : []); @endphp
            @if(!empty($subsDoGrupo))
              @foreach($subsDoGrupo as $subT => $subItens)
                @php [$si,$sc] = $metaGrupo[$subT] ?? ['•','#475569']; $sb = (int) collect($subItens)->sum(fn($it)=>(int)($it['b'] ?? 0)); $sbk = collect($subItens)->pluck('bk')->filter()->implode(' '); @endphp
                <div class="relative" @mouseenter="sub='{{ $subT }}'" @mouseleave="sub=null">
                  <div class="flex items-center gap-2.5 rounded-lg px-2.5 py-2.5 text-sm font-bold cursor-default transition hover:bg-slate-50" :class="sub==='{{ $subT }}' ? 'bg-slate-50' : ''" style="color:{{ $sc }}">
                    <span class="text-lg w-6 text-center">{{ $si }}</span>{{ $subT }}
                    @if($sbk)<span data-badge-keys="{{ $sbk }}" class="min-w-[18px] text-center rounded-full bg-red-500 text-white text-[10px] font-bold px-1 py-0.5" @if(!$sb) style="display:none" @endif>{{ $sb ?: '' }}</span>@elseif($sb)<span class="min-w-[18px] text-center rounded-full bg-red-500 text-white text-[10px] font-bold px-1 py-0.5">{{ $sb }}</span>@endif
                    <svg class="w-4 h-4 opacity-50 ml-auto" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>
                  </div>
                  <div x-show="sub==='{{ $subT }}'" x-cloak x-transition.origin.top.left class="absolute left-full top-0 ml-1 z-50 w-60 rounded-xl bg-white shadow-xl border border-slate-100 p-1.5">
                    <div class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider" style="color:{{ $sc }}">{{ $subT }}</div>
                    @foreach($subItens as $it)
                      @php
                        $ic = $it['c'] ?? null; $at = $ativo($it['r']);
                        if ($at) { $cls = 'font-bold'; $sty = 'color:'.($ic ?: $sc).';box-shadow:inset 3px 0 0 '.($ic ?: $sc); }
                        elseif ($ic) { $cls = 'font-medium hover:bg-slate-50'; $sty = 'color:'.$ic; }
                        else { $cls = 'text-slate-600 hover:bg-slate-50 font-medium'; $sty = ''; }
                      @endphp
                      @if(!empty($it['sep']))<div class="my-1 border-t border-slate-100"></div>@endif
                      <a href="{{ $it['r'] }}" @if($sty) style="{{ $sty }}" @endif class="flex items-center gap-2.5 rounded-lg px-2.5 py-2.5 text-sm transition {{ $cls }}">
                        <span class="text-lg w-6 text-center">{{ $it['i'] }}</span>{{ $it['l'] }}
                        @if(!empty($it['bk']))<span data-badge-item="{{ $it['bk'] }}" class="ml-auto min-w-[18px] text-center rounded-full bg-red-500 text-white text-[10px] font-bold px-1 py-0.5" @if(empty($it['b'])) style="display:none" @endif>{{ $it['b'] ?: '' }}</span>@elseif(!empty($it['b']))<span class="ml-auto min-w-[18px] text-center rounded-full bg-red-500 text-white text-[10px] font-bold px-1 py-0.5">{{ $it['b'] }}</span>@endif
                      </a>
                    @endforeach
                  </div>
                </div>
              @endforeach
            @endif
          </div>
        </div>
      @endforeach
    </div>
  </nav>
</header>
@endunless

@unless($embed)
{{-- ===== GAVETA (mobile) ===== --}}
<aside class="no-print fixed inset-y-0 left-0 z-40 w-72 bg-white shadow-2xl transform transition lg:hidden flex flex-col"
       :class="menu ? 'translate-x-0' : '-translate-x-full'" x-cloak>
  <div class="flex items-center justify-between p-4 border-b">
    <span class="font-bold">Menu</span>
    <button @click="menu=false" class="p-2">✕</button>
  </div>
  <nav class="p-3 space-y-3 text-sm flex-1 overflow-y-auto overscroll-contain pb-8">
    @if(!$semBemVindo)<a href="{{ route('inicio') }}" @if($ativo(route('inicio'))) style="border-left:4px solid var(--cor3)" @endif class="flex items-center gap-3 rounded-xl px-3 py-3 font-medium {{ $ativo(route('inicio')) ? 'bg-slate-100 text-[var(--cor1)] font-semibold' : 'text-slate-700' }}">
      <span class="text-lg w-6 text-center">👋</span>Bem Vindo
    </a>@endif
    @foreach($grupos as $titulo => $itens)
      <div>
        <div class="px-3 pb-1 text-[10.5px] font-bold uppercase tracking-wider text-slate-400">{{ $titulo }}</div>
        @foreach($itens as $it)
          @if(!empty($it['sec']))<div class="px-3 pt-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $it['sec'] }}</div>@continue @endif
          @php
            $ic = $it['c'] ?? null; $at = $ativo($it['r']);
            if ($at && $ic) { $cls = 'font-bold text-white'; $sty = 'background:'.$ic; }
            elseif ($at) { $cls = 'bg-slate-100 text-[var(--cor1)] font-semibold'; $sty = 'border-left:4px solid var(--cor3)'; }
            elseif ($ic) { $cls = 'font-bold'; $sty = 'color:'.$ic.';background:color-mix(in srgb,'.$ic.' 10%,white);border-left:4px solid '.$ic; }
            else { $cls = 'text-slate-700 font-medium'; $sty = ''; }
          @endphp
          @if(!empty($it['sep']))<div class="my-2 border-t border-slate-100"></div>@endif
          <a href="{{ $it['r'] }}" @if(!empty($it['blank'])) target="_blank" @endif @if($sty) style="{{ $sty }}" @endif class="flex items-center gap-3 rounded-xl px-3 py-3 {{ $cls }}">
            <span class="text-lg w-6 text-center">{{ $it['i'] }}</span>{{ $it['l'] }}
            @if(!empty($it['bk']))<span data-badge-item="{{ $it['bk'] }}" class="ml-auto min-w-[20px] text-center rounded-full bg-red-500 text-white text-[11px] font-bold px-1.5 py-0.5" @if(empty($it['b'])) style="display:none" @endif>{{ $it['b'] ?: '' }}</span>@elseif(!empty($it['b']))<span class="ml-auto min-w-[20px] text-center rounded-full bg-red-500 text-white text-[11px] font-bold px-1.5 py-0.5">{{ $it['b'] }}</span>@endif</a>
        @endforeach
      </div>
    @endforeach
    @if(!$previewAs)<form method="post" action="{{ route('logout') }}" class="pt-2 border-t">@csrf
      <button class="w-full text-left flex items-center gap-3 rounded-xl px-3 py-3 font-medium text-red-600"><span class="w-6 text-center">⏻</span>Sair</button>
    </form>@endif
  </nav>
</aside>
@endunless
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
    // Abre um documento (recibo/orçamento) num pop-up grande (iframe). O próprio doc tem os botões de PDF/WhatsApp.
    abrirDoc: (url) => {
      const ov=document.createElement('div');
      ov.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.62);display:flex;flex-direction:column;padding:12px';
      ov.innerHTML='<div style="display:flex;justify-content:flex-end;margin-bottom:8px"><button type="button" aria-label="Fechar" style="background:#fff;border:0;border-radius:10px;width:42px;height:42px;font-size:22px;line-height:1;cursor:pointer">&times;</button></div><div style="flex:1;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,.4)"><iframe src="'+url+'" style="width:100%;height:100%;border:0"></iframe></div>';
      const close=()=>{ ov.remove(); document.removeEventListener('keydown',onk); };
      const onk=(e)=>{ if(e.key==='Escape') close(); };
      ov.querySelector('button').onclick=close;
      ov.addEventListener('click',(e)=>{ if(e.target===ov) close(); });
      document.addEventListener('keydown',onk);
      document.body.appendChild(ov);
    },
    moeda: v => (Number(v)||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'}),
    fmtMoney: v => (Number(v)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}),
    parseMoney: s => { const d=String(s||'').replace(/\D/g,''); return d ? parseInt(d,10)/100 : 0; },
    abrirImpressao: url => { const w = window.open(url, 'fbre_print', 'width=420,height=640'); if (w) w.focus(); },
    perguntarVias: () => { const n = prompt('Quantas vias imprimir?', '1'); if (n === null) return 1; const v = parseInt(n) || 1; return Math.max(1, Math.min(9, v)); },
    // Pergunta O QUE imprimir (tudo/bilhete/resumo) + quantas vias; devolve a query ou null (cancelou).
    perguntarImpressao: (titulo) => {
      const r = prompt((titulo || 'O que imprimir?') + '\n\n1 = Tudo (bilhetes + resumo)\n2 = Só os bilhetes\n3 = Só o resumo', '1');
      if (r === null) return null;
      const t = String(r).trim();
      const so = t === '3' ? 'resumo' : (t === '2' ? 'bilhete' : 'tudo');
      let vias = 1;
      if (so !== 'resumo') { const n = prompt('Quantas vias dos bilhetes?', '1'); if (n === null) return null; vias = Math.max(1, Math.min(9, parseInt(n) || 1)); }
      return 'so=' + so + '&vias=' + vias;
    },
    // Abre o comprovante perguntando o que imprimir. extra = params adicionais (ex.: 'modo=pdf').
    imprimirComprovante: (baseUrl, titulo, extra) => {
      const qs = window.FBRE.perguntarImpressao(titulo); if (qs === null) return;
      const sep = baseUrl.indexOf('?') >= 0 ? '&' : '?';
      window.FBRE.abrirImpressao(baseUrl + sep + qs + (extra ? '&' + extra : ''));
    },
    // Copia o bilhete (comprovante) como IMAGEM PNG pra colar no WhatsApp/outros.
    copiarBilheteImg: (btn, baseUrl) => {
      const prev = btn.innerHTML; btn.disabled = true; btn.innerHTML = '⏳';
      let done = false, resolveBlob, rejectBlob;
      const blobPromise = new Promise((res, rej) => { resolveBlob = res; rejectBlob = rej; });
      const ifr = document.createElement('iframe');
      ifr.style.cssText = 'position:fixed;left:-10000px;top:0;width:360px;height:10px;border:0;opacity:0';
      const cleanup = () => { window.removeEventListener('message', onMsg); try { ifr.remove(); } catch (e) {} };
      const restore = (t) => { btn.innerHTML = t; setTimeout(() => { btn.innerHTML = prev; btn.disabled = false; }, 1700); };
      const fail = (msg) => { if (done) return; done = true; cleanup(); try { rejectBlob(new Error('x')); } catch (e) {} restore('✖'); if (msg) alert(msg); };
      const ok = () => { if (done) return; done = true; cleanup(); restore('✅'); };
      const canCopy = !!(navigator.clipboard && window.ClipboardItem);
      const onMsg = async (ev) => {
        const d = ev.data; if (!d || !d.fbreRaster) return;
        const imgs = d.imagens || [];
        if (!imgs.length) { fail('Não consegui gerar a imagem do bilhete.'); return; }
        try { const blob = await FBRE._bilhetesPng(imgs); resolveBlob(blob); if (!canCopy) { FBRE._baixarPng(blob, 'bilhete.png'); ok(); } }
        catch (e) { fail('Falha ao montar a imagem.'); }
      };
      window.addEventListener('message', onMsg);
      if (canCopy) {
        try {
          navigator.clipboard.write([new ClipboardItem({ 'image/png': blobPromise })]).then(ok).catch(() => { blobPromise.then(b => { FBRE._baixarPng(b, 'bilhete.png'); ok(); }).catch(() => {}); });
        } catch (e) { blobPromise.then(b => { FBRE._baixarPng(b, 'bilhete.png'); ok(); }).catch(() => {}); }
      }
      document.body.appendChild(ifr);
      ifr.src = baseUrl + (baseUrl.indexOf('?') >= 0 ? '&' : '?') + 'raster=1';
      setTimeout(() => fail('Tempo esgotado ao gerar a imagem.'), 20000);
    },
    _bilhetesPng: async (imgs) => {
      const loaded = await Promise.all(imgs.map(src => new Promise((res, rej) => { const im = new Image(); im.onload = () => res(im); im.onerror = rej; im.src = src; })));
      const w = Math.max.apply(null, loaded.map(im => im.width));
      const gap = 16;
      const h = loaded.reduce((s, im) => s + im.height, 0) + gap * Math.max(0, loaded.length - 1);
      const cv = document.createElement('canvas'); cv.width = w; cv.height = h;
      const ctx = cv.getContext('2d'); ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, w, h);
      let y = 0; for (const im of loaded) { ctx.drawImage(im, 0, y, im.width, im.height); y += im.height + gap; }
      return await new Promise(res => cv.toBlob(res, 'image/png'));
    },
    _baixarPng: (blob, nome) => { const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = nome || 'bilhete.png'; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(url), 1500); },
    // Impressão DIRETA de uma página (etiqueta/via): rasteriza e manda pra fila da estação.
    // Se não houver estação ativa (ou erro), abre a janela de impressão como fallback.
    imprimirDiretoUrl: (btn, viewUrl, postUrl) => {
      const prev = btn.innerHTML; btn.disabled = true; btn.innerHTML = '⏳';
      let done = false;
      const ifr = document.createElement('iframe');
      ifr.style.cssText = 'position:fixed;left:-10000px;top:0;width:360px;height:10px;border:0;opacity:0';
      const cleanup = () => { window.removeEventListener('message', onMsg); try { ifr.remove(); } catch (e) {} };
      const restore = (t) => { btn.innerHTML = t; setTimeout(() => { btn.innerHTML = prev; btn.disabled = false; }, 1600); };
      const abrir = () => { btn.innerHTML = prev; btn.disabled = false; FBRE.abrirImpressao(viewUrl); };
      const onMsg = async (ev) => {
        const d = ev.data; if (!d || !d.fbreRaster) return;
        if (done) return; done = true;
        const imgs = d.imagens || [];
        cleanup();
        if (!imgs.length) { abrir(); return; }
        try {
          const r = await fetch(postUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': FBRE.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ imagens: imgs }) });
          const j = await r.json().catch(() => null);
          if (r.ok && j && j.ok) { restore('✅'); } else { abrir(); }
        } catch (e) { abrir(); }
      };
      window.addEventListener('message', onMsg);
      document.body.appendChild(ifr);
      ifr.src = viewUrl + (viewUrl.indexOf('?') >= 0 ? '&' : '?') + 'raster=1';
      setTimeout(() => { if (!done) { done = true; cleanup(); abrir(); } }, 15000);
    },
    // Check-in individual de 1 passageiro (toggle). Atualiza o botão e o contador na tela.
    checkinPax: async (btn, url) => {
      if (btn.dataset.busy) return; btn.dataset.busy = '1'; btn.style.opacity = '.5';
      const ON = 'chkbtn rounded-lg px-3 py-1.5 text-[13px] font-bold bg-emerald-600 text-white border border-emerald-600';
      const OFF = 'chkbtn rounded-lg px-3 py-1.5 text-[13px] font-bold bg-white text-slate-600 border border-slate-300';
      try {
        const r = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': FBRE.csrf, 'Accept': 'application/json' } });
        const d = await r.json().catch(() => null);
        if (!r.ok || !d || !d.ok) { alert((d && (d.message || d.erro)) || 'Nao consegui registrar o check-in.'); return; }
        const on = !!d.checkin;
        btn.className = on ? ON : OFF; btn.textContent = on ? '✓ embarcou' : 'Check-in';
        document.querySelectorAll('[data-chkcount]').forEach(el => el.textContent = d.feitos + '/' + d.total);
        document.querySelectorAll('[data-chkfull]').forEach(el => el.style.display = (d.feitos >= d.total) ? '' : 'none');
      } catch (e) { alert('Falha de conexao ao registrar o check-in.'); }
      finally { btn.style.opacity = ''; delete btn.dataset.busy; }
    },
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
<script>
(function(){
  var url='{{ route('sync.badges') }}';
  function setB(el,n){ if(!el) return; if(n>0){ el.textContent=n; el.style.display=''; } else { el.textContent=''; el.style.display='none'; } }
  async function tick(){
    try{
      var r=await fetch(url,{headers:{'X-Requested-With':'fetch'},credentials:'same-origin'}); if(!r.ok) return;
      var c=await r.json();
      document.querySelectorAll('[data-badge-item]').forEach(function(el){ setB(el, c[el.getAttribute('data-badge-item')]||0); });
      document.querySelectorAll('[data-badge-keys]').forEach(function(el){ var tot=0; el.getAttribute('data-badge-keys').split(/\s+/).forEach(function(k){ if(k) tot+=(c[k]||0); }); setB(el, tot); });
    }catch(e){}
  }
  setInterval(tick, 10000);
})();
</script>
@stack('scripts')
</body>
</html>
