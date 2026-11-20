f='resources/views/passagens/comprovante.blade.php'
s=open(f,encoding='utf-8').read()
orig=s
reps=[
  # fontes maiores
  ('.resumo-venda{position:absolute;left:-10000px;top:0;width:360px;background:#fff;color:#000;padding:4mm 4.5mm 5mm;font-family:inherit;font-size:12.5px;line-height:1.3}',
   '.resumo-venda{position:absolute;left:-10000px;top:0;width:360px;background:#fff;color:#000;padding:4mm 4.5mm 5mm;font-family:inherit;font-size:14px;line-height:1.32}'),
  ('.rz-emp{font-weight:900;font-size:14px;text-align:center;letter-spacing:.02em}',
   '.rz-emp{font-weight:900;font-size:17px;text-align:center;letter-spacing:.02em}'),
  ('.rz-row{display:flex;justify-content:space-between;gap:8px;font-size:12px;padding:1.5px 0}',
   '.rz-row{display:flex;justify-content:space-between;gap:8px;font-size:13.5px;padding:2px 0}'),
  ('.rz-row .vv{font-weight:700;text-align:right}',
   '.rz-row .vv{font-weight:800;text-align:right;color:#000}'),
  ('.rz-tot{display:flex;justify-content:space-between;font-weight:900;font-size:14px;border-top:2px solid #000;margin-top:4px;padding-top:4px}',
   '.rz-tot{display:flex;justify-content:space-between;font-weight:900;font-size:18px;border-top:2px solid #000;margin-top:4px;padding-top:4px}'),
  ('.rz-px{font-size:12px;padding:2px 0;display:flex;justify-content:space-between;gap:8px;border-bottom:1px dotted #e5e7eb}',
   '.rz-px{font-size:13.5px;padding:2.5px 0;display:flex;justify-content:space-between;gap:8px;border-bottom:1px dotted #e5e7eb}'),
  # nova linha de pagamento destacada (CSS) - injeta antes de .rz-tot
  ('.rz-tot{display:flex;justify-content:space-between;font-weight:900;font-size:18px;',
   '.rz-pay{display:flex;justify-content:space-between;gap:8px;font-size:16px;font-weight:900;padding:4px 7px;margin:3px 0;background:#f1f5f9;border:1.5px solid #cbd5e1;border-radius:8px}\n.rz-tot{display:flex;justify-content:space-between;font-weight:900;font-size:18px;'),
  # troca a linha "Forma de pagamento" pelo meio real, destacada
  ('<div class="rz-row"><span class="k">Forma de pagamento</span><span class="vv">{{ \\App\\Models\\Passagem::FORMAS[$p->forma] ?? ucfirst((string) $p->forma) }}</span></div>',
   '<div class="rz-pay"><span>💳 Pagamento</span><span>{{ $formas ?: (\\App\\Models\\Passagem::FORMAS[$p->forma] ?? ucfirst((string) $p->forma)) }}</span></div>'),
]
miss=[]
for a,b in reps:
    if a in s: s=s.replace(a,b,1)
    elif b not in s: miss.append(a[:40])
open(f,'w',encoding='utf-8').write(s)
print("changed:", s!=orig, "| faltaram:", miss)
