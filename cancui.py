f='resources/views/passagens/show.blade.php'
s=open(f,encoding='utf-8').read()
orig=s

# 1) calcula pagantes no @php do form
a1='''    @php $embc = $p->embarcacao; $pa = $embc?->cancelAntes() ?? 10; $pd = $embc?->cancelDepois() ?? 30;
         $jaSaiu = $p->jaSaiu(); @endphp'''
b1='''    @php $embc = $p->embarcacao; $pa = $embc?->cancelAntes() ?? 10; $pd = $embc?->cancelDepois() ?? 30;
         $jaSaiu = $p->jaSaiu();
         $pagC = $p->passageiros->filter(fn($x)=>($x->papel ?? '')!=='colo')->values(); @endphp'''
if '$pagC =' not in s and a1 in s: s=s.replace(a1,b1,1)

# 2) seletor de passageiros antes do Motivo
a2='    <label class="label">Motivo da devolução</label>'
b2='''    @if($pagC->count() > 1)
    <div x-data="{escopo:'todos'}" class="mb-3 rounded-lg border p-2.5 bg-white">
      <label class="flex items-center gap-2 font-semibold text-sm py-0.5 cursor-pointer"><input type="radio" name="escopo_cancel" value="todos" x-model="escopo" class="w-4 h-4"> Cancelar a passagem TODA ({{ $pagC->count() }} passageiros)</label>
      <label class="flex items-center gap-2 font-semibold text-sm py-0.5 cursor-pointer"><input type="radio" name="escopo_cancel" value="parcial" x-model="escopo" class="w-4 h-4"> Cancelar só alguns passageiros</label>
      <div x-show="escopo==='parcial'" x-cloak class="mt-1.5 space-y-1 pl-6">
        @foreach($pagC as $px)
        <label class="flex items-center gap-2 text-sm py-0.5 cursor-pointer"><input type="checkbox" name="pax_ids[]" value="{{ $px->id }}" class="w-4 h-4"> {{ $px->nome ?: 'Passageiro' }}@if($px->poltrona) · <span class="text-slate-500">polt. {{ $px->poltrona }}</span>@endif · R$ {{ number_format($px->valor_total,2,',','.') }}</label>
        @endforeach
        <p class="text-[11px] text-slate-400 pt-0.5">A devolução em dinheiro dos cancelados é registrada manualmente no caixa (saída avulsa).</p>
      </div>
    </div>
    @endif
    <label class="label">Motivo da devolução</label>'''
if 'name="escopo_cancel"' not in s: s=s.replace(a2,b2,1)

open(f,'w',encoding='utf-8').write(s)
print("pagC:", '$pagC =' in s, "| seletor:", 'name="escopo_cancel"' in s, "| changed:", s!=orig)
