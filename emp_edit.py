# --- 1) Controller: salva a config ---
fc='app/Http/Controllers/Admin/EmpresaController.php'
c=open(fc,encoding='utf-8').read()
anc="        $config['usa_cancel_transporte'] = $request->boolean('usa_cancel_transporte');"
assert anc in c, "anchor controller nao achado"
if 'comprovante_dados_completos' not in c:
    c=c.replace(anc, anc+"\n        $config['comprovante_dados_completos'] = $request->boolean('comprovante_dados_completos');",1)
    open(fc,'w',encoding='utf-8').write(c)
    print("controller OK")
else:
    print("controller JA tinha")

# --- 2) Form: checkbox apos o aviso_comprovante ---
fv='resources/views/admin/empresa.blade.php'
v=open(fv,encoding='utf-8').read()
anc2='<div class="sm:col-span-2"><label class="label">Aviso em destaque no topo do bilhete</label><textarea name="aviso_comprovante" rows="2" class="input !min-h-[60px] py-2" placeholder="Ex.: Passageiro no porto com 1 hora de antecedência à saída da embarcação">{{ old(\'aviso_comprovante\',$empresa->aviso_comprovante) }}</textarea></div>'
assert anc2 in v, "anchor form nao achado"
add='''
  <label class="sm:col-span-2 flex items-start gap-2 py-1.5 cursor-pointer border rounded-lg px-3 bg-slate-50">
    <input type="checkbox" name="comprovante_dados_completos" value="1" @checked($empresa->config('comprovante_dados_completos', false)) class="w-5 h-5 rounded mt-0.5 shrink-0">
    <span><span class="font-semibold">Mostrar razão social e endereço completo no bilhete</span><br><span class="text-xs text-slate-500">Desligado (padrão): o bilhete mostra só o nome, CNPJ e telefone. Ligado: inclui a razão social e o endereço.</span></span>
  </label>'''
if 'comprovante_dados_completos' not in v:
    v=v.replace(anc2, anc2+add,1)
    open(fv,'w',encoding='utf-8').write(v)
    print("form OK")
else:
    print("form JA tinha")
