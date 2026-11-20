f='app/Http/Controllers/PassagemController.php'
s=open(f,encoding='utf-8').read()
orig=s

# ocupadas(): vendidas
a1='''        $q = Passagem::where('embarcacao_id', $embarcacaoId)->where('cancelada', false)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when(! $viagemId && $dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId));'''
b1='''        $viagemAberta = false;
        if ($viagemId) { $vg = \\App\\Models\\Viagem::find($viagemId); $viagemAberta = ! $vg || $vg->data_partida === null; }
        $q = Passagem::where('embarcacao_id', $embarcacaoId)->where('cancelada', false)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when(($viagemAberta || ! $viagemId) && $dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId));'''
if '$viagemAberta' not in s and a1 in s: s=s.replace(a1,b1,1)

# reservadas(): holds
a2='''        $q = \\App\\Models\\PoltronaHold::ativo()->where('embarcacao_id', $embarcacaoId)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when(! $viagemId && $dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))
            ->when($exceptUserId, fn ($q) => $q->where('user_id', '!=', $exceptUserId));'''
b2='''        $viagemAberta = false;
        if ($viagemId) { $vg = \\App\\Models\\Viagem::find($viagemId); $viagemAberta = ! $vg || $vg->data_partida === null; }
        $q = \\App\\Models\\PoltronaHold::ativo()->where('embarcacao_id', $embarcacaoId)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when(($viagemAberta || ! $viagemId) && $dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem))
            ->when($exceptUserId, fn ($q) => $q->where('user_id', '!=', $exceptUserId));'''
if a2 in s: s=s.replace(a2,b2,1)

open(f,'w',encoding='utf-8').write(s)
print("ocupadas:", b1 in s, "| reservadas:", b2 in s, "| changed:", s!=orig, "| count viagemAberta:", s.count('$viagemAberta ='))
