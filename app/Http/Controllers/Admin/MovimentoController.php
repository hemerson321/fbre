<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Embarcacao;
use App\Models\Pagamento;
use App\Models\Passagem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Relatório administrativo: entradas e saídas de cancelamentos, devoluções, transferências e avulsos. */
class MovimentoController extends Controller
{
    public const TIPOS = [
        'cancelamento' => 'Cancelamento',
        'devolucao' => 'Devolução',
        'avulso' => 'Avulso',
        'transferencia' => 'Transferência',
    ];

    public function index(Request $request)
    {
        $inicio = $request->filled('inicio') ? Carbon::parse($request->input('inicio'))->startOfDay() : now()->startOfMonth();
        $fim = $request->filled('fim') ? Carbon::parse($request->input('fim'))->endOfDay() : now()->endOfDay();
        $tipo = (string) $request->input('tipo', '');
        $mov = (string) $request->input('mov', '');
        $embId = $request->integer('transporte_id') ?: null;
        $userId = $request->integer('user_id') ?: null;

        $pgs = Pagamento::where('estornado', false)->where('meio', '!=', 'desconto')
            ->whereBetween('created_at', [$inicio, $fim])
            ->where(function ($w) {
                $w->where(fn ($a) => $a->where('modulo', 'avulso')->where('momento', 'cadastro'))
                  ->orWhere('categoria', 'like', 'Devolução%')
                  ->orWhere('categoria', 'like', 'Débito do dono%')
                  ->orWhere('categoria', 'like', 'Transferência%');
            })
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->with(['user:id,name', 'embarcacao:id,nome'])
            ->orderByDesc('created_at')->orderByDesc('id')->limit(3000)->get();

        // passagens ligadas (pra saber bilhete, passageiro, lancha e se foi cancelamento ou devolução)
        $passIds = $pgs->where('modulo', 'passagem')->pluck('origem_id')->filter()->unique()->values();
        $bilhetes = $pgs->map(fn ($p) => preg_match('/passagem\s+([A-Z0-9\-]+)/iu', (string) $p->descricao, $m) ? $m[1] : null)->filter()->unique()->values();
        $passPorId = $passIds->isNotEmpty() ? Passagem::whereIn('id', $passIds)->with(['passageiros:id,passagem_id,nome', 'embarcacao:id,nome'])->get()->keyBy('id') : collect();
        $passPorBil = $bilhetes->isNotEmpty() ? Passagem::whereIn('numero_completo', $bilhetes)->with(['passageiros:id,passagem_id,nome', 'embarcacao:id,nome'])->get()->keyBy('numero_completo') : collect();

        $linhas = $pgs->map(function ($p) use ($passPorId, $passPorBil) {
            $cat = (string) $p->categoria;
            $bil = preg_match('/passagem\s+([A-Z0-9\-]+)/iu', (string) $p->descricao, $m) ? $m[1] : null;
            $pas = $p->modulo === 'passagem' ? ($passPorId[$p->origem_id] ?? null) : ($bil ? ($passPorBil[$bil] ?? null) : null);
            $ehAvulso = $p->modulo === 'avulso' && $p->momento === 'cadastro';
            if ($ehAvulso) $tipo = 'avulso';
            elseif (str_starts_with($cat, 'Transferência')) $tipo = 'transferencia';
            else $tipo = ($pas && ($pas->cancel_tipo ?? '') === 'cancelamento') || str_contains(mb_strtolower((string) $p->descricao), 'cancelamento') ? 'cancelamento' : 'devolucao';

            // o que é, em palavras
            $oque = match (true) {
                $ehAvulso => $cat !== '' ? $cat : 'Avulso',
                $cat === 'Devolução ao passageiro' => 'Devolvido ao passageiro',
                $cat === 'Devolução — caixa local' => 'Retido (caixa local)',
                $cat === 'Débito do dono (devolução)' => 'Proprietário deve (passagem já acertada)',
                $cat === 'Débito do dono — abatido' => 'Débito do proprietário abatido na prestação',
                default => $cat,
            };
            return (object) [
                'id' => $p->id,
                'editavel' => $ehAvulso, // só avulso pode ser editado; derivados vêm da passagem
                'meio_raw' => (string) $p->meio,
                'categoria' => $cat,
                'data' => $p->created_at,
                'tipo' => $tipo,
                'oque' => $oque,
                'descricao' => (string) $p->descricao,
                'bilhete' => $pas ? $pas->numeroFormatado() : $bil,
                'passageiro' => $pas ? (string) (optional($pas->passageiros->first())->nome ?: $pas->comprador_nome) : null,
                'transporte' => (string) ($p->embarcacao?->nome ?? ($pas?->embarcacao?->nome ?? '')),
                'embarcacao_id' => $p->embarcacao_id ?: ($pas?->embarcacao_id),
                'operador' => (string) ($p->user?->name ?? ''),
                'forma' => Pagamento::MEIOS[$p->meio] ?? ucfirst((string) $p->meio),
                'mov' => $p->tipo_mov === 'saida' ? 'saida' : 'entrada',
                'valor' => (float) $p->valor,
            ];
        })
        ->when($tipo !== '', fn ($c) => $c->where('tipo', $tipo))
        ->when($mov !== '', fn ($c) => $c->where('mov', $mov))
        ->when($embId, fn ($c) => $c->filter(fn ($l) => (int) $l->embarcacao_id === (int) $embId))
        ->values();

        $resumo = [];
        foreach (self::TIPOS as $k => $nome) {
            $g = $linhas->where('tipo', $k);
            $resumo[$k] = ['nome' => $nome, 'qtd' => $g->count(), 'entradas' => round((float) $g->where('mov', 'entrada')->sum('valor'), 2), 'saidas' => round((float) $g->where('mov', 'saida')->sum('valor'), 2)];
        }

        return view('admin.movimentos', [
            'linhas' => $linhas,
            'resumo' => $resumo,
            'totEntradas' => round((float) $linhas->where('mov', 'entrada')->sum('valor'), 2),
            'totSaidas' => round((float) $linhas->where('mov', 'saida')->sum('valor'), 2),
            'inicio' => $inicio, 'fim' => $fim, 'tipo' => $tipo, 'mov' => $mov, 'embId' => $embId, 'userId' => $userId,
            'tipos' => self::TIPOS,
            'transportes' => Embarcacao::orderBy('nome')->get(['id', 'nome']),
            'usuarios' => User::where('empresa_id', \App\Support\Tenant::id())->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Edita um lançamento AVULSO (os derivados — devolução/transferência — vêm da passagem e não são editáveis aqui). */
    public function editar(Request $request, Pagamento $pagamento)
    {
        abort_unless($pagamento->empresa_id === \App\Support\Tenant::id(), 404);
        abort_unless($pagamento->modulo === 'avulso' && $pagamento->momento === 'cadastro', 422, 'Só lançamentos avulsos podem ser editados aqui.');
        abort_if($pagamento->estornado, 422, 'Lançamento estornado não pode ser editado.');
        abort_if((bool) $pagamento->prestacao_id, 422, 'Esse avulso já entrou numa prestação e não pode ser editado.');

        $data = $request->validate([
            'tipo_mov' => 'required|in:entrada,saida',
            'valor' => 'required|numeric|min:0.01|max:9999999',
            'meio' => 'nullable|string|max:20',
            'categoria' => 'nullable|string|max:80',
            'descricao' => 'nullable|string|max:255',
        ]);
        $pagamento->update([
            'tipo_mov' => $data['tipo_mov'],
            'valor' => round((float) $data['valor'], 2),
            'meio' => $data['meio'] ?: $pagamento->meio,
            'categoria' => trim((string) ($data['categoria'] ?? '')) ?: $pagamento->categoria,
            'descricao' => trim((string) ($data['descricao'] ?? '')),
        ]);

        return back()->with('ok', 'Lançamento avulso atualizado.');
    }

    /** Estorna (soft delete) um lançamento. Avulso some de vez; derivado (devolução/transferência) reverte só o
     *  lançamento no caixa — NÃO descancela a passagem (pra isso, mexer no cancelamento da passagem). */
    public function estornar(Pagamento $pagamento)
    {
        abort_unless($pagamento->empresa_id === \App\Support\Tenant::id(), 404);
        if ($pagamento->estornado) return back()->with('ok', 'Esse lançamento já estava estornado.');
        $pagamento->update(['estornado' => true]);

        return back()->with('ok', 'Lançamento estornado.');
    }
}
