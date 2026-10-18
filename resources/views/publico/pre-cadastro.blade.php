@php
  $cor1 = $empresa->cor_primaria ?? '#0b1a33';
  $cor2 = $empresa->cor_secundaria ?? '#00a8e8';
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Pré-cadastro · {{ $empresa->nomeExibicao() }}</title>
<link rel="icon" href="{{ $empresa->logo ? '/'.$empresa->logo : '/img/icon.png' }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<style type="text/tailwindcss">
  :root { --cor1: {{ $cor1 }}; --cor2: {{ $cor2 }}; }
  body { background:#eef2f7; }
  .field { @apply w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-base; min-height:50px; }
  .field:focus { @apply outline-none; border-color:var(--cor2); box-shadow:0 0 0 3px color-mix(in srgb, var(--cor2) 25%, transparent); }
  .lbl { @apply block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1; }
  .card { @apply bg-white rounded-2xl shadow-sm border border-slate-100 p-4; }
  [x-cloak]{display:none!important}
</style>
</head>
<body class="font-sans text-slate-800 min-h-screen">
<div class="max-w-lg mx-auto p-4 pb-28" x-data="pre()">
  {{-- topo --}}
  <div class="rounded-2xl p-5 text-white shadow-md mb-4" style="background:linear-gradient(120deg,var(--cor1),color-mix(in srgb,var(--cor1) 65%,var(--cor2)))">
    <div class="flex items-center gap-3">
      @if($empresa->logo)<img src="/{{ $empresa->logo }}" class="h-12 w-12 rounded-xl object-cover bg-white/90 p-1">@endif
      <div>
        <div class="font-extrabold text-lg leading-tight">{{ $empresa->nomeExibicao() }}</div>
        <div class="text-sm opacity-85">Pré-cadastro de encomenda</div>
      </div>
    </div>
    <p class="text-sm text-white/85 mt-3">Preencha aqui enquanto espera. No balcão, é só mostrar o <b>código</b> e o atendente finaliza rapidinho. 🚀</p>
  </div>

  @if($errors->any())
    <div class="mb-3 rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
      <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="post" action="{{ route('pre.store', $empresa->slug) }}" class="space-y-4" @submit="salvando=true">
    @csrf

    <div class="card space-y-3">
      <div class="font-bold text-sm" style="color:var(--cor1)">1 · Para onde vai</div>
      <div class="grid grid-cols-2 gap-2">
        <div><label class="lbl">Origem</label>
          <select name="origem" class="field" required>
            <option value="">—</option>
            @foreach($cidades as $c)<option value="{{ $c }}" @selected(old('origem')===$c)>{{ $c }}</option>@endforeach
          </select></div>
        <div><label class="lbl">Destino</label>
          <select name="destino" class="field" required>
            <option value="">—</option>
            @foreach($cidades as $c)<option value="{{ $c }}" @selected(old('destino')===$c)>{{ $c }}</option>@endforeach
          </select></div>
      </div>
    </div>

    <div class="card space-y-3">
      <div class="font-bold text-sm" style="color:var(--cor1)">2 · Quem envia e quem recebe</div>
      <div><label class="lbl">Seu nome (quem envia)</label><input name="remetente_nome" class="field" required value="{{ old('remetente_nome') }}" placeholder="Nome completo"></div>
      <div><label class="lbl">Seu WhatsApp</label><input name="remetente_telefone" class="field" inputmode="tel" value="{{ old('remetente_telefone') }}" placeholder="(opcional)"></div>
      <div><label class="lbl">Quem vai receber</label><input name="destinatario_nome" class="field" required value="{{ old('destinatario_nome') }}" placeholder="Nome completo"></div>
      <div><label class="lbl">WhatsApp de quem recebe</label><input name="destinatario_telefone" class="field" inputmode="tel" value="{{ old('destinatario_telefone') }}" placeholder="(opcional)"></div>
    </div>

    <div class="card space-y-3">
      <div class="font-bold text-sm" style="color:var(--cor1)">3 · O que você está enviando</div>
      <div class="flex flex-wrap gap-1.5">
        @foreach($produtos as $p)
          <button type="button" @click="add('{{ addslashes($p) }}')" class="rounded-full border px-3 py-1.5 text-sm font-semibold" style="border-color:color-mix(in srgb,var(--cor1) 25%,white);color:var(--cor1)">{{ $p }}</button>
        @endforeach
        <button type="button" @click="add('')" class="rounded-full border border-dashed px-3 py-1.5 text-sm font-semibold text-slate-500">＋ outro</button>
      </div>
      <div class="space-y-2">
        <template x-for="(it,i) in itens" :key="i">
          <div class="flex gap-2 items-center">
            <input :name="`itens[${i}][descricao]`" x-model="it.descricao" class="field flex-1" placeholder="O que é (ex.: caixa, saco...)" required>
            <input :name="`itens[${i}][quantidade]`" x-model.number="it.quantidade" type="number" min="1" step="1" class="field w-20 text-center" inputmode="numeric">
            <button type="button" @click="itens.splice(i,1)" class="w-10 h-10 shrink-0 rounded-full text-red-500 text-2xl leading-none">×</button>
          </div>
        </template>
        <p x-show="!itens.length" class="text-sm text-slate-400">Toque num produto acima ou em “＋ outro”.</p>
      </div>
      <div><label class="lbl">Observação (opcional)</label><input name="observacao" class="field" value="{{ old('observacao') }}" placeholder="Algo importante sobre a encomenda"></div>
    </div>

    <button class="w-full rounded-2xl py-4 text-lg font-extrabold text-white shadow-lg active:scale-[.98] transition disabled:opacity-50" style="background:var(--cor2)" :disabled="salvando">
      <span x-show="!salvando">Gerar meu código ✓</span><span x-show="salvando" x-cloak>Enviando…</span>
    </button>
    <p class="text-center text-xs text-slate-400">É rápido e grátis. O pagamento é feito no balcão.</p>
  </form>
</div>

<script>
function pre(){
  return {
    salvando:false,
    itens: @js(collect(old('itens', []))->map(fn($i)=>['descricao'=>$i['descricao'] ?? '', 'quantidade'=>$i['quantidade'] ?? 1])->values()),
    add(nome){ this.itens.push({descricao:nome, quantidade:1}); },
  }
}
</script>
</body>
</html>
