# Controller: enriquece linhas com flag municipio (faturado)
f='app/Http/Controllers/CaixaController.php'
s=open(f,encoding='utf-8').read()
orig=s
a="""            $canais = Pagamento::CANAIS;
            $linhas = $movs->map(fn ($p) => (object) ["""
b="""            $canais = Pagamento::CANAIS;
            $passIdsP = $movs->where('modulo', 'passagem')->pluck('origem_id')->filter()->unique()->all();
            $formasP = $passIdsP ? \\App\\Models\\Passagem::whereIn('id', $passIdsP)->pluck('forma', 'id') : collect();
            $linhas = $movs->map(fn ($p) => (object) ["""
if '$formasP = ' not in s: s=s.replace(a,b,1)
a2="""                'valor' => (float) $p->valor,
            ]);
            $ent = (float) $porMeio->sum('valor');"""
b2="""                'valor' => (float) $p->valor,
                'municipio' => $p->modulo === 'passagem' && (($formasP[$p->origem_id] ?? null) === 'faturado'),
            ]);
            $ent = (float) $porMeio->sum('valor');"""
if "'municipio' =>" not in s: s=s.replace(a2,b2,1)
open(f,'w',encoding='utf-8').write(s)
print("ctrl:", '$formasP = ' in s and "'municipio' =>" in s)

# Blade: classe de cor na linha + legenda
f2='resources/views/relatorio/prestacao.blade.php'
s2=open(f2,encoding='utf-8').read()
s2=s2.replace('      @forelse($linhas as $l)\n      <tr>',
              '      @forelse($linhas as $l)\n      <tr @if($l->municipio ?? false)style="background:#dbeafe"@endif>')
# legenda antes da tabela de totais
s2=s2.replace('  <div class="totais">',
              '  <div style="font-size:10px;color:#555;margin:6px 0"><span style="background:#dbeafe;padding:1px 7px;border-radius:4px">Agente / Município (faturado)</span> <span style="background:#fee2e2;color:#b91c1c;padding:1px 7px;border-radius:4px">Cancelado</span></div>\n  <div class="totais">')
open(f2,'w',encoding='utf-8').write(s2)
print("blade:", 'style="background:#dbeafe"@endif' in s2)
