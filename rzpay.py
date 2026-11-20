f='resources/views/passagens/comprovante.blade.php'
s=open(f,encoding='utf-8').read()
orig=s

# CSS: rz-pay vira caixa (coluna) + rz-payrow
a_css='.rz-pay{display:flex;justify-content:space-between;gap:8px;font-size:16px;font-weight:900;padding:4px 7px;margin:3px 0;background:#f1f5f9;border:1.5px solid #cbd5e1;border-radius:8px}'
b_css=('.rz-pay{font-size:15.5px;padding:5px 9px;margin:4px 0;background:#f1f5f9;border:1.5px solid #cbd5e1;border-radius:9px}\n'
       '.rz-payrow{display:flex;justify-content:space-between;gap:8px;font-weight:900;padding:2px 0}\n'
       '.rz-payrow .k{font-weight:700;color:#1f2937}')
if '.rz-payrow{' not in s and a_css in s: s=s.replace(a_css,b_css,1)

# HTML: duas linhas (forma + pago em)
a_html='<div class="rz-pay"><span>💳 Pagamento</span><span>{{ $formas ?: (\\App\\Models\\Passagem::FORMAS[$p->forma] ?? ucfirst((string) $p->forma)) }}</span></div>'
b_html=('<div class="rz-pay">\n'
        '    <div class="rz-payrow"><span class="k">💳 Forma</span><span>{{ \\App\\Models\\Passagem::FORMAS[$p->forma] ?? ucfirst((string) $p->forma) }}</span></div>\n'
        '    @if($formas)<div class="rz-payrow"><span class="k">Pago em</span><span>{{ $formas }}</span></div>@endif\n'
        '  </div>')
if '.rz-payrow"' not in s and a_html in s: s=s.replace(a_html,b_html,1)

open(f,'w',encoding='utf-8').write(s)
print("css:", '.rz-payrow{' in s, "| html:", 'rz-payrow"><span class="k">💳 Forma' in s, "| changed:", s!=orig)
