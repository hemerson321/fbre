f='resources/views/passagens/comprovante.blade.php'
s=open(f,encoding='utf-8').read()
orig=s

# 1) bilhete: linha de desconto na caixa de pagamento (apos a forma)
a1='<div class="row"><span class="k">Forma de pagamento</span><span class="vv b">{{ $formaLabel }}</span></div>'
b1=a1+'\n    @if($p->desconto > 0.009)<div class="row"><span class="k">Desconto na venda</span><span class="vv">- R$ {{ number_format((float) $p->desconto,2,\',\',\'.\') }}</span></div>@endif'
if 'Desconto na venda' not in s and a1 in s: s=s.replace(a1,b1,1)

# 2) resumo: linha de desconto antes do Recebido
a2='<div class="rz-row"><span class="k">Recebido</span><span class="vv">R$ {{ number_format($rzPag,2,\',\',\'.\') }}</span></div>'
b2='@if($p->desconto > 0.009)<div class="rz-row"><span class="k">Desconto</span><span class="vv">- R$ {{ number_format((float) $p->desconto,2,\',\',\'.\') }}</span></div>@endif\n  '+a2
if 'rz-row"><span class="k">Desconto</span>' not in s and a2 in s: s=s.replace(a2,b2,1)

open(f,'w',encoding='utf-8').write(s)
print("bilhete:", 'Desconto na venda' in s, "| resumo:", 'rz-row"><span class="k">Desconto</span>' in s, "| changed:", s!=orig)
