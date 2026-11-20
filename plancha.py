f='app/Http/Controllers/CaixaController.php'
s=open(f,encoding='utf-8').read()
orig=s
# insere o calculo porLancha apos a linha do porModulo
anc="        $porModulo = (clone $base)->selectRaw('modulo, SUM(valor) valor, COUNT(*) qtd')->groupBy('modulo')->get()->keyBy('modulo');"
assert anc in s, "anchor porModulo nao achado"
block=anc+'''

        // ===== POR LANCHA (transporte): soma do caixa por embarcação, mapeado em PHP (evita join ambíguo). =====
        $pgsL = (clone $base)->selectRaw("modulo, origem_id, encomenda_id, $canalSql as canal_sql, valor")->get();
        $passIdsL = $pgsL->where('modulo', 'passagem')->pluck('origem_id')->filter()->unique()->all();
        $encIdsL = $pgsL->where('modulo', 'encomenda')->pluck('encomenda_id')->filter()->unique()->all();
        $embPass = $passIdsL ? \\App\\Models\\Passagem::whereIn('id', $passIdsL)->pluck('embarcacao_id', 'id') : collect();
        $embEnc = $encIdsL ? \\App\\Models\\Encomenda::whereIn('id', $encIdsL)->pluck('embarcacao_id', 'id') : collect();
        $porLancha = [];
        foreach ($pgsL as $pg) {
            $emb = $pg->modulo === 'passagem'
                ? ($embPass[$pg->origem_id] ?? null)
                : ($pg->modulo === 'encomenda' ? ($embEnc[$pg->encomenda_id] ?? null) : null);
            $k = (int) ($emb ?: 0);
            if (! isset($porLancha[$k])) $porLancha[$k] = ['total' => 0.0, 'meios' => []];
            $porLancha[$k]['total'] += (float) $pg->valor;
            $porLancha[$k]['meios'][$pg->canal_sql] = ($porLancha[$k]['meios'][$pg->canal_sql] ?? 0) + (float) $pg->valor;
        }
        uasort($porLancha, fn ($a, $b) => $b['total'] <=> $a['total']);'''
if '$porLancha = [];' not in s: s=s.replace(anc, block, 1)

# adiciona porLancha ao compact do return view
s=s.replace("'porModulo', 'porOperador',", "'porModulo', 'porOperador', 'porLancha',",1)

open(f,'w',encoding='utf-8').write(s)
print("calc:", '$porLancha = [];' in s, "| compact:", "'porLancha'," in s, "| changed:", s!=orig)
