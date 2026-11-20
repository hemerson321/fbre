f='app/Http/Controllers/PassagemController.php'
s=open(f,encoding='utf-8').read()
anchor="""        return ['ok' => true, 'alteradas' => $n];
    }

    public function cancelar(Request $request, Passagem $passagem)"""
assert anchor in s, "anchor nao encontrado"
if 'function editarDados' in s:
    print("JA INSERIDO - pulando"); raise SystemExit(0)
block=r'''        return ['ok' => true, 'alteradas' => $n];
    }

    /** Edicao individual COMPLETA: carrega todos os dados da passagem + passageiros pro modal. */
    public function editarDados(Passagem $passagem)
    {
        abort_unless(auth()->user()->podeEditarPassagem(), 403, 'Sem permissao para editar passagens.');
        $passagem->load(['passageiros' => fn ($q) => $q->orderBy('id')]);
        return [
            'ok' => true,
            'id' => $passagem->id,
            'numero' => $passagem->numeroFormatado(),
            'cancelada' => (bool) $passagem->cancelada,
            'embarcacao_id' => $passagem->embarcacao_id ? (string) $passagem->embarcacao_id : '',
            'data_viagem' => $passagem->data_viagem ? \Illuminate\Support\Carbon::parse($passagem->data_viagem)->format('Y-m-d') : '',
            'origem' => (string) $passagem->origem,
            'destino' => (string) $passagem->destino,
            'forma' => (string) ($passagem->forma ?? 'avista'),
            'comprador_nome' => (string) ($passagem->comprador_nome ?? ''),
            'comprador_telefone' => (string) ($passagem->comprador_telefone ?? ''),
            'comprador_documento' => (string) ($passagem->comprador_documento ?? ''),
            'observacao' => (string) ($passagem->observacao ?? ''),
            'passageiros' => $passagem->passageiros->map(fn ($x) => [
                'id' => $x->id,
                'nome' => (string) $x->nome,
                'tipo' => (string) $x->tipo,
                'papel' => (string) ($x->papel ?? ''),
                'documento' => (string) ($x->documento ?? ''),
                'nascimento' => $x->nascimento ? $x->nascimento->format('Y-m-d') : '',
                'sexo' => (string) ($x->sexo ?? ''),
                'telefone' => (string) ($x->telefone ?? ''),
                'poltrona' => (string) ($x->poltrona ?? ''),
                'valor_passagem' => (float) $x->valor_passagem,
                'valor_taxa' => (float) $x->valor_taxa,
                'valor_alimentacao' => (float) $x->valor_alimentacao,
            ])->values(),
        ];
    }

    /** Salva a edicao individual completa (empresa-escopada pelo binding). */
    public function atualizar(Request $request, Passagem $passagem)
    {
        abort_unless(auth()->user()->podeEditarPassagem(), 403, 'Sem permissao para editar passagens.');
        abort_if($passagem->cancelada, 422, 'Passagem cancelada nao pode ser editada.');

        $data = $request->validate([
            'embarcacao_id' => 'nullable|integer',
            'data_viagem' => 'nullable|date',
            'origem' => 'required|string|max:120',
            'destino' => 'required|string|max:120',
            'forma' => 'required|in:avista,areceber,faturado,online',
            'comprador_nome' => 'nullable|string|max:120',
            'comprador_telefone' => 'nullable|string|max:30',
            'comprador_documento' => 'nullable|string|max:20',
            'observacao' => 'nullable|string|max:1000',
            'passageiros' => 'required|array|min:1',
            'passageiros.*.id' => 'required|integer',
            'passageiros.*.nome' => 'required|string|max:120',
            'passageiros.*.documento' => 'nullable|string|max:20',
            'passageiros.*.nascimento' => 'nullable|date',
            'passageiros.*.sexo' => 'nullable|string|max:10',
            'passageiros.*.telefone' => 'nullable|string|max:30',
            'passageiros.*.poltrona' => 'nullable|string|max:10',
            'passageiros.*.valor_passagem' => 'nullable|numeric|min:0',
            'passageiros.*.valor_taxa' => 'nullable|numeric|min:0',
            'passageiros.*.valor_alimentacao' => 'nullable|numeric|min:0',
        ]);

        if (! empty($data['embarcacao_id']) && ! \App\Models\Embarcacao::whereKey($data['embarcacao_id'])->exists()) {
            return response()->json(['ok' => false, 'erro' => 'Transporte invalido.'], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($passagem, $data) {
            $passagem->forceFill([
                'embarcacao_id' => ($data['embarcacao_id'] ?? null) ?: null,
                'data_viagem' => $data['data_viagem'] ?? null,
                'origem' => $data['origem'],
                'destino' => $data['destino'],
                'forma' => $data['forma'],
                'comprador_nome' => $data['comprador_nome'] ?? null,
                'comprador_telefone' => $data['comprador_telefone'] ?? null,
                'comprador_documento' => $data['comprador_documento'] ?? null,
                'observacao' => $data['observacao'] ?? null,
            ])->save();

            $idsValidos = $passagem->passageiros()->pluck('id')->all();
            foreach ($data['passageiros'] as $pax) {
                if (! in_array((int) $pax['id'], $idsValidos, true)) continue;
                $row = $passagem->passageiros()->whereKey($pax['id'])->first();
                if (! $row) continue;
                $ehColo = ($row->papel === 'colo');
                $vp = $ehColo ? 0 : round((float) ($pax['valor_passagem'] ?? 0), 2);
                $vt = $ehColo ? 0 : round((float) ($pax['valor_taxa'] ?? 0), 2);
                $va = $ehColo ? 0 : round((float) ($pax['valor_alimentacao'] ?? 0), 2);
                $row->forceFill([
                    'nome' => $pax['nome'],
                    'documento' => ($pax['documento'] ?? null) ?: null,
                    'nascimento' => ($pax['nascimento'] ?? null) ?: null,
                    'sexo' => ($pax['sexo'] ?? null) ?: null,
                    'telefone' => ($pax['telefone'] ?? null) ?: null,
                    'poltrona' => ($pax['poltrona'] ?? null) ?: null,
                    'valor_passagem' => $vp,
                    'valor_taxa' => $vt,
                    'valor_alimentacao' => $va,
                    'valor_integral' => max((float) $row->valor_integral, $vp + $vt + $va),
                    'valor_total' => round($vp + $vt + $va, 2),
                ])->save();
            }

            $passagem->recalcular();
        });

        return ['ok' => true];
    }

    public function cancelar(Request $request, Passagem $passagem)'''
s=s.replace(anchor,block,1)
open(f,'w',encoding='utf-8').write(s)
print("OK inserido")
