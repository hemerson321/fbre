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
  $emp = $empresa;
  $coresPorModulo = (bool) ($emp?->config('cores_por_modulo', true) ?? true);
  $corEmp = $emp?->cor_primaria ?? '#13295e';
  $modAtivos = $emp?->config('modulos_ativos');
  $modOn = fn ($m) => empty($modAtivos) || in_array($m, (array) $modAtivos, true);
  $corMod = function ($k) use ($emp, $coresPorModulo, $corEmp) {
    if (! $coresPorModulo) return $corEmp;
    return $emp?->config('cores_modulo.'.$k) ?: Modulos::hex($k);
  };

  $grupos = [];

  // PASSAGEM
  if ($u->temModulo('passagem') && $modOn('passagem')) {
    $it = [];
    if ($u->permite('passagem','cadastra')) $it[] = ['r'=>route('passagens.create'), 'l'=>'Nova passagem', 'i'=>'🎫'];
    if ($u->permite('passagem','ver'))      $it[] = ['r'=>route('passagens.index'), 'l'=>'Passagens / embarque', 'i'=>'🧾'];
    if ($u->permite('passagem','cadastra') && Route::has('orcamentos.index')) $it[] = ['r'=>route('orcamentos.index'), 'l'=>'Orçamento', 'i'=>'📝'];
    if ($u->permite('passagem','baixa'))    $it[] = ['r'=>route('passagens.scanner'), 'l'=>'Check-in / embarque', 'i'=>'📷'];
    if ($u->permite('passagem','cadastra') && Route::has('passagens.bloqueios')) $it[] = ['r'=>route('passagens.bloqueios'), 'l'=>'Travar poltronas', 'i'=>'🔒'];
    if (($u->isAdmin() || $u->isSuper()) && Route::has('passagens.transferencia')) $it[] = ['r'=>route('passagens.transferencia'), 'l'=>'Transferir transporte', 'i'=>'🔄'];
    if ($u->permite('passagem','ver') && $u->revendedorDono() !== null) $it[] = ['r'=>route('passagens.minhas-vendas'), 'l'=>'Minhas vendas', 'i'=>'📊'];
    if ($it) $grupos[] = ['nome'=>'Passagem', 'cor'=>$corMod('passagem'), 'itens'=>$it];
  }

  // CAMAROTE
  if ($u->temModulo('camarote') && $modOn('camarote')) {
    $it = [];
    if ($u->permite('camarote','cadastra')) $it[] = ['r'=>route('passagens.camarote.create'), 'l'=>'Vender suíte/camarote', 'i'=>'🛏️'];
    if (Route::has('passagens.camarote.lista')) $it[] = ['r'=>route('passagens.camarote.lista'), 'l'=>'Camarotes / embarque', 'i'=>'🧾'];
    if (Route::has('passagens.camarotes')) $it[] = ['r'=>route('passagens.camarotes'), 'l'=>'Ocupação / reservas', 'i'=>'🗺️'];
    if ($it) $grupos[] = ['nome'=>'Camarote', 'cor'=>$corMod('camarote'), 'itens'=>$it];
  }

  // ENCOMENDA
  if ($u->temModulo('encomenda') && $modOn('encomenda')) {
    $it = [];
    if ($u->permite('encomenda','cadastra')) $it[] = ['r'=>route('encomendas.create'), 'l'=>'Nova encomenda', 'i'=>'📦'];
    if (Route::has('encomendas.index')) $it[] = ['r'=>route('encomendas.index'), 'l'=>'Encomendas / baixa', 'i'=>'📋'];
    if ($it) $grupos[] = ['nome'=>'Encomenda', 'cor'=>$corMod('encomenda'), 'itens'=>$it];
  }

  // FRETE
  if ($u->temModulo('frete') && $modOn('frete')) {
    $it = [];
    if ($u->permite('frete','cadastra')) $it[] = ['r'=>route('fretes.create'), 'l'=>'Nova frete', 'i'=>'🚚'];
    if (Route::has('fretes.index')) $it[] = ['r'=>route('fretes.index'), 'l'=>'Fretes / entrega', 'i'=>'📋'];
    if ($it) $grupos[] = ['nome'=>'Frete', 'cor'=>$corMod('frete'), 'itens'=>$it];
  }

  // FINANCEIRO
  $fin = [];
  if ($u->podeVerCaixa())  $fin[] = ['r'=>route('caixa.index'), 'l'=>$u->caixaTodos()?'Caixa geral':'Meu caixa', 'i'=>'💰'];
  if ($u->podeRelatorio()) $fin[] = ['r'=>route('relatorio.meu'), 'l'=>$u->relatorioTodos()?'Relatório geral':'Meu relatório', 'i'=>'📋'];
  if ($fin) $grupos[] = ['nome'=>'Financeiro', 'cor'=>'#059669', 'itens'=>$fin];

  // ATENDIMENTO
  if ($u->permite('passagem','atendimento')) {
    $at = [
      ['r'=>route('atendimento.index'), 'l'=>'Atendimento (WhatsApp)', 'i'=>'💬'],
      ['r'=>route('atendimento.kanban'), 'l'=>'CRM · Funil', 'i'=>'📋'],
    ];
    $grupos[] = ['nome'=>'Atendimento', 'cor'=>'#0ea5a4', 'itens'=>$at];
  }

  // ADMINISTRAÇÃO
  $adm = [];
  if ($u->isAdmin() || $u->isSuper()) {
    $adm[] = ['r'=>route('admin.usuarios.index'), 'l'=>'Usuários', 'i'=>'👥'];
    $adm[] = ['r'=>route('admin.cadastros'), 'l'=>'Cadastros gerais', 'i'=>'🗂️'];
    if ($modOn('camarote') && Route::has('admin.camarotes')) $adm[] = ['r'=>route('admin.camarotes'), 'l'=>'Suítes / Camarotes', 'i'=>'🛏️'];
    if ($u->temModulo('passagem') && Route::has('admin.rotas')) $adm[] = ['r'=>route('admin.rotas'), 'l'=>'Rotas e horários', 'i'=>'🗺️'];
    if ($u->temModulo('passagem') && Route::has('clientes-faturado')) $adm[] = ['r'=>route('clientes-faturado'), 'l'=>'Clientes faturado', 'i'=>'🧾'];
    $adm[] = ['r'=>route('admin.empresa'), 'l'=>'Minha empresa', 'i'=>'🏢'];
  }
  if ($u->isSuper() && ! config('fbre.single') && Route::has('super.empresas')) $adm[] = ['r'=>route('super.empresas'), 'l'=>'Empresas', 'i'=>'🛠️'];
  if ($adm) $grupos[] = ['nome'=>'Administração', 'cor'=>$corEmp, 'itens'=>$adm];
