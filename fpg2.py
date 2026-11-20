f='resources/views/passagens/comprovante.blade.php'
s=open(f,encoding='utf-8').read()
orig=s

# CSS mais destacado e maior
a_css='''.fpg{background:#f1f5f9;border:1.5px solid #cbd5e1;border-radius:9px;padding:4px 9px;margin:5px 0}
.fpg .row{font-size:15px;padding:2.5px 0}
.fpg .row .vv{font-weight:900}
.fpg .row .k{font-weight:700;color:#1f2937}'''
b_css='''.fpg{background:#f1f5f9;border:2px solid var(--cor);border-radius:10px;padding:6px 10px;margin:6px 0}
.fpg .fpg-h{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.09em;color:var(--cor);margin-bottom:3px}
.fpg .row{font-size:17px;padding:3px 0}
.fpg .row .vv{font-weight:900;color:#000}
.fpg .row .k{font-weight:700;color:#111}'''
if a_css in s: s=s.replace(a_css,b_css,1)

# header dentro da caixa
a_box='<div class="fpg">\n    <div class="row"><span class="k">Forma de pagamento</span>'
b_box='<div class="fpg">\n    <div class="fpg-h">💳 Pagamento</div>\n    <div class="row"><span class="k">Forma de pagamento</span>'
if '<div class="fpg-h">' not in s and a_box in s: s=s.replace(a_box,b_box,1)

open(f,'w',encoding='utf-8').write(s)
print("css:", '.fpg .fpg-h{' in s, "| header:", '<div class="fpg-h">' in s, "| changed:", s!=orig)
