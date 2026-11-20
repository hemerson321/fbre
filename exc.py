f='app/Http/Controllers/PassagemController.php'
s=open(f,encoding='utf-8').read()
anchor="    public function cancelar(Request $request, Passagem $passagem)\n    {"
assert anchor in s, "anchor cancelar nao achado"
block='''    /** Exclui PERMANENTEMENTE a passagem (só quem tem permissão). Empresa-escopado pelo binding. */
    public function excluir(Passagem $passagem)
    {
        abort_unless(auth()->user()->podeExcluirPassagem(), 403, 'Sem permissão para excluir passagens.');
        $num = $passagem->numeroFormatado();
        \\Illuminate\\Support\\Facades\\DB::transaction(function () use ($passagem) {
            \\App\\Models\\Pagamento::where('modulo', 'passagem')->where('origem_id', $passagem->id)->delete();
            $passagem->passageiros()->delete();
            $passagem->delete();
        });
        if (request()->expectsJson()) return ['ok' => true];
        return redirect()->route('passagens.index')->with('ok', 'Passagem #'.$num.' excluída permanentemente.');
    }

    public function cancelar(Request $request, Passagem $passagem)
    {'''
if 'function excluir(Passagem' not in s: s=s.replace(anchor, block, 1)
open(f,'w',encoding='utf-8').write(s)
print("excluir metodo:", 'function excluir(Passagem' in s)
