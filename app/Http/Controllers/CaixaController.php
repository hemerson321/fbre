<?php

namespace App\Http\Controllers;

use App\Models\Encomenda;
use App\Models\Evento;
use App\Models\Pagamento;
use App\Models\User;
use App\Models\Viagem;
use App\Models\AvulsoCategoria;
use App\Support\Tenant;
use Illuminate\Http\Request;

class CaixaController extends Controller
{
    public function index(Request $request)
    {
        [$inicio, $fim, $periodo] = $this->periodo($request);
        // escopo: quem não vê "de todos" fica travado no próprio
        $verTodos = auth()->user()->caixaTodos();
        $userId = $verTodos ? $request->input('user_id') : auth()->id();
        $viagemId = $request->input('viagem_id');
        $modulo = $request->input('modulo');
        $canal = $request->input('canal'); // dinheiro|pix_manual|pix_auto|debito|credito
        $podeTransporte = auth()->user()->podeFiltrarTransporte();
        $transporteId = $podeTransporte ? $request->input('transporte_id') : null;
        $transportes = \App\Models\Embarcacao::orderBy('nome')->get();
        $dataViagem = $podeTransporte ? ($request->input('data_viagem') ?: null) : null;
        $datasViagem = $transporteId
            ? \App\Models\Passagem::where('embarcacao_id', $transporteId)->where('cancelada', false)->whereNotNull('data_viagem')
                ->whereBetween('data_viagem', [now()->subMonthsNoOverflow(3)->toDateString(), now()->addMonthsNoOverflow(3)->toDateString()])
                ->select('data_viagem')->distinct()->orderByDesc('data_viagem')->pluck('data_viagem')
            : collect();
        $filtraDataViagem = fn ($q) => $q->where('modulo', 'passagem')->whereIn('origem_id', \App\Models\Passagem::whereDate('data_viagem', $dataViagem)->select('id'));
        $filtraTransp = fn ($q) => $q->where(function ($w) use ($transporteId) {
            $w->where(fn ($x) => $x->where('modulo', 'passagem')->whereIn('origem_id', \App\Models\Passagem::where('embarcacao_id', $transporteId)->select('id')))
              ->orWhere(fn ($x) => $x->where('modulo', 'encomenda')->whereIn('encomenda_id', \App\Models\Encomenda::where('embarcacao_id', $transporteId)->select('id')));
        });
        $viagens = \App\Support\Tenant::empresa()->temRecurso('viagens')
            ? Viagem::orderByDesc('data_partida')->limit(60)->get() : collect();
        // quando filtra por viagem, ignora o período (viagem é o recorte)
        $encIds = $viagemId ? Encomenda::where('viagem_id', $viagemId)->pluck('id') : null;
        // venda de agente de repasse (revendedor) NAO entra no caixa ate o acerto; o repasse vira entrada na baixa
        $revendaIds = \App\Models\Passagem::whereNotNull('revendedor_id')->select('id');
        $semRevenda = function ($q) use ($revendaIds) {
            $q->where(fn ($w) => $w->where('modulo', '!=', 'passagem')->orWhereNotIn('origem_id', $revendaIds));
        };

        // ===== Escopo do DONO: se o usuario e dono de transporte (ex.: Cris admin+dona),
        // o caixa so mostra as vendas do(s) transporte(s) dele. Para ver tudo, usa o Relatorio. =====
        // dono que NAO e admin fica preso no transporte dele; admin/gerente/financeiro ve o caixa da empresa (guiche)
        $meusBarcos = (auth()->user()->ehDonoTransporte() && ! auth()->user()->caixaTodos())
            ? \App\Models\Embarcacao::where('dono_id', auth()->id())->pluck('id')->map(fn ($i) => (int) $i)->all()
            : null;
        if ($meusBarcos !== null) {
            $transportes = \App\Models\Embarcacao::whereIn('id', $meusBarcos)->orderBy('nome')->get();
            if ($transporteId && ! in_array((int) $transporteId, $meusBarcos, true)) $transporteId = null;
        }
        $escopoBarcos = ($meusBarcos !== null && ! $transporteId) ? $meusBarcos : null;
        $filtraBarcos = function ($q) use ($escopoBarcos) {
            $q->where(function ($w) use ($escopoBarcos) {
                $w->where(fn ($x) => $x->where('modulo', 'passagem')->whereIn('origem_id', \App\Models\Passagem::whereIn('embarcacao_id', $escopoBarcos)->select('id')))
                  ->orWhere(fn ($x) => $x->where('modulo', 'encomenda')->whereIn('encomenda_id', \App\Models\Encomenda::whereIn('embarcacao_id', $escopoBarcos)->select('id')));
            });
        };

        $base = Pagamento::where('estornado', false)
            ->where('meio', '!=', 'desconto')
            ->when($encIds === null, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($encIds !== null, fn ($q) => $q->whereIn('encomenda_id', $encIds))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($modulo, fn ($q) => $q->where('modulo', $modulo))
            ->when($canal, fn ($q) => $this->filtrarCanal($q, $canal))
            ->when($transporteId, $filtraTransp)
            ->when($escopoBarcos, $filtraBarcos)
            ->when($dataViagem, $filtraDataViagem)
            ->where('tipo_mov', '!=', 'saida')
            ->where($semRevenda);

        $canalSql = Pagamento::canalSql();
        $porMeio = (clone $base)->selectRaw("$canalSql as canal, SUM(valor) valor, COUNT(*) qtd")->groupBy('canal')->get()->keyBy('canal');
        $porOperador = (clone $base)->selectRaw("user_id, $canalSql as canal, SUM(valor) valor")->groupBy('user_id', 'canal')->get()
            ->groupBy('user_id')->map(fn ($rows) => [
                'total' => $rows->sum('valor'),
                'meios' => $rows->pluck('valor', 'canal'),
            ]);
        $porModulo = (clone $base)->selectRaw('modulo, SUM(valor) valor, COUNT(*) qtd')->groupBy('modulo')->get()->keyBy('modulo');

        $usuarios = User::where('empresa_id', Tenant::id())->orderBy('name')->get()->keyBy('id');

        $descontos = Pagamento::where('estornado', false)->where('meio', 'desconto')
            ->when($encIds === null, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($encIds !== null, fn ($q) => $q->whereIn('encomenda_id', $encIds))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))->when($modulo, fn ($q) => $q->where('modulo', $modulo))->when($transporteId, $filtraTransp)->when($escopoBarcos, $filtraBarcos)->when($dataViagem, $filtraDataViagem)->where($semRevenda)->sum('valor');

        $entregas = Evento::where('tipo', 'entrega')
            ->when($encIds === null, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($encIds !== null, fn ($q) => $q->whereIn('encomenda_id', $encIds))
            ->when($transporteId, fn ($q) => $q->whereIn('encomenda_id', \App\Models\Encomenda::where('embarcacao_id', $transporteId)->select('id')))
            ->when($escopoBarcos, fn ($q) => $q->whereIn('encomenda_id', \App\Models\Encomenda::whereIn('embarcacao_id', $escopoBarcos)->select('id')))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))->count();
        $cadastros = Encomenda::when($encIds === null, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when($transporteId, fn ($q) => $q->where('embarcacao_id', $transporteId))
            ->when($escopoBarcos, fn ($q) => $q->whereIn('embarcacao_id', $escopoBarcos))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))->count();

        $saidas = Pagamento::where('estornado', false)->where('tipo_mov', 'saida')
            ->when($encIds === null, fn ($q) => $q->whereBetween('created_at', [$inicio, $fim]))
            ->when($encIds !== null, fn ($q) => $q->whereIn('origem_id', $encIds))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($modulo, fn ($q) => $q->where('modulo', $modulo))->when($transporteId, $filtraTransp)->when($escopoBarcos, $filtraBarcos)->when($dataViagem, $filtraDataViagem)->sum('valor');

        $categorias = AvulsoCategoria::where('ativo', true)->orderBy('ordem')->orderBy('nome')->get();
        $podeAvulso = auth()->user()->podeModulo('avulso');

        $movimentos = (clone $base)->with(['user', 'encomenda'])->orderByDesc('id')->paginate(100)->withQueryString();

        // VENDAS (BRUTO): valor total vendido no filtro, independente de ter entrado no caixa (ex.: venda de agente ainda nao repassada)
        $filtroData = function ($q) use ($inicio, $fim, $userId, $transporteId, $escopoBarcos) {
            return $q->whereBetween('created_at', [$inicio, $fim])
                ->when($userId, fn ($x) => $x->where('user_id', $userId))
                ->when($transporteId, fn ($x) => $x->where('embarcacao_id', $transporteId))
                ->when($escopoBarcos, fn ($x) => $x->whereIn('embarcacao_id', $escopoBarcos));
        };
        $aplicaVB = function ($q) use ($viagemId, $userId, $transporteId, $filtroData, $escopoBarcos) {
            if ($viagemId) {
                return $q->where('viagem_id', $viagemId)
                    ->when($userId, fn ($x) => $x->where('user_id', $userId))
                    ->when($transporteId, fn ($x) => $x->where('embarcacao_id', $transporteId))
                    ->when($escopoBarcos, fn ($x) => $x->whereIn('embarcacao_id', $escopoBarcos));
            }
            return $filtroData($q);
        };
        $vendasBruto = 0.0;
        if (! $modulo || $modulo === 'passagem') $vendasBruto += (float) $aplicaVB(\App\Models\Passagem::where('cancelada', false)->where(fn ($q) => $q->whereNull('revendedor_id')->orWhere('acerto_empresa', true)->orWhere('acerto_dono', true))->when($dataViagem, fn ($x) => $x->whereDate('data_viagem', $dataViagem)))->sum('total');
        if (! $modulo || $modulo === 'encomenda') $vendasBruto += (float) $aplicaVB(Encomenda::query())->sum('total');
        if (! $modulo || $modulo === 'frete') $vendasBruto += (float) $filtroData(\App\Models\Frete::query())->sum('total');

        return view('caixa.index', compact('porMeio', 'porModulo', 'porOperador', 'usuarios', 'descontos', 'entregas', 'cadastros', 'movimentos', 'inicio', 'fim', 'periodo', 'userId', 'viagens', 'viagemId', 'verTodos', 'modulo', 'saidas', 'categorias', 'podeAvulso', 'canal', 'transportes', 'transporteId', 'podeTransporte', 'datasViagem', 'dataViagem'))
            ->with(['meios' => Pagamento::CANAIS, 'entradas' => $porMeio->sum('valor'), 'total' => $porMeio->sum('valor') - $saidas, 'vendasBruto' => $vendasBruto]);
    }

    /** Aplica o filtro por canal (PIX dividido em manual/automático). */
    protected function filtrarCanal($q, string $canal)
    {
        return match ($canal) {
            'pix_manual' => $q->where('meio', 'pix')->where(fn ($w) => $w->where('pix_auto', false)->orWhereNull('pix_auto')),
            'pix_auto' => $q->where('meio', 'pix')->where('pix_auto', true),
            'dinheiro', 'debito', 'credito' => $q->where('meio', $canal),
            default => $q,
        };
    }

    /** Registra uma entrada ou saída avulsa no caixa. */
    public function avulso(Request $request)
    {
        abort_unless($request->user()->podeModulo('avulso'), 403, 'Sem permissão para lançar avulso.');
        $data = $request->validate([
            'tipo_mov' => 'required|in:entrada,saida',
            'categoria' => 'nullable|string|max:60',
            'valor' => 'required|numeric|min:0.01',
            'meio' => 'required|in:dinheiro,pix,debito,credito',
            'descricao' => 'nullable|string|max:200',
        ]);
        $cat = ! empty($data['categoria']) ? AvulsoCategoria::where('nome', $data['categoria'])->first() : null;
        Pagamento::create([
            'empresa_id' => Tenant::id(),
            'modulo' => 'avulso',
            'origem_id' => null,
            'encomenda_id' => null,
            'meio' => $data['meio'],
            'valor' => $data['valor'],
            'user_id' => auth()->id(),
            'momento' => 'cadastro',
            'tipo_mov' => $data['tipo_mov'],
            'categoria' => $data['categoria'] ?? null,
            'descricao' => $data['descricao'] ?? null,
            'acerto_conta' => $cat ? (bool) $cat->conta_acerto : true,
            'acerto_pct' => $cat ? (int) $cat->comissao_pct : 0,
        ]);
        return back()->with('ok', ($data['tipo_mov'] === 'entrada' ? 'Entrada' : 'Saída') . ' avulsa registrada.');
    }

    /** Resolve o período: hoje | semana | mes | personalizado */
    public static function periodo(Request $request): array
    {
        $p = $request->input('periodo', 'hoje');
        return match ($p) {
            'semana' => [now()->startOfWeek(), now()->endOfWeek(), 'semana'],
            'mes' => [now()->startOfMonth(), now()->endOfMonth(), 'mes'],
            'personalizado' => [
                \Carbon\Carbon::parse($request->input('inicio', now()->toDateString()).' '.$request->input('hora_inicio', '00:00')),
                \Carbon\Carbon::parse($request->input('fim', now()->toDateString()).' '.$request->input('hora_fim', '23:59')),
                'personalizado',
            ],
            default => [now()->startOfDay(), now()->endOfDay(), 'hoje'],
        };
    }
}
