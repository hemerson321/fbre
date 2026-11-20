f='resources/views/passagens/create.blade.php'
s=open(f,encoding='utf-8').read()
orig=s

# 1) filtrar formas por permissao
a1="          @if(empty($soAvista) || $k==='avista')"
b1="          @if((empty($soAvista) || $k==='avista') && in_array($k, $formasPermitidas ?? ['avista']))"
if a1 in s: s=s.replace(a1,b1,1)

# 2) select de parcelas apos os botoes de forma
a2='''          @endforeach
        </div>
        @if(!empty($soAvista))'''
b2='''          @endforeach
        </div>
        <div x-show="forma==='areceber'" class="flex items-center gap-2 mt-1">
          <label class="lbl !mb-0">Parcelado em</label>
          <select x-model.number="parcelas" class="field w-auto font-semibold"><template x-for="n in 12" :key="n"><option :value="n" x-text="n+'x'"></option></template></select>
        </div>
        @if(!empty($soAvista))'''
if 'forma===\'areceber\'" class="flex items-center gap-2 mt-1"' not in s: s=s.replace(a2,b2,1)

# 3) Alpine data: forma inicial + parcelas
a3="      salvando:false, forma:'avista', imprimir:'direto',"
b3="      salvando:false, forma:@js($formaInicial ?? 'avista'), parcelas:1, imprimir:'direto',"
if a3 in s: s=s.replace(a3,b3,1)

# 4) montarDados: parcelas
a4="          forma:this.forma, desconto:Number(this.desconto)||0, observacao:this.observacao||null,"
b4="          forma:this.forma, parcelas:(this.forma==='areceber'?(Number(this.parcelas)||1):1), desconto:Number(this.desconto)||0, observacao:this.observacao||null,"
if a4 in s: s=s.replace(a4,b4,1)

open(f,'w',encoding='utf-8').write(s)
print("filtro:", b1 in s, "| parcelas-sel:", 'Parcelado em' in s, "| alpine:", 'parcelas:1,' in s, "| montar:", 'parcelas:(this.forma' in s, "| changed:", s!=orig)
