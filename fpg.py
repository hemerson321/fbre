f='resources/views/passagens/comprovante.blade.php'
s=open(f,encoding='utf-8').read()
orig=s

# 1) CSS da caixa de pagamento (apos .row .vv)
css_anchor='.row .k{color:#374151;font-weight:600}.row .vv{font-weight:700;color:#000;text-align:right}'
css_add=css_anchor+'\n.fpg{background:#f1f5f9;border:1.5px solid #cbd5e1;border-radius:9px;padding:4px 9px;margin:5px 0}\n.fpg .row{font-size:15px;padding:2.5px 0}\n.fpg .row .vv{font-weight:900}\n.fpg .row .k{font-weight:700;color:#1f2937}'
if '.fpg{' not in s: s=s.replace(css_anchor,css_add,1)

# 2) envolve forma de pagamento + meios na caixa .fpg
a='''    <div class="row"><span class="k">Forma de pagamento</span><span class="vv b">{{ \\App\\Models\\Passagem::FORMAS[$p->forma] ?? ucfirst((string) $p->forma) }}</span></div>
    @php $pgs = $p->pagamentos->groupBy('meio')->map(fn ($g) => (float) $g->sum('valor'))->filter(fn ($v) => $v > 0.009); @endphp
    @if($pgs->count())
      @foreach($pgs as $meio => $val)<div class="row"><span class="k">{{ \\App\\Models\\Pagamento::MEIOS[$meio] ?? ucfirst($meio) }}</span><span class="vv">R$ {{ number_format($val,2,',','.') }}</span></div>@endforeach
    @elseif($formas)<div class="row"><span class="k">Forma</span><span class="vv">{{ $formas }}</span></div>@endif'''
b='''    <div class="fpg">
    <div class="row"><span class="k">Forma de pagamento</span><span class="vv b">{{ \\App\\Models\\Passagem::FORMAS[$p->forma] ?? ucfirst((string) $p->forma) }}</span></div>
    @php $pgs = $p->pagamentos->groupBy('meio')->map(fn ($g) => (float) $g->sum('valor'))->filter(fn ($v) => $v > 0.009); @endphp
    @if($pgs->count())
      @foreach($pgs as $meio => $val)<div class="row"><span class="k">{{ \\App\\Models\\Pagamento::MEIOS[$meio] ?? ucfirst($meio) }}</span><span class="vv">R$ {{ number_format($val,2,',','.') }}</span></div>@endforeach
    @elseif($formas)<div class="row"><span class="k">Forma</span><span class="vv">{{ $formas }}</span></div>@endif
    </div>'''
assert a in s, "anchor forma pagamento nao achado"
if '<div class="fpg">' not in s: s=s.replace(a,b,1)

open(f,'w',encoding='utf-8').write(s)
print("css:", '.fpg{' in s, "| box:", '<div class="fpg">' in s, "| changed:", s!=orig)
