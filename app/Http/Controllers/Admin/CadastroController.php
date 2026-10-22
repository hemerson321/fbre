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
            'tiposPassageiro' => TipoPassageiro::orderBy('ordem')->orderBy('nome')->get(),
            'precosMapa' => TabelaPreco::where('ativo', true)->get(['origem', 'destino', 'passagem'])
                ->mapWithKeys(fn ($t) => [mb_strtolower($t->origem.'|'.$t->destino) => (float) $t->passagem])
                ->toArray(),
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
    public function salvarTipoPassageiro(Request $request, ?TipoPassageiro $tipo = null)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:60',
            'modo' => 'required|in:porcentagem,valor',
            'percentual' => 'nullable|integer|min:0|max:100',
            'valor_fixo' => 'nullable|numeric|min:0',
            'paga_taxa' => 'nullable|boolean',
        ]);
        $payload = [
            'nome' => $data['nome'],
            'modo' => $data['modo'],
            'percentual' => $data['modo'] === 'porcentagem' ? (int) ($data['percentual'] ?? 100) : 100,
            'valor_fixo' => $data['modo'] === 'valor' ? (float) ($data['valor_fixo'] ?? 0) : 0,
            'paga_taxa' => $request->boolean('paga_taxa'),
            'ativo' => true,
        ];
        if ($tipo && $tipo->exists) {
            $tipo->update($payload);
            return back()->with('ok', 'Tipo atualizado.');
        }
        TipoPassageiro::create($payload + ['ordem' => (int) (TipoPassageiro::max('ordem') ?? 0) + 1]);
        return back()->with('ok', 'Tipo criado.');
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
}
