import re
f='resources/views/caixa/index.blade.php'
s=open(f,encoding='utf-8').read()
orig=s

# === 1) tira o card resumo da grade e vira faixa horizontal ===
old_resumo='''  @endforeach
  <div class="card p-3 text-xs space-y-1">
    <div class="flex justify-between"><span class="text-slate-500">Saídas avulsas</span><b class="text-red-600">R$ {{ number_format($saidas,2,',','.') }}</b></div>
    <div class="flex justify-between"><span class="text-slate-500">Descontos</span><b>R$ {{ number_format($descontos,2,',','.') }}</b></div>
    <div class="flex justify-between"><span class="text-slate-500">Cadastros</span><b>{{ $cadastros }}</b></div>
    <div class="flex justify-between"><span class="text-slate-500">Entregas</span><b>{{ $entregas }}</b></div>
    <div class="flex justify-between"><span class="text-slate-500">❌ Cancelados</span><b class="text-slate-500">{{ $cancelados->qtd ?? 0 }} · R$ {{ number_format($cancelados->valor ?? 0,2,',','.') }}</b></div>
  </div>
</div>'''
new_resumo='''  @endforeach
</div>
<div class="card px-4 py-2.5">
  <div class="flex flex-wrap items-center gap-x-7 gap-y-1.5 text-[13px]">
    <span class="text-slate-500">Saídas avulsas <b class="text-red-600 ml-1">R$ {{ number_format($saidas,2,',','.') }}</b></span>
    <span class="text-slate-300">·</span>
    <span class="text-slate-500">Descontos <b class="text-slate-800 ml-1">R$ {{ number_format($descontos,2,',','.') }}</b></span>
    <span class="text-slate-300">·</span>
    <span class="text-slate-500">Cadastros <b class="text-slate-800 ml-1">{{ $cadastros }}</b></span>
    <span class="text-slate-300">·</span>
    <span class="text-slate-500">Entregas <b class="text-slate-800 ml-1">{{ $entregas }}</b></span>
    <span class="text-slate-300">·</span>
    <span class="text-slate-500">❌ Cancelados <b class="text-slate-800 ml-1">{{ $cancelados->qtd ?? 0 }} · R$ {{ number_format($cancelados->valor ?? 0,2,',','.') }}</b></span>
  </div>
</div>'''
assert old_resumo in s, "resumo nao achado"
s=s.replace(old_resumo,new_resumo,1)

# === 2) Por lancha ANTES de Por operador, 2 colunas ===
pat=re.compile(r'(<div class="card overflow-hidden">\s*<div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">Por operador</div>.*?</table>\s*</div>)\s*(<div class="card overflow-hidden">\s*<div class="p-3 font-bold text-sm text-slate-500 uppercase border-b">🚤 Por lancha / transporte</div>.*?</table>\s*</div>)', re.DOTALL)
m=pat.search(s)
assert m, "blocos operador/lancha nao achados"
operador, lancha = m.group(1), m.group(2)
novo='<div class="grid grid-cols-1 xl:grid-cols-2 gap-4">\n'+lancha+'\n'+operador+'\n</div>'
s=s[:m.start()]+novo+s[m.end():]

open(f,'w',encoding='utf-8').write(s)
print("resumo-faixa:", 'card px-4 py-2.5' in s, "| 2col:", 'grid grid-cols-1 xl:grid-cols-2 gap-4' in s, "| lancha antes:", s.index('Por lancha') < s.index('Por operador'), "| changed:", s!=orig)
