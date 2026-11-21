# Controller: carrega tipo/papel/cancelado dos passageiros
f='app/Http/Controllers/PassagemController.php'
s=open(f,encoding='utf-8').read()
a="->with(['passageiros:id,passagem_id', 'embarcacao:id,nome'])"
b="->with(['passageiros:id,passagem_id,papel,tipo,cancelado', 'embarcacao:id,nome'])"
if a in s: s=s.replace(a,b,1)
open(f,'w',encoding='utf-8').write(s)
print("ctrl:", 'passageiros:id,passagem_id,papel,tipo,cancelado' in s)

# View: cor na linha + legenda + prestacao aponta pro caixa-viagem
f2='resources/views/passagens/minhas-vendas.blade.php'
s2=open(f2,encoding='utf-8').read()
# cor da linha
a2='''      @forelse($vendas as $p)
        <a href="{{ route('passagens.show',$p) }}" class="flex justify-between gap-3 p-3 hover:bg-slate-50">'''
b2='''      @forelse($vendas as $p)
        @php
          $corV = null;
          if ($p->cancelada) $corV = '#fee2e2';
          elseif ($p->forma === 'faturado') $corV = '#dbeafe';
          elseif ($p->passageiros->contains(fn($x)=> mb_stripos((string)$x->tipo,'pcd')!==false || mb_stripos((string)$x->tipo,'defic')!==false)) $corV = '#fef9c3';
          elseif ($p->passageiros->contains(fn($x)=> mb_stripos((string)$x->tipo,'idoso')!==false)) $corV = '#dcfce7';
        @endphp
        <a href="{{ route('passagens.show',$p) }}" class="flex justify-between gap-3 p-3 hover:brightness-95"@if($corV) style="background:{{ $corV }}"@endif>'''
if '$corV = null' not in s2 and a2 in s2: s2=s2.replace(a2,b2,1)
# legenda apos a lista
a3='      @empty <div class="p-6 text-center text-slate-400">Nenhuma venda no período.</div> @endforelse\n    </div>\n  </div>'
b3=a3+'''
  <div class="text-[11px] text-slate-500 flex flex-wrap gap-2 px-1">Legenda:
    <span style="background:#dbeafe;padding:1px 7px;border-radius:4px">Agente/Município</span>
    <span style="background:#dcfce7;padding:1px 7px;border-radius:4px">Idoso</span>
    <span style="background:#fef9c3;padding:1px 7px;border-radius:4px">PCD</span>
    <span style="background:#fee2e2;color:#b91c1c;padding:1px 7px;border-radius:4px">Cancelado</span>
  </div>'''
if 'Legenda:' not in s2 and a3 in s2: s2=s2.replace(a3,b3,1)
# prestacao (PDF) aponta pro caixa-viagem (do proprio operador)
a4="<a href=\"{{ request()->fullUrlWithQuery(['pdf'=>1]) }}\" target=\"_blank\" class=\"btn btn-ghost btn-sm\">📄 Prestação (PDF)</a>"
b4="<a href=\"{{ route('passagens.caixa-viagem', array_merge(request()->only(['inicio','fim']), ['user_id'=>auth()->id()])) }}\" target=\"_blank\" class=\"btn btn-ghost btn-sm\">📄 Prestação (PDF)</a>"
if a4 in s2: s2=s2.replace(a4,b4,1)
open(f2,'w',encoding='utf-8').write(s2)
print("view cor:", '$corV = null' in s2, "| legenda:", 'Legenda:' in s2, "| prestacao:", "route('passagens.caixa-viagem'" in s2)
