<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Embarcacao;
use App\Models\Encomenda;
use App\Models\EncomendaItem;
use App\Models\Estacao;
use App\Models\Evento;
use App\Models\FilaImpressao;
use App\Models\Localidade;
use App\Models\Pagamento;
use App\Models\PreCadastro;
use App\Models\Produto;
use App\Models\Rota;
use App\Models\Setor;
use App\Models\AvulsoCategoria;
use App\Models\TabelaPreco;
use App\Models\TaxaEmbarque;
use App\Models\TipoPassageiro;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Cadastros auxiliares: embarcações, localidades, setores, produtos, estações de impressão. */
class CadastroController extends Controller
{
    protected array $tipos = [
        'embarcacoes' => Embarcacao::class,
        'localidades' => Localidade::class,
        'setores' => Setor::class,
        'produtos' => Produto::class,
        'categorias' => AvulsoCategoria::class,
        'tipos_passageiro' => TipoPassageiro::class,
    ];

    public function transportes()
    {
        return view('admin.transportes', ['embarcacoes' => Embarcacao::orderBy('nome')->get()]);
    }

    public function index()
    {
        return view('admin.cadastros', [
            'embarcacoes' => Embarcacao::orderBy('nome')->get(),
            'setores' => Setor::orderBy('nome')->get(),
            'produtos' => Produto::where('modulo', 'encomenda')->orderBy('nome')->get(),
            'produtosFrete' => Produto::where('modulo', 'frete')->orderBy('nome')->get(),
            'estacoes' => Estacao::orderBy('nome')->get(),
            'categorias' => AvulsoCategoria::orderBy('ordem')->orderBy('nome')->get(),
            'tiposPassageiro' => TipoPassageiro::where('ativo', true)->orderBy('eh_normal', 'desc')->orderBy('ordem')->orderBy('nome')->get(),
            'taxasEmbarque' => TaxaEmbarque::where('ativo', true)->orderBy('origem')->orderBy('destino')->get(),
            'trechoPrecosMapa' => \App\Models\TrechoPreco::where('ativo', true)->get(['origem', 'destino', 'tipo_passageiro_id', 'tarifa', 'alimentacao', 'integral'])
                ->mapWithKeys(fn ($r) => [mb_strtolower($r->origem.'|'.$r->destino.'|'.$r->tipo_passageiro_id) => ['tarifa' => (float) $r->tarifa, 'alim' => (float) $r->alimentacao, 'integral' => (float) $r->integral]])
                ->toArray(),
            'usaTaxaServico' => (bool) \App\Support\Tenant::empresa()->config('usa_taxa_servico', false),
            'usaAlimentacao' => (bool) \App\Support\Tenant::empresa()->config('usa_alimentacao', false),
            'labelTaxa' => \App\Support\Tenant::empresa()->config('label_taxa_servico') ?: 'Taxa de serviço',
            'labelAlim' => \App\Support\Tenant::empresa()->config('label_alimentacao') ?: 'Alimentação',
            'rotas' => \App\Support\Tenant::empresa()->temRecurso('viagens') ? Rota::with(['paradas', 'embarcacao'])->orderBy('nome')->get() : collect(),
            'localidadesNomes' => Rota::todasLocalidades(),
            'dias' => Rota::DIAS,
        ]);
    }

    public function store(Request $request, string $tipo)
    {
        abort_unless(isset($this->tipos[$tipo]), 404);

        if ($tipo === 'categorias') {
            $data = $request->validate([
                'nome' => 'required|string|max:60',
                'tipo' => 'required|in:entrada,saida,ambos',
                'conta_acerto' => 'nullable|boolean',
                'comissao_pct' => 'nullable|integer|min:0|max:100',
            ]);
            AvulsoCategoria::create([
                'nome' => $data['nome'], 'tipo' => $data['tipo'], 'ativo' => true,
                'conta_acerto' => $request->boolean('conta_acerto'),
                'comissao_pct' => (int) ($data['comissao_pct'] ?? 0),
            ]);
            return back()->with('ok', 'Categoria criada.');
        }

        $data = $request->validate([
            'nome' => 'required|string|max:120',
            'valor_padrao' => 'nullable|numeric|min:0',
            'modulo' => 'nullable|in:encomenda,frete',
        ]);
        $m = $this->tipos[$tipo];
        if ($tipo === 'produtos') {
            Produto::create(['nome' => $data['nome'], 'valor_padrao' => $data['valor_padrao'] ?? 0, 'modulo' => $data['modulo'] ?? 'encomenda', 'ativo' => true]);
        } elseif ($tipo === 'embarcacoes') {
            $m::create(['nome' => $data['nome'], 'comissao_empresa_pct' => 10]);
        } else {
            $m::create(['nome' => $data['nome']]);
        }
        return back()->with('ok', 'Cadastrado.');
    }

