f='resources/views/passagens/show.blade.php'
s=open(f,encoding='utf-8').read()
orig=s

# 1) rotulo do botao
s=s.replace('@click="painel = painel===\'cancel\' ? \'\' : \'cancel\'">↩ Devolução</button>',
            '@click="painel = painel===\'cancel\' ? \'\' : \'cancel\'">↩ Cancelamento / Devolução</button>',1)
# 2) titulo do painel
s=s.replace('<div class="font-bold text-sm mb-1">↩ Devolução ao passageiro</div>',
            '<div class="font-bold text-sm mb-1">↩ Cancelamento / Devolução</div>',1)
# 3) badge automatico -> seletor manual
a='''      <div class="pt-0.5"><span class="inline-block rounded px-2 py-1 text-white font-bold text-[11px]" style="background:{{ $jaSaiu ? '#e11d48' : '#16a34a' }}">Esta passagem agora: {{ $jaSaiu ? 'DEPOIS — retém '.$pd.'%' : 'ANTES — retém '.$pa.'%' }}</span></div>'''
b='''      <div class="pt-1 border-t mt-1">
        <div class="font-bold text-slate-700 mb-1">Quanto devolver? (o operador escolhe)</div>
        <label class="flex items-center gap-2 py-0.5 cursor-pointer font-semibold"><input type="radio" name="retencao" value="integral" class="w-4 h-4"> Devolução INTEGRAL — devolve 100% (retém 0%)</label>
        <label class="flex items-center gap-2 py-0.5 cursor-pointer font-semibold"><input type="radio" name="retencao" value="antes" class="w-4 h-4" @checked(!$jaSaiu)> Retém {{ $pa }}% — devolve {{ 100-$pa }}% (vai pro caixa local)</label>
        <label class="flex items-center gap-2 py-0.5 cursor-pointer font-semibold"><input type="radio" name="retencao" value="depois" class="w-4 h-4" @checked($jaSaiu)> Retém {{ $pd }}% — devolve {{ 100-$pd }}% (fica pro proprietário)</label>
      </div>'''
if 'name="retencao"' not in s and a in s: s=s.replace(a,b,1)

open(f,'w',encoding='utf-8').write(s)
print("botao:", 'Cancelamento / Devolução</button>' in s, "| titulo:", '<div class="font-bold text-sm mb-1">↩ Cancelamento / Devolução</div>' in s, "| seletor:", 'name="retencao"' in s, "| changed:", s!=orig)
