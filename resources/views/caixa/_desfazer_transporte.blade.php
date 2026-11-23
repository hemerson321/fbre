  @if(($podeDesfazerTransporte ?? false))
  <div class="px-3 py-3 border-b bg-amber-50 flex items-center justify-between gap-2 flex-wrap">
    <div class="text-[12px] text-amber-800">↩️ Este transporte já teve o acerto feito ({{ $acertoDesfazivel }} passagem(ns){{ $dataViagem ? ' · viagem '.\Illuminate\Support\Carbon::parse($dataViagem)->format('d/m/Y') : '' }}). Dá pra desfazer e voltar pra em aberto.</div>
    <form method="post" action="{{ route('prestacoes.desfazer-transporte', $transporteId) }}" onsubmit="return confirm('Desfazer o acerto deste transporte/viagem? As {{ $acertoDesfazivel }} passagem(ns) voltam pra EM ABERTO e os lançamentos são estornados.')">
      @csrf
      <input type="hidden" name="viagem_id" value="{{ $viagemId }}">
      <input type="hidden" name="data_viagem" value="{{ $dataViagem }}">
      <button class="rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-sm font-semibold text-rose-600 hover:bg-rose-50 whitespace-nowrap">↩️ Desfazer acerto deste transporte</button>
    </form>
  </div>
  @endif