    public function destroy(string $tipo, int $id)
    {
        abort_unless(isset($this->tipos[$tipo]) || $tipo === 'estacoes', 404);
        $m = $tipo === 'estacoes' ? Estacao::class : $this->tipos[$tipo];
        $reg = $m::findOrFail($id);
        if (in_array($tipo, ['setores', 'estacoes', 'categorias', 'tipos_passageiro'])) {
            $reg->delete();
            return back()->with('ok', 'Removido.');
        }
        // Embarcacoes: so bloqueia a exclusao se tiver historico (passagens/encomendas/fretes).
        if ($tipo === 'embarcacoes') {
            $temUso = \App\Models\Passagem::where('embarcacao_id', $id)->exists()
                || \App\Models\Encomenda::where('embarcacao_id', $id)->exists()
                || \App\Models\Frete::where('embarcacao_id', $id)->exists();
            if ($temUso) {
                $reg->update(['ativo' => false]);
                return back()->with('erro', 'Este transporte tem passagens/encomendas registradas — nao pode ser excluido (o historico quebraria). Foi desativado.');
            }
        }
        $reg->delete();
        return back()->with('ok', 'Excluido.');
    }

    /** Cria ou edita um tipo de passageiro (porcentagem ou valor fixo). */
    /** Salva TODOS os tipos de passageiro de uma vez (editor em lote). */
    public function salvarTiposPassageiro(Request $request)
    {
        $data = $request->validate([
            'tipos' => 'required|array|min:1',
            'tipos.*.id' => 'nullable|integer',
            'tipos.*.nome' => 'required|string|max:60',
            'tipos.*.cor' => 'nullable|string|max:9',
            'tipos.*.modo' => 'required|in:porcentagem,valor',
            'tipos.*.percentual' => 'nullable|integer|min:0|max:100',
            'tipos.*.pct_sobre' => 'nullable|in:tarifa,tudo',
            'tipos.*.idade_min' => 'nullable|integer|min:0|max:120',
            'tipos.*.idade_max' => 'nullable|integer|min:0|max:120',
            'tipos.*.tarifa_padrao' => 'nullable|numeric|min:0',
            'tipos.*.alimentacao_padrao' => 'nullable|numeric|min:0',
            'tipos.*.eh_normal' => 'nullable',
            'tipos.*.paga_taxa' => 'nullable',
        ]);

        $vistos = [];
        $ordem = 1;
        $normalId = null;
        foreach ($data['tipos'] as $row) {
            $ehNormal = ! empty($row['eh_normal']);
            $modo = $ehNormal ? 'valor' : $row['modo']; // o Normal é sempre base de valor
            $payload = [
                'nome' => trim($row['nome']),
                'cor' => $row['cor'] ?: '#2563eb',
                'modo' => $modo,
                'percentual' => $modo === 'porcentagem' ? (int) ($row['percentual'] ?? 100) : 100,
                'pct_sobre' => $modo === 'porcentagem' ? (($row['pct_sobre'] ?? 'tarifa') === 'tudo' ? 'tudo' : 'tarifa') : 'tarifa',
                'idade_min' => ($row['idade_min'] ?? '') === '' ? null : (int) $row['idade_min'],
                'idade_max' => ($row['idade_max'] ?? '') === '' ? null : (int) $row['idade_max'],
                'tarifa_padrao' => $modo === 'valor' ? (float) ($row['tarifa_padrao'] ?? 0) : 0,
                'alimentacao_padrao' => $modo === 'valor' ? (float) ($row['alimentacao_padrao'] ?? 0) : 0,
                'paga_taxa' => ! empty($row['paga_taxa']),
                'eh_normal' => $ehNormal,
                'ativo' => true,
                'ordem' => $ordem++,
            ];
            if (! empty($row['id']) && ($t = TipoPassageiro::find($row['id']))) {
                $t->update($payload);
            } else {
                $t = TipoPassageiro::create($payload);
            }
            $vistos[] = $t->id;
            if ($ehNormal && ! $normalId) $normalId = $t->id;
        }

        // garante exatamente UM normal
        if (! $normalId && ! empty($vistos)) {
            $normalId = $vistos[0];
            TipoPassageiro::whereKey($normalId)->update(['eh_normal' => true, 'modo' => 'valor']);
        }
        TipoPassageiro::whereIn('id', $vistos)->where('id', '!=', $normalId)->update(['eh_normal' => false]);

        // removidos: desativa (preserva histórico de passagens)
        TipoPassageiro::whereNotIn('id', $vistos ?: [0])->update(['ativo' => false]);

        return back()->with('ok', 'Tipos de passageiro salvos.');
    }

