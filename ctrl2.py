f='app/Http/Controllers/PassagemController.php'
s=open(f,encoding='utf-8').read()
orig=s

# A) index(): passar tiposPax pra view
a1="            'verTodos' => $verTodos,\n            'escopo' => $escopo,\n        ]);"
assert a1 in s, "anchor index view nao achado"
if "'tiposPax'" not in s:
    b1="            'verTodos' => $verTodos,\n            'escopo' => $escopo,\n            'tiposPax' => \\App\\Models\\TipoPassageiro::where('ativo', true)->orderBy('eh_normal', 'desc')->orderBy('nome')->get(['id', 'nome']),\n        ]);"
    s=s.replace(a1,b1,1)

# B) atualizar(): validar tipo do passageiro
a2="            'passageiros.*.nome' => 'required|string|max:120',"
if "'passageiros.*.tipo'" not in s:
    s=s.replace(a2, a2+"\n            'passageiros.*.tipo' => 'nullable|string|max:60',",1)

# C) atualizar(): gravar tipo (se vier)
a3="                $row->forceFill([\n                    'nome' => $pax['nome'],"
b3="                $tipoNovo = trim((string) ($pax['tipo'] ?? ''));\n                $row->forceFill([\n                    'nome' => $pax['nome'],\n                    'tipo' => ($ehColo ? $row->tipo : ($tipoNovo !== '' ? $tipoNovo : $row->tipo)),"
if "'tipo' => ($ehColo ? $row->tipo" not in s:
    s=s.replace(a3,b3,1)

open(f,'w',encoding='utf-8').write(s)
print("tiposPax:", "'tiposPax'" in s, "| val tipo:", "'passageiros.*.tipo'" in s, "| save tipo:", "$tipoNovo" in s, "| changed:", s!=orig)
