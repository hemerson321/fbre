f='resources/views/passagens/comprovante.blade.php'
s=open(f,encoding='utf-8').read()
orig=s

# 1) Razao social -> so quando dados completos ligado
a1='@if($e && $e->nome && mb_strtoupper(trim($e->nome)) !== mb_strtoupper(trim($e->nomeExibicao())))<div class="cnpj">{{ strtoupper($e->nome) }}</div>@endif'
b1='@if($e?->config(\'comprovante_dados_completos\') && $e && $e->nome && mb_strtoupper(trim($e->nome)) !== mb_strtoupper(trim($e->nomeExibicao())))<div class="cnpj">{{ strtoupper($e->nome) }}</div>@endif'
if a1 in s: s=s.replace(a1,b1,1)

# 2) Endereco -> so quando dados completos ligado
a2='@if($e?->endereco || $e?->cidade)<div class="cnpj">{{ $e->endereco }}{{ ($e->endereco && $e->cidade) ? \' · \' : \'\' }}{{ $e->cidade }}{{ $e->uf ? \'/\'.$e->uf : \'\' }}</div>@endif'
b2='@if($e?->config(\'comprovante_dados_completos\') && ($e?->endereco || $e?->cidade))<div class="cnpj">{{ $e->endereco }}{{ ($e->endereco && $e->cidade) ? \' · \' : \'\' }}{{ $e->cidade }}{{ $e->uf ? \'/\'.$e->uf : \'\' }}</div>@endif'
if a2 in s: s=s.replace(a2,b2,1)

# 3) Titulo da empresa maior (14px -> 16px)
a3='.emp{font-weight:800;font-size:14px;letter-spacing:.02em;line-height:1.1}'
b3='.emp{font-weight:800;font-size:16px;letter-spacing:.02em;line-height:1.12}'
if a3 in s: s=s.replace(a3,b3,1)

open(f,'w',encoding='utf-8').write(s)
print("razao:",b1 in s,"| endereco:",b2 in s,"| titulo:",b3 in s,"| changed:",s!=orig)