    /** Configura poltrona de uma embarcação (usa/qtd/colunas). */
    public function salvarEmbarcacao(Request $request, Embarcacao $embarcacao)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:80',
            'usa_poltrona' => 'nullable|boolean',
            'poltronas_qtd' => 'nullable|integer|min:0|max:500',
            'poltronas_colunas' => 'nullable|integer|min:1|max:8',
            'poltronas_lista' => 'nullable|string|max:8000',
            'rastreio_url' => 'nullable|url|max:255',
            'cancel_antes_pct' => 'nullable|integer|min:0|max:100',
            'cancel_depois_pct' => 'nullable|integer|min:0|max:100',
            'dono_nome' => 'nullable|string|max:120',
            'comissao_empresa_pct' => 'nullable|integer|min:0|max:100',
            'ficha' => 'nullable|array',
            'tripulantes' => 'nullable|array',
            'logo' => 'nullable|image|max:2048',
        ]);
        $ficha = collect($request->input('ficha', []))->map(fn ($v) => is_string($v) ? trim($v) : $v)->filter(fn ($v) => $v !== null && $v !== '')->all();
        $trip = collect($request->input('tripulantes', []))->filter(fn ($t) => trim($t['nome'] ?? '') !== '')->map(fn ($t) => [
            'nome' => trim($t['nome']), 'funcao' => trim($t['funcao'] ?? ''), 'nascimento' => ($t['nascimento'] ?? '') ?: null,
            'cir' => trim($t['cir'] ?? ''), 'validade_cir' => ($t['validade_cir'] ?? '') ?: null,
        ])->values()->all();
        $embarcacao->update([
            'nome' => $data['nome'],
            'usa_poltrona' => $request->boolean('usa_poltrona'),
            'poltronas_qtd' => (int) ($data['poltronas_qtd'] ?? 0),
            'poltronas_colunas' => (int) ($data['poltronas_colunas'] ?? 4),
            'poltronas_lista' => trim((string) ($data['poltronas_lista'] ?? '')) ?: null,
            'rastreio_url' => trim((string) ($data['rastreio_url'] ?? '')) ?: null,
            'cancel_antes_pct' => ($data['cancel_antes_pct'] ?? '') !== '' ? (int) $data['cancel_antes_pct'] : null,
            'cancel_depois_pct' => ($data['cancel_depois_pct'] ?? '') !== '' ? (int) $data['cancel_depois_pct'] : null,
            'dono_nome' => trim((string) ($data['dono_nome'] ?? '')) ?: null,
            'comissao_empresa_pct' => (int) ($data['comissao_empresa_pct'] ?? 0),
            'ficha' => $ficha ?: null,
            'tripulantes' => $trip ?: null,
        ]);
        if ($request->hasFile('logo')) {
            $embarcacao->logo = 'storage/'.$request->file('logo')->store('logos', 'public');
            $embarcacao->save();
        }
        return back()->with('ok', 'Embarcação atualizada.');
    }

    public function novaEstacao(Request $request)
    {
        $data = $request->validate(['nome' => 'required|string|max:60']);
        $e = Estacao::create(['nome' => $data['nome'], 'token' => Str::random(48)]);
        return back()->with('ok', "Estação criada. Token: {$e->token}")->with('token', $e->token);
    }

    public function renomearEstacao(Request $request, Estacao $estacao)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:60',
            'colunas' => 'nullable|in:32,48',
        ]);
        $estacao->update(['nome' => $data['nome'], 'colunas' => (int) ($data['colunas'] ?? 48)]);
        return back()->with('ok', 'Estação atualizada.');
    }

    /**
     * Zera os dados operacionais (começar do zero): apaga encomendas, itens,
     * pagamentos, eventos, fila de impressão e pré-cadastros, e reinicia a
     * numeração em 000001. Mantém os cadastros (barcos, setores, produtos,
     * rotas, usuários, estações). Protegido por permissão (podeZerar).
     */
    public function zerar(Request $request)
    {
        abort_unless($request->user()->podeZerar(), 403, 'Sem permissão para zerar o sistema.');
        $request->validate(['confirmacao' => 'required|in:ZERAR']);

        $empresaId = Tenant::id();

        DB::transaction(function () use ($empresaId) {
            $encIds = Encomenda::pluck('id');                       // só da empresa (escopo global)
            EncomendaItem::whereIn('encomenda_id', $encIds)->delete(); // item não tem escopo próprio
            Pagamento::whereIn('encomenda_id', $encIds)->delete();
            Evento::query()->delete();                              // escopo por empresa
            FilaImpressao::query()->delete();
            PreCadastro::query()->delete();
            Encomenda::query()->delete();
            DB::table('empresas')->where('id', $empresaId)->update(['proximo_numero' => 1]);
        });

        return back()->with('ok', 'Sistema zerado. As encomendas começam do 000001.');
    }

    /** Exclui vendas de um modulo (passagem/encomenda/frete), de todos ou de um operador. */
    public function excluirVendas(Request $request)
    {
        abort_unless($request->user()->podeZerar(), 403, 'Sem permissão.');
        $data = $request->validate([
            'modulo' => 'required|in:passagem,encomenda,frete,avulso',
            'user_id' => 'nullable|integer',
            'confirmacao' => 'required|in:EXCLUIR',
        ]);
        $uid = $data['user_id'] ?: null;
        $n = 0;
        DB::transaction(function () use ($data, $uid, &$n) {
            if ($data['modulo'] === 'passagem') {
                $q = \App\Models\Passagem::query()->when($uid, fn ($x) => $x->where('user_id', $uid));
                $ids = (clone $q)->pluck('id');
                \App\Models\PassagemPassageiro::whereIn('passagem_id', $ids)->delete();
                Pagamento::where('modulo', 'passagem')->whereIn('origem_id', $ids)->delete();
                $n = $q->delete();
            } elseif ($data['modulo'] === 'encomenda') {
                $q = Encomenda::query()->when($uid, fn ($x) => $x->where('user_id', $uid));
                $ids = (clone $q)->pluck('id');
                EncomendaItem::whereIn('encomenda_id', $ids)->delete();
                Pagamento::whereIn('encomenda_id', $ids)->delete();
                Evento::whereIn('encomenda_id', $ids)->delete();
                $n = $q->delete();
            } elseif ($data['modulo'] === 'frete') {
                $q = \App\Models\Frete::query()->when($uid, fn ($x) => $x->where('user_id', $uid));
                $ids = (clone $q)->pluck('id');
                Pagamento::where('modulo', 'frete')->whereIn('origem_id', $ids)->delete();
                $n = $q->delete();
            } else { // avulso: entradas/saidas manuais do caixa
                $n = Pagamento::where('modulo', 'avulso')
                    ->when($uid, fn ($x) => $x->where('user_id', $uid))
                    ->delete();
            }
        });
        $rotulo = $data['modulo'] === 'avulso' ? 'lançamento(s) de caixa (entradas/saídas)' : 'venda(s) de '.$data['modulo'];
        return back()->with('ok', 'Excluídas '.$n.' '.$rotulo.($uid ? ' do operador selecionado' : '').'.');
    }

    /** Salva a tabela de Taxa de Embarque (Embarque → Destino → R$). */
    public function salvarTaxasEmbarque(Request $request)
    {
        $data = $request->validate([
            'taxas' => 'nullable|array',
            'taxas.*.origem' => 'nullable|string|max:80',
            'taxas.*.destino' => 'nullable|string|max:80',
            'taxas.*.taxa' => 'nullable|numeric|min:0',
        ]);
        TaxaEmbarque::query()->delete();
        foreach ($data['taxas'] ?? [] as $t) {
            $o = trim($t['origem'] ?? '');
            $d = trim($t['destino'] ?? '');
            if ($o === '' || $d === '' || mb_strtolower($o) === mb_strtolower($d)) continue;
            TaxaEmbarque::updateOrCreate(
                ['empresa_id' => \App\Support\Tenant::id(), 'origem' => $o, 'destino' => $d],
                ['taxa' => (float) ($t['taxa'] ?? 0), 'ativo' => true],
            );
        }
        return back()->with('ok', 'Taxas de embarque salvas.');
    }
}
