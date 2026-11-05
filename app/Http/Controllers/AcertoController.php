<?php

namespace App\Http\Controllers;

use App\Models\Encomenda;
use App\Models\Pagamento;
use App\Models\Passagem;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Acerto: empresa ↔ revendedores (admin) e revendedor ↔ filiados (revendedor).
 */
class AcertoController extends Controller
{
    // ---------- ADMIN: acerto da empresa (operadores de balcão + agentes externos) ----------
    public function index(Request $request)
    {
        abort_unless(auth()->user()->podeAcerto(), 403);
        [$inicio, $fim, $periodo] = CaixaController::periodo($request);

        $transporteId = $request->input('transporte_id') ?: null;
        $viagemId = $request->input('viagem_id') ?: null;
        $opFiltro = $request->input('user_id') ?: null;

        $transportes = \App\Models\Embarcacao::orderBy('nome')->get();
        $viagens = Tenant::empresa()?->temRecurso('viagens')
            ? \App\Models\Viagem::orderByDesc('data_partida')->limit(60)->get() : collect();
        $pessoas = User::where('empresa_id', Tenant::id())->orderBy('name')->get();

        $ft = $this->filtraTransp($transporteId);
        $fv = $this->filtraViagem($viagemId);

        // Agentes externos (revendedores): devem repassar das passagens vendidas.
        $linhas = User::where('is_revendedor', true)
            ->when($opFiltro, fn ($q) => $q->where('id', $opFiltro))
            ->orderBy('name')->get()->map(function ($r) use ($transporteId, $viagemId) {
                $base = Passagem::where('revendedor_id', $r->id)->where('cancelada', false)
                    ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
                    ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId));
                $pend = (clone $base)->where('acerto_empresa', false);
                return [
                    'rev' => $r,
                    'qtd' => (clone $pend)->count(),
                    'total' => (float) (clone $pend)->sum('acerto_empresa_valor'),
                    'acertado' => (float) (clone $base)->where('acerto_empresa', true)->sum('acerto_empresa_valor'),
                ];
            });

        // Operadores de balcão (não revendedores): conferir/validar o caixa do período.
        $ops = User::where('empresa_id', Tenant::id())->where('is_revendedor', false)
            ->when($opFiltro, fn ($q) => $q->where('id', $opFiltro))
            ->orderBy('name')->get()
            ->map(function ($o) use ($inicio, $fim, $transporteId, $viagemId, $ft, $fv) {
                $base = Pagamento::where('user_id', $o->id)->where('estornado', false)
                    ->where('tipo_mov', '!=', 'saida')->where('meio', '!=', 'desconto')
                    ->whereBetween('created_at', [$inicio, $fim])
                    ->when($transporteId, $ft)->when($viagemId, $fv);
                $pend = (clone $base)->where('conferido', false);
                return [
                    'op' => $o,
                    'qtd' => (clone $pend)->count(),
                    'dinheiro' => (float) (clone $pend)->where('meio', 'dinheiro')->sum('valor'),
                    'pix' => (float) (clone $pend)->where('meio', 'pix')->sum('valor'),
                    'total' => (float) (clone $pend)->sum('valor'),
                    'conferido' => (float) (clone $base)->where('conferido', true)->sum('valor'),
                ];
            })->filter(fn ($l) => $l['qtd'] > 0 || $l['conferido'] > 0)->values();

        // Donos de transporte: a empresa fica com a comissao e paga o resto pro dono.
        $donos = \App\Models\Embarcacao::whereNotNull('dono_nome')->where('dono_nome', '!=', '')
            ->when($transporteId, fn ($q) => $q->where('id', $transporteId))
            ->orderBy('nome')->get()->map(function ($emb) use ($viagemId) {
                $pct = (int) $emb->comissao_empresa_pct;
                $base = Passagem::where('embarcacao_id', $emb->id)->where('cancelada', false)->where('transferida', false)
                    ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId));
                $pend = (clone $base)->where('acerto_dono', false);
                return [
                    'emb' => $emb,
                    'pct' => $pct,
                    'qtd' => (clone $pend)->count(),
                    'dono' => (float) (clone $pend)->selectRaw('COALESCE(SUM(total * (100 - '.$pct.') / 100),0) as v')->value('v'),
                    'pago' => (float) (clone $base)->where('acerto_dono', true)->selectRaw('COALESCE(SUM(total * (100 - '.$pct.') / 100),0) as v')->value('v'),
                ];
            })->filter(fn ($l) => $l['qtd'] > 0 || $l['pago'] > 0)->values();

        $aba = $request->input('aba');
        return view('acertos.index', compact(
            'linhas', 'ops', 'donos', 'inicio', 'fim', 'periodo', 'aba',
            'transportes', 'viagens', 'pessoas', 'transporteId', 'viagemId', 'opFiltro'
        ));
    }

    /** ADMIN paga o dono do transporte (marca as passagens do transporte como acertadas com o dono). */
    public function baixaDono(Request $request, \App\Models\Embarcacao $embarcacao)
    {
        abort_unless(auth()->user()->podeAcerto(), 403);
        abort_unless($embarcacao->empresa_id === Tenant::id(), 404);
        $viagemId = $request->input('viagem_id') ?: null;
        $n = Passagem::where('embarcacao_id', $embarcacao->id)->where('cancelada', false)->where('transferida', false)->where('acerto_dono', false)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->update(['acerto_dono' => true, 'acerto_dono_em' => now(), 'acerto_dono_por' => auth()->id()]);
        return back()->with('ok', 'Acerto do dono de '.$embarcacao->nome.' pago ('.$n.' passagens).');
    }

    /** Relatorio do acerto de um transporte pra mandar pro dono (admin compartilha no WhatsApp). */
    public function donoRelatorio(Request $request, \App\Models\Embarcacao $embarcacao)
    {
        abort_unless(auth()->user()->podeAcerto(), 403);
        abort_unless($embarcacao->empresa_id === Tenant::id(), 404);
        $viagemId = $request->input('viagem_id') ?: null;
        $pct = (int) $embarcacao->comissao_empresa_pct;
        $base = Passagem::where('embarcacao_id', $embarcacao->id)->where('cancelada', false)->where('transferida', false)->where('acerto_dono', false)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId));
        $passagens = (clone $base)->orderByDesc('id')->limit(500)->get();
        $bruto = (float) (clone $base)->sum('total');
        $dono = round($bruto * (100 - $pct) / 100, 2);
        $comissao = round($bruto - $dono, 2);
        return view('acertos.dono', compact('embarcacao', 'passagens', 'bruto', 'dono', 'comissao', 'pct', 'viagemId'));
    }

    public function baixaEmpresa(Request $request, User $revendedor)
    {
        abort_unless(auth()->user()->podeAcerto(), 403);
        abort_unless($revendedor->empresa_id === Tenant::id(), 404);
        $transporteId = $request->input('transporte_id') ?: null;
        $viagemId = $request->input('viagem_id') ?: null;
        $n = Passagem::where('revendedor_id', $revendedor->id)->where('cancelada', false)->where('acerto_empresa', false)
            ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->update(['acerto_empresa' => true, 'acerto_empresa_em' => now(), 'acerto_empresa_por' => auth()->id()]);
        if (! $transporteId && ! $viagemId) {
            Pagamento::where('user_id', $revendedor->id)->where('modulo', 'avulso')->where('conferido', false)
                ->update(['conferido' => true, 'conferido_em' => now(), 'conferido_por' => auth()->id()]);
        }
        return back()->with('ok', 'Acerto de '.$revendedor->name.' baixado ('.$n.' passagens).');
    }

    /** ADMIN confere/valida o caixa de um operador de balcão no período + filtros mostrados. */
    public function baixaOperador(Request $request, User $operador)
    {
        abort_unless(auth()->user()->podeAcerto(), 403);
        abort_unless($operador->empresa_id === Tenant::id(), 404);
        [$inicio, $fim] = CaixaController::periodo($request);
        $transporteId = $request->input('transporte_id') ?: null;
        $viagemId = $request->input('viagem_id') ?: null;
        $n = Pagamento::where('user_id', $operador->id)->where('estornado', false)
            ->where('tipo_mov', '!=', 'saida')->where('conferido', false)
            ->whereBetween('created_at', [$inicio, $fim])
            ->when($transporteId, $this->filtraTransp($transporteId))
            ->when($viagemId, $this->filtraViagem($viagemId))
            ->update(['conferido' => true, 'conferido_em' => now(), 'conferido_por' => auth()->id()]);
        return back()->with('ok', 'Caixa de '.$operador->name.' conferido ('.$n.' lançamentos).');
    }

    /** ADMIN confere o caixa de TODOS os operadores no período + filtros mostrados. */
    public function baixaOperadoresTodos(Request $request)
    {
        abort_unless(auth()->user()->podeAcerto(), 403);
        [$inicio, $fim] = CaixaController::periodo($request);
        $transporteId = $request->input('transporte_id') ?: null;
        $viagemId = $request->input('viagem_id') ?: null;
        $opIds = User::where('empresa_id', Tenant::id())->where('is_revendedor', false)
            ->when($request->input('user_id'), fn ($q) => $q->where('id', $request->input('user_id')))->select('id');
        $n = Pagamento::where('estornado', false)->where('tipo_mov', '!=', 'saida')->where('conferido', false)
            ->whereIn('user_id', $opIds)->whereBetween('created_at', [$inicio, $fim])
            ->when($transporteId, $this->filtraTransp($transporteId))
            ->when($viagemId, $this->filtraViagem($viagemId))
            ->update(['conferido' => true, 'conferido_em' => now(), 'conferido_por' => auth()->id()]);
        return back()->with('ok', 'Caixa de todos os operadores conferido ('.$n.' lançamentos).');
    }

    /** ADMIN dá baixa no acerto de TODOS os agentes externos (com filtros transporte/viagem). */
    public function baixaAgentesTodos(Request $request)
    {
        abort_unless(auth()->user()->podeAcerto(), 403);
        $transporteId = $request->input('transporte_id') ?: null;
        $viagemId = $request->input('viagem_id') ?: null;
        $revIds = User::where('empresa_id', Tenant::id())->where('is_revendedor', true)
            ->when($request->input('user_id'), fn ($q) => $q->where('id', $request->input('user_id')))->pluck('id');
        $n = Passagem::whereIn('revendedor_id', $revIds)->where('cancelada', false)->where('acerto_empresa', false)
            ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->update(['acerto_empresa' => true, 'acerto_empresa_em' => now(), 'acerto_empresa_por' => auth()->id()]);
        if (! $transporteId && ! $viagemId) {
            Pagamento::whereIn('user_id', $revIds)->where('modulo', 'avulso')->where('conferido', false)
                ->update(['conferido' => true, 'conferido_em' => now(), 'conferido_por' => auth()->id()]);
        }
        return back()->with('ok', 'Baixa feita em todos os agentes ('.$n.' passagens).');
    }

    /** Agente externo gera um PIX (Mercado Pago da empresa) pra acertar; baixa sozinho no webhook. */
    public function pagarEmpresa(Request $request, \App\Services\MercadoPago $mp)
    {
        $u = auth()->user();
        abort_unless($u->ehRevendedor(), 403);
        $empresa = Tenant::empresa();
        $base = Passagem::where('revendedor_id', $u->id)->where('cancelada', false)->where('acerto_empresa', false);
        $repasse = (float) (clone $base)->sum('acerto_empresa_valor');
        $avEnt = (float) Pagamento::where('user_id', $u->id)->where('estornado', false)->where('modulo', 'avulso')->where('tipo_mov', 'entrada')->where('conferido', false)->where('acerto_conta', true)->selectRaw('COALESCE(SUM(valor * (100 - COALESCE(acerto_pct,0)) / 100),0) as v')->value('v');
        $avSai = (float) Pagamento::where('user_id', $u->id)->where('estornado', false)->where('modulo', 'avulso')->where('tipo_mov', 'saida')->where('conferido', false)->where('acerto_conta', true)->sum('valor');
        $valor = round($repasse + $avEnt - $avSai, 2);
        if ($valor <= 0) return response()->json(['ok' => false, 'erro' => 'Você não tem valor pendente pra acertar.']);
        $api = $u->acerto_pix_api_id ? \App\Models\PagamentoApi::find($u->acerto_pix_api_id) : null;
        if (! $api || $api->ehEstatico()) return response()->json(['ok' => false, 'erro' => 'Esse agente não está no modo PIX automático (Mercado Pago).']);
        $r = $mp->cobrarPix($api, $valor, 'Acerto '.$u->name, ['nome' => $u->name]);
        if (! ($r['ok'] ?? false)) return response()->json(['ok' => false, 'erro' => 'Não consegui gerar o PIX: '.($r['erro'] ?? 'erro')]);
        $cob = \App\Models\Cobranca::create([
            'empresa_id' => $empresa->id, 'pagamento_api_id' => $api->id, 'user_id' => $u->id,
            'modulo' => 'passagem', 'referencia_tipo' => 'acerto', 'referencia_id' => $u->id,
            'provider' => 'mercadopago', 'provider_payment_id' => $r['payment_id'] ?? null,
            'valor' => $valor, 'status' => 'pendente',
            'qr_copia_cola' => $r['copia_cola'] ?? null, 'qr_base64' => $r['qr_base64'] ?? null,
            'ticket_url' => $r['ticket_url'] ?? null, 'cliente_nome' => $u->name,
        ]);
        return response()->json(['ok' => true, 'id' => $cob->id, 'valor' => $valor, 'qr_base64' => $cob->qr_base64, 'copia_cola' => $cob->qr_copia_cola]);
    }

    /** Pagamento (passagem/encomenda) ligado a uma embarcação. */
    protected function filtraTransp(?int $t): \Closure
    {
        return function ($q) use ($t) {
            $q->where(function ($w) use ($t) {
                $w->where(fn ($x) => $x->where('modulo', 'passagem')->whereIn('origem_id', Passagem::where('embarcacao_id', $t)->select('id')))
                  ->orWhere(fn ($x) => $x->where('modulo', 'encomenda')->whereIn('encomenda_id', Encomenda::where('embarcacao_id', $t)->select('id')));
            });
        };
    }

    /** Pagamento (passagem/encomenda) ligado a uma viagem. */
    protected function filtraViagem($v): \Closure
    {
        return function ($q) use ($v) {
            $q->where(function ($w) use ($v) {
                $w->where(fn ($x) => $x->where('modulo', 'passagem')->whereIn('origem_id', Passagem::where('viagem_id', $v)->select('id')))
                  ->orWhere(fn ($x) => $x->where('modulo', 'encomenda')->whereIn('encomenda_id', Encomenda::where('viagem_id', $v)->select('id')));
            });
        };
    }

    // ---------- REVENDEDOR: meus filiados + acerto com eles ----------
    public function meus()
    {
        $u = auth()->user();
        abort_unless($u->ehRevendedor(), 403);
        $filiados = User::where('revendedor_id', $u->id)->orderBy('name')->get();
        $linhas = $filiados->map(function ($f) {
            $base = Passagem::where('user_id', $f->id)->where('cancelada', false);
            $pend = (clone $base)->where('acerto_revendedor', false);
            return [
                'fil' => $f,
                'qtd' => (clone $pend)->count(),
                'total' => (float) (clone $pend)->sum('acerto_revendedor_valor'),
                'acertado' => (float) (clone $base)->where('acerto_revendedor', true)->sum('acerto_revendedor_valor'),
            ];
        });
        return view('acertos.meus', ['rev' => $u, 'filiados' => $filiados, 'linhas' => $linhas]);
    }

    public function baixaFiliado(Request $request, User $filiado)
    {
        $u = auth()->user();
        abort_unless($u->ehRevendedor() && $filiado->revendedor_id === $u->id, 403);
        Passagem::where('user_id', $filiado->id)->where('cancelada', false)->where('acerto_revendedor', false)
            ->update(['acerto_revendedor' => true, 'acerto_revendedor_em' => now(), 'acerto_revendedor_por' => auth()->id()]);
        return back()->with('ok', 'Acerto do filiado baixado.');
    }

    /** Revendedor cria/edita um filiado (vendedor vinculado a ele). */
    public function salvarFiliado(Request $request, ?User $filiado = null)
    {
        $u = auth()->user();
        abort_unless($u->ehRevendedor(), 403);
        if ($filiado && $filiado->exists) abort_unless($filiado->revendedor_id === $u->id, 404);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'login' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9._-]+$/'],
            'password' => ($filiado && $filiado->exists) ? 'nullable|string|min:4' : 'required|string|min:4',
            'prefixo' => ['required', 'string', 'max:8', 'regex:/^[A-Za-z0-9]+$/'],
            'modo_acerto' => 'required|in:comissao,cheio',
            'comissao_pct' => 'nullable|integer|min:0|max:100',
            'ativo' => 'nullable|boolean',
        ]);

        $payload = [
            'name' => $data['name'],
            'login' => $data['login'],
            'prefixo' => strtoupper($data['prefixo']),
            'revendedor_id' => $u->id,
            'is_revendedor' => false,
            'modo_acerto' => $data['modo_acerto'],
            'comissao_pct' => (int) ($data['comissao_pct'] ?? 0),
            'ativo' => $request->boolean('ativo', true),
            'empresa_id' => $u->empresa_id,
            'mod_perms' => ['passagem' => ['cadastra' => true, 'imprimir' => true]],
        ];
        if (! empty($data['password'])) $payload['password'] = Hash::make($data['password']);

        if ($filiado && $filiado->exists) {
            $filiado->update($payload);
            return back()->with('ok', 'Filiado atualizado.');
        }
        User::create($payload + ['proximo_seq' => 1]);
        return back()->with('ok', 'Filiado criado.');
    }
}
