{{-- Prestação de contas com OPERADORES (opcional: Administração → Empresa). --}}
@if(($prestOperador ?? false) && auth()->user()->caixaTodos())
@php $podeDesfOp = (bool) \App\Support\Tenant::empresa()->config('desfazer_acerto') || auth()->user()->isAdmin() || auth()->user()->isSuper(); @endphp
<div class="card overflow-hidden">
  <div class="p-4 border-b flex items-center justify-between gap-2 flex-wrap">
    <div>
      <div class="text-lg font-extrabold text-slate-800">👤 Prestação com operadores</div>
      <div class="text-[13px] text-slate-500">O que cada operador recebeu e ainda não prestou conta.
        @if(\App\Support\Tenant::empresa()->config('caixa_apos_prestacao')) Esses valores só entram no Caixa depois de fechar a prestação. @else As vendas já entram direto no Caixa; aqui é a conferência do operador. @endif
      </div>
    </div>
    <span class="rounded-full px-3 py-1 text-xs font-bold" style="background:color-mix(in srgb, {{ $tema[0] }} 14%, white); color:{{ $tema[1] }}">em aberto R$ {{ number_format($operadoresPrest->sum('total'),2,',','.') }}</span>
  </div>
  <div class="divide-y text-sm">
    @forelse($operadoresPrest as $o)
    <div class="flex items-center justify-between gap-3 p-3 flex-wrap">
      <div class="min-w-0">
        <div class="font-semibold truncate">👤 {{ $o->op->name }}</div>
        @if($o->qtd > 0)
        <div class="text-xs text-slate-500">{{ $o->qtd }} lançamento(s) · dinheiro <b>R$ {{ number_format($o->dinheiro,2,',','.') }}</b> · PIX <b>R$ {{ number_format($o->pix,2,',','.') }}</b> @if($o->outros > 0)· outros <b>R$ {{ number_format($o->outros,2,',','.') }}</b> @endif</div>
        @else
        <div class="text-xs text-emerald-600">em dia</div>
        @endif
        @if($o->ultima)
        <div class="text-[11px] text-slate-400">última prestação em {{ \Illuminate\Support\Carbon::parse($o->ultima)->format('d/m/Y H:i') }}</div>
        @endif
      </div>
      <div class="flex items-center gap-2">
        <div class="text-lg font-bold text-amber-700">R$ {{ number_format($o->total,2,',','.') }}</div>
        @if($o->qtd > 0)
        <form method="post" action="{{ route('prestacoes.operador.fechar', $o->op->id) }}" onsubmit="return confirm('Fechar a prestação de {{ $o->op->name }}? Total R$ {{ number_format($o->total,2,',','.') }}.')">
          @csrf
          <button class="btn btn-accent btn-sm whitespace-nowrap">Fechar prestação</button>
        </form>
        @endif
        @if($o->ultima && $podeDesfOp)
        <form method="post" action="{{ route('prestacoes.operador.desfazer', $o->op->id) }}" onsubmit="return confirm('Desfazer a última prestação de {{ $o->op->name }}? Os lançamentos voltam para em aberto.')">
          @csrf
          <button class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50" title="Desfazer a última prestação">↩️ Desfazer</button>
        </form>
        @endif
      </div>
    </div>
    @empty
    <div class="p-6 text-center text-slate-400">Nenhum operador com valor em aberto.</div>
    @endforelse
  </div>
</div>
@endif
