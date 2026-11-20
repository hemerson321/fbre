# Controller: troca flags dinheiro/faturado por idoso/pcd/municipio
f='app/Http/Controllers/PassagemController.php'
s=open(f,encoding='utf-8').read()
orig=s
# remove as flags antigas de passagem (temDinheiro/ehFaturado) e usa no push
a1="""            $temDinheiro = $p->pagamentos->where('meio', 'dinheiro')->where('valor', '>', 0)->isNotEmpty();
            $ehFaturado = in_array($p->forma, ['faturado', 'areceber'], true);"""
b1="""            $ehMunicipio = $p->forma === 'faturado';"""
if a1 in s: s=s.replace(a1,b1,1)

a2="""                $tipoTxt = (string) ($pax->papel ?: $pax->tipo);
                $ehIdoso = mb_stripos($tipoTxt, 'idoso') !== false;"""
b2="""                $tipoTxt = (string) ($pax->papel ?: $pax->tipo);
                $ehIdoso = mb_stripos($tipoTxt, 'idoso') !== false;
                $ehPcd = mb_stripos($tipoTxt, 'pcd') !== false || mb_stripos($tipoTxt, 'defic') !== false;"""
if a2 in s: s=s.replace(a2,b2,1)

a3="""                    'forma' => $formaTxt,
                    'dinheiro' => $temDinheiro,
                    'faturado' => $ehFaturado,"""
b3="""                    'forma' => $formaTxt,
                    'idoso' => $ehIdoso && ! $cancPax,
                    'pcd' => $ehPcd && ! $cancPax,
                    'municipio' => $ehMunicipio && ! $cancPax,"""
if a3 in s: s=s.replace(a3,b3,1)
open(f,'w',encoding='utf-8').write(s)
print("ctrl:", 'municipio' in s and 'ehPcd' in s and 'dinheiro' not in s.split("'forma' => $formaTxt,")[1][:120])

# View: classes de cor + legenda
f2='resources/views/passagens/caixa_viagem.blade.php'
s2=open(f2,encoding='utf-8').read()
# CSS: troca .dinheiro/.fat por .idoso/.pcd/.mun
s2=s2.replace("  tr.dinheiro{background:#dcfce7}\n  tr.fat{background:#dbeafe}",
"  tr.idoso{background:#dcfce7}\n  tr.pcd{background:#fef9c3}\n  tr.mun{background:#dbeafe}")
# classe da linha (prioridade: cancelado > pcd > idoso > municipio)
s2=s2.replace('<tr class="@if($l->cancelado)canc @elseif($l->faturado)fat @elseif($l->dinheiro)dinheiro @endif">',
'<tr class="@if($l->cancelado)canc @elseif($l->pcd)pcd @elseif($l->idoso)idoso @elseif($l->municipio)mun @endif">')
# legenda
s2=s2.replace('''  Legenda: <span style="background:#dcfce7">Dinheiro</span>
  <span style="background:#dbeafe">Faturado / a receber</span>
  <span style="background:#fee2e2;color:#b91c1c">Devolvida</span>''',
'''  Legenda:
  <span style="background:#dbeafe">Agente / Município</span>
  <span style="background:#dcfce7">Idoso</span>
  <span style="background:#fef9c3">PCD</span>
  <span style="background:#fee2e2;color:#b91c1c">Cancelado / Devolvido</span>''')
open(f2,'w',encoding='utf-8').write(s2)
print("view:", 'tr.pcd{' in s2 and 'Agente / Município' in s2)
