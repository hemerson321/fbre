# 1) show.blade: seletor Devolucao/Cancelamento apos o titulo
f='resources/views/passagens/show.blade.php'
s=open(f,encoding='utf-8').read()
a='<div class="font-bold text-sm mb-1">↩ Cancelamento / Devolução</div>'
b='''<div class="font-bold text-sm mb-1">↩ Cancelamento / Devolução</div>
    <div class="grid grid-cols-2 gap-2 mb-2" x-data="{t:'devolucao'}">
      <label class="border-2 rounded-xl p-2 text-center font-bold text-sm cursor-pointer transition" :class="t==='devolucao' ? 'border-rose-500 bg-rose-50 text-rose-700' : 'border-slate-200 text-slate-500'"><input type="radio" name="cancel_tipo" value="devolucao" x-model="t" class="sr-only"> ↩ Devolução</label>
      <label class="border-2 rounded-xl p-2 text-center font-bold text-sm cursor-pointer transition" :class="t==='cancelamento' ? 'border-rose-500 bg-rose-50 text-rose-700' : 'border-slate-200 text-slate-500'"><input type="radio" name="cancel_tipo" value="cancelamento" x-model="t" class="sr-only"> ✖ Cancelamento</label>
    </div>'''
if "name=\"cancel_tipo\"" not in s and a in s: s=s.replace(a,b,1)
open(f,'w',encoding='utf-8').write(s)
print("seletor:", 'name="cancel_tipo"' in s)

# 2) comprovante: tarja dinamica
f2='resources/views/passagens/comprovante.blade.php'
s2=open(f2,encoding='utf-8').read()
a2='@if($p->cancelada || ($pax->cancelado ?? false))<div class="cwm"><b>CANCELADO</b></div>@endif'
b2='@php $__tarja = $p->cancelada ? ((($p->cancel_tipo ?? \'\')===\'cancelamento\')?\'CANCELAMENTO\':\'DEVOLUÇÃO\') : (($pax->cancelado ?? false) ? ((($pax->cancelado_tipo ?? \'\')===\'cancelamento\')?\'CANCELAMENTO\':\'DEVOLUÇÃO\') : null); @endphp@if($__tarja)<div class="cwm"><b>{{ $__tarja }}</b></div>@endif'
if '$__tarja' not in s2 and a2 in s2: s2=s2.replace(a2,b2,1)
open(f2,'w',encoding='utf-8').write(s2)
print("tarja:", '$__tarja' in s2)