@endphp

<div class="max-w-5xl mx-auto space-y-4">

  {{-- saudação enxuta (sem card grandão) --}}
  <div class="flex items-center gap-3 pt-1">
    @if($empresa?->logo)<img onerror="this.style.display='none'" src="/{{ $empresa->logo }}" alt="" class="h-9 w-9 rounded-lg object-cover bg-white p-0.5 border border-slate-200 shrink-0">@endif
    <div class="text-[13px] text-slate-500">{{ $saud }}, <b class="text-slate-700">{{ $primeiro }}</b> · {{ $empresa?->nomeExibicao() ?? 'FBRE' }}</div>
  </div>

  {{-- grupos por módulo: caixa com borda e o nome na borda --}}
  @foreach($grupos as $g)
  <div class="relative rounded-2xl border-2 bg-white/40 px-4 pb-4 pt-5" style="border-color:{{ $g['cor'] }}">
    <span class="absolute -top-2.5 left-4 px-2 text-[13px] font-extrabold uppercase tracking-wider" style="color:{{ $g['cor'] }};background:#eef2f7">{{ $g['nome'] }}</span>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
      @foreach($g['itens'] as $it)
        <a href="{{ $it['r'] }}" class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 hover:shadow-md hover:-translate-y-0.5 hover:border-slate-300 transition">
          <span class="text-xl shrink-0">{{ $it['i'] }}</span>
          <span class="font-semibold text-[13px] text-slate-700 leading-tight">{{ $it['l'] }}</span>
        </a>
      @endforeach
    </div>
  </div>
  @endforeach

  <p class="text-xs text-slate-400 text-center pt-1">Dúvida? Fale com o administrador ou com o suporte da GERATEC.</p>
</div>
@endsection
