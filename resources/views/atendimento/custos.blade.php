@extends('layouts.app')
@section('titulo','Custo da IA no atendimento')
@section('conteudo')
@php
  $br = fn ($v, $d = 2) => 'R$ '.number_format((float) $v, $d, ',', '.');
  $mil = fn ($v) => number_format((int) $v, 0, ',', '.');
  // conversa barata (centavos) precisa de mais casas pra não virar "R$ 0,00"
  $brc = fn ($v) => 'R$ '.number_format((float) $v, ((float) $v) < 1 ? 4 : 2, ',', '.');
@endphp
<div class="max-w-6xl mx-auto space-y-4">
  <div class="flex items-center justify-between gap-2 flex-wrap">
    <div>
      <h1 class="text-xl font-extrabold">🧮 Custo da IA no atendimento</h1>
      <p class="text-sm text-slate-500">Tokens gastos pelo agente em cada conversa, quanto isso custa em reais e a simulação do mês. Só vê esta tela o usuário com a permissão "Custo da IA".</p>
    </div>
    <div class="flex gap-2">
      <form method="post" action="{{ route('atendimento.custos.zerar') }}" onsubmit="return confirm('Zerar todas as medições de tokens? Isso apaga o histórico de custo desta tela (as conversas não são apagadas).')">@csrf<button class="btn btn-sm">🧹 Zerar medições</button></form>
      <a href="{{ route('atendimento.index') }}" class="btn btn-sm">💬 Conversas</a>
    </div>
  </div>

  @if(! $migrado)
    <div class="card p-4 border-amber-300 bg-amber-50 text-sm text-amber-800">⚠️ A medição ainda não foi ativada no servidor. Rode <b>php artisan migrate --force</b> depois de atualizar o sistema.</div>
  @endif

  <div class="flex gap-1.5 text-sm">
    @foreach([7 => '7 dias', 30 => '30 dias', 90 => '90 dias'] as $k => $l)
      <a href="{{ route('atendimento.custos', ['dias' => $k]) }}" class="rounded-full px-3.5 py-1.5 font-bold {{ $dias === $k ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600' }}">{{ $l }}</a>
    @endforeach
  </div>

  {{-- ===== resumo + simulação ===== --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="card p-4"><div class="text-xs text-slate-500 font-semibold">Conversas com o agente</div><div class="text-2xl font-extrabold">{{ $mil($resumo['conversas']) }}</div><div class="text-[11px] text-slate-400">{{ $resumo['soTeste'] ? 'só conversas de teste até agora' : 'nos últimos '.$dias.' dias' }}</div></div>
    <div class="card p-4"><div class="text-xs text-slate-500 font-semibold">Tokens gastos</div><div class="text-2xl font-extrabold">{{ $mil($resumo['tokens']) }}</div><div class="text-[11px] text-slate-400">entrada + saída + cache</div></div>
    <div class="card p-4"><div class="text-xs text-slate-500 font-semibold">Custo total no período</div><div class="text-2xl font-extrabold">{{ $br($resumo['totalBrl']) }}</div><div class="text-[11px] text-slate-400">dólar a {{ $br($dolar) }}</div></div>
    <div class="card p-4"><div class="text-xs text-slate-500 font-semibold">Custo médio por conversa</div><div class="text-2xl font-extrabold">{{ $brc($resumo['media']) }}</div><div class="text-[11px] text-slate-400">a mais cara: {{ $brc($resumo['maior']) }}</div></div>
  </div>

  <div class="card p-4">
    <div class="font-bold mb-1">📈 Simulação por mês</div>
    <p class="text-xs text-slate-500 mb-3">Quanto a IA custaria num mês se esse número de pessoas conversasse com o agente (1 conversa por pessoa), pelo custo das conversas medidas acima.</p>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr class="text-left text-xs text-slate-500 border-b">
          <th class="py-2 pr-3">Cenário</th>
          @foreach($simular as $q)<th class="py-2 px-3 text-right">{{ $mil($q) }} pessoas/mês</th>@endforeach
        </tr></thead>
        <tbody>
          <tr class="border-b"><td class="py-2 pr-3 font-semibold">Conversa média <span class="text-slate-400 font-normal">({{ $brc($resumo['media']) }} cada)</span></td>
            @foreach($resumo['simMedia'] as $v)<td class="py-2 px-3 text-right font-extrabold text-emerald-700">{{ $br($v) }}</td>@endforeach</tr>
          <tr><td class="py-2 pr-3 font-semibold">Pior caso — todas como a mais cara <span class="text-slate-400 font-normal">({{ $brc($resumo['maior']) }} cada)</span></td>
            @foreach($resumo['simMaior'] as $v)<td class="py-2 px-3 text-right font-bold text-amber-700">{{ $br($v) }}</td>@endforeach</tr>
        </tbody>
      </table>
    </div>
    @if(! $resumo['conversas'])
      <p class="text-xs text-slate-400 mt-2">Ainda não há conversa medida. A contagem começa a partir desta versão — faça um teste no chat de demonstração do agente e volte aqui.</p>
    @endif
  </div>

  {{-- ===== conversas ===== --}}
  <div class="card p-4">
    <div class="font-bold mb-3">💬 Conversas ({{ count($lista) }})</div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm whitespace-nowrap">
        <thead><tr class="text-left text-xs text-slate-500 border-b">
          <th class="py-2 pr-3">Cliente</th>
          <th class="py-2 px-2">Última resposta</th>
          <th class="py-2 px-2 text-right">Respostas da IA</th>
          <th class="py-2 px-2 text-right">Tokens</th>
          <th class="py-2 px-2 text-right">Custo da conversa</th>
          @foreach($simular as $q)<th class="py-2 px-2 text-right">× {{ $mil($q) }}/mês</th>@endforeach
          <th class="py-2 pl-2"></th>
        </tr></thead>
        <tbody>
        @forelse($lista as $c)
          <tr class="border-b last:border-0 hover:bg-slate-50">
            <td class="py-2 pr-3">
              <div class="font-semibold">{{ $c['nome'] }} @if($c['demo'])<span class="chip bg-violet-100 text-violet-700 text-[10px] font-bold">teste</span>@endif</div>
              <div class="text-[11px] text-slate-400">{{ $c['telefone'] }}{{ $c['telefone'] ? ' · ' : '' }}{{ implode(', ', $c['modelos']) }}</div>
            </td>
            <td class="py-2 px-2 text-slate-600">{{ $c['fim'] }}</td>
            <td class="py-2 px-2 text-right">{{ $mil($c['chamadas']) }}</td>
            <td class="py-2 px-2 text-right" title="entrada {{ $mil($c['in']) }} · cache {{ $mil($c['cache']) }} · saída {{ $mil($c['out']) }}">
              <div class="font-semibold">{{ $mil($c['tokens']) }}</div>
              <div class="text-[11px] text-slate-400">{{ $mil($c['in'] + $c['cache']) }} entr. · {{ $mil($c['out']) }} saída</div>
            </td>
            <td class="py-2 px-2 text-right font-extrabold">{{ $brc($c['brl']) }} @if($c['semPreco'])<span title="Modelo sem preço cadastrado — informe o preço abaixo" class="text-amber-600">⚠️</span>@endif</td>
            @foreach($c['sim'] as $v)<td class="py-2 px-2 text-right text-emerald-700 font-semibold">{{ $br($v) }}</td>@endforeach
            <td class="py-2 pl-2 text-right">@if(! $c['demo'])<a href="{{ route('atendimento.show', $c['id']) }}" class="text-xs underline text-slate-500">ver conversa</a>@endif</td>
          </tr>
        @empty
          <tr><td colspan="{{ 6 + count($simular) }}" class="py-6 text-center text-slate-400">Nenhuma conversa medida neste período.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- ===== preços ===== --}}
  <form method="post" action="{{ route('atendimento.custos.salvar') }}" class="card p-4 space-y-3">
    @csrf
    <div class="font-bold">⚙️ Cotação e preço dos modelos</div>
    <p class="text-xs text-slate-500">O sistema já traz o preço de tabela de cada modelo (US$ por 1 milhão de tokens). Se o provedor mudar o preço, digite o valor novo — em branco, vale o de tabela.</p>
    <div class="max-w-[220px]"><label class="label">Dólar (R$)</label><input name="dolar" class="input" inputmode="decimal" value="{{ number_format($dolar, 2, ',', '') }}"></div>
    @if($modelos)
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr class="text-left text-xs text-slate-500 border-b"><th class="py-2 pr-3">Modelo usado</th><th class="py-2 px-2 text-right">Respostas</th><th class="py-2 px-2 text-right">Tokens</th><th class="py-2 px-2 text-right">Custo</th><th class="py-2 px-2">Entrada (US$/1M)</th><th class="py-2 px-2">Saída (US$/1M)</th></tr></thead>
        <tbody>
        @foreach($modelos as $m)
          <tr class="border-b last:border-0">
            <td class="py-2 pr-3 font-mono text-xs">{{ $m['modelo'] ?: '(sem nome)' }} @if(! $m['preco']['conhecido'])<span class="chip bg-amber-100 text-amber-700 text-[10px] font-bold">sem preço — informe</span>@elseif($m['preco']['ajustado'])<span class="chip bg-sky-100 text-sky-700 text-[10px] font-bold">ajustado</span>@endif</td>
            <td class="py-2 px-2 text-right">{{ $mil($m['chamadas']) }}</td>
            <td class="py-2 px-2 text-right">{{ $mil($m['tokens']) }}</td>
            <td class="py-2 px-2 text-right font-semibold">{{ $br($m['usd'] * $dolar) }}</td>
            <td class="py-2 px-2"><input name="precos[{{ $m['modelo'] }}][in]" class="input w-28" inputmode="decimal" placeholder="{{ number_format($m['preco']['in'], 2, ',', '') }}" value="{{ $m['preco']['ajustado'] ? number_format($m['preco']['in'], 2, ',', '') : '' }}"></td>
            <td class="py-2 px-2"><input name="precos[{{ $m['modelo'] }}][out]" class="input w-28" inputmode="decimal" placeholder="{{ number_format($m['preco']['out'], 2, ',', '') }}" value="{{ $m['preco']['ajustado'] ? number_format($m['preco']['out'], 2, ',', '') : '' }}"></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    @endif
    <button class="btn btn-primary">Salvar</button>
    <p class="text-[11px] text-slate-400">Conta só o texto do agente (conversa). Leitura de foto de documento e áudio (transcrição/voz) são cobrados à parte pelo provedor e não entram aqui.</p>
  </form>
</div>
@endsection
