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

    public function index()
    {
        return view('admin.cadastros', [
            'embarcacoes' => Embarcacao::orderBy('nome')->get(),
            'setores' => Setor::orderBy('nome')->get(),
            'produtos' => Produto::orderBy('nome')->get(),
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
            ]);
            AvulsoCategoria::create(['nome' => $data['nome'], 'tipo' => $data['tipo'], 'ativo' => true]);
            return back()->with('ok', 'Categoria criada.');
        }

        $data = $request->validate([
            'nome' => 'required|string|max:120',
            'valor_padrao' => 'nullable|numeric|min:0',
        ]);
        $m = $this->tipos[$tipo];
        $m::create($tipo === 'produtos' ? $data : ['nome' => $data['nome']]);
        return back()->with('ok', 'Cadastrado.');
    }

    public function destroy(string $tipo, int $id)
    {
        abort_unless(isset($this->tipos[$tipo]) || $tipo === 'estacoes', 404);
        $m = $tipo === 'estacoes' ? Estacao::class : $this->tipos[$tipo];
        $reg = $m::findOrFail($id);
        if (in_array($tipo, ['setores', 'estacoes', 'categorias', 'tipos_passageiro'])) { $reg->delete(); }
        else { $reg->update(['ativo' => false]); }
        return back()->with('ok', 'Removido.');
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
        ]);
        $embarcacao->update([
            'nome' => $data['nome'],
            'usa_poltrona' => $request->boolean('usa_poltrona'),
            'poltronas_qtd' => (int) ($data['poltronas_qtd'] ?? 0),
            'poltronas_colunas' => (int) ($data['poltronas_colunas'] ?? 4),
        ]);
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
