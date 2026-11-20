f='resources/views/passagens/show.blade.php'
s=open(f,encoding='utf-8').read()
orig=s
reps=[
  # container rolavel
  ('  <div class="flex gap-2">\n    <button onclick="FBRE.abrirImpressao(',
   '  <div class="flex gap-2 overflow-x-auto pb-1">\n    <button onclick="FBRE.abrirImpressao('),
  # 3 botoes: tira flex-1, poe shrink-0 nowrap
  ('class="btn btn-ghost flex-1">🖨 Comprovante',
   'class="btn btn-ghost shrink-0 whitespace-nowrap">🖨 Comprovante'),
  ('class="btn btn-ghost flex-1">📲 Enviar PDF (WhatsApp)',
   'class="btn btn-ghost shrink-0 whitespace-nowrap">📲 Enviar PDF (WhatsApp)'),
  ('class="btn btn-ghost flex-1">🖼️ Copiar imagem',
   'class="btn btn-ghost shrink-0 whitespace-nowrap">🖼️ Copiar imagem'),
  # demais botoes da linha: nao espremer
  ('<button type="button" class="btn btn-danger" @click="painel = painel===\'cancel\'',
   '<button type="button" class="btn btn-danger shrink-0 whitespace-nowrap" @click="painel = painel===\'cancel\''),
  ('<button type="button" class="btn btn-ghost" style="border:1px solid #6366f1;color:#4f46e5"',
   '<button type="button" class="btn btn-ghost shrink-0 whitespace-nowrap" style="border:1px solid #6366f1;color:#4f46e5"'),
  ('<button type="button" class="btn btn-ghost" style="border:1px solid #06b6d4;color:#0891b2"',
   '<button type="button" class="btn btn-ghost shrink-0 whitespace-nowrap" style="border:1px solid #06b6d4;color:#0891b2"'),
]
miss=[]
for a,b in reps:
    if a in s: s=s.replace(a,b,1)
    elif b not in s: miss.append(a[:45])
open(f,'w',encoding='utf-8').write(s)
print("changed:", s!=orig, "| faltaram:", miss)
