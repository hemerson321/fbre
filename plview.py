f='resources/views/caixa/index.blade.php'
s=open(f,encoding='utf-8').read()
anc='''<div class="card overflow-hidden">
  <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Lançamentos</div>'''
assert anc in s, "anchor Lancamentos nao achado"
bloco='''<div class="card overflow-hidden">
  <div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">🚤 Por lancha / transporte</div>
  @php $tmap = $transportes->keyBy('id'); @endphp
  <table class="w-full text-sm"><thead class="bg-slate-50 text-[11px] uppercase text-slate-500"><tr><th class="p-3 text-left">Transporte</th>@foreach(['dinheiro','pix_manual','pix_auto','debito','credito'] as $m)<th class="p-3 text-right">{{ $meios[$m] }}</th>@endforeach<th class="p-3 text-right">Total</th></tr></thead>
  <tbody class="divide-y">
  @forelse($porLancha as $eid=>$d)
    <tr><td class="p-3 font-semibold">{{ $eid ? '🚤 '.(($tmap[$eid] ?? null)?->nome ?? ('Transporte '.$eid)) : '— sem transporte —' }}</td>@foreach(['dinheiro','pix_manual','pix_auto','debito','credito'] as $m)<td class="p-3 text-right">{{ number_format($d['meios'][$m] ?? 0,2,',','.') }}</td>@endforeach<td class="p-3 text-right font-bold">{{ number_format($d['total'],2,',','.') }}</td></tr>
  @empty <tr><td colspan="7" class="p-6 text-center text-slate-400">Sem movimento no período.</td></tr> @endforelse
  </tbody></table>
</div>

'''+anc
if '🚤 Por lancha' not in s: s=s.replace(anc, bloco, 1)
open(f,'w',encoding='utf-8').write(s)
print("inserido:", '🚤 Por lancha' in s)
