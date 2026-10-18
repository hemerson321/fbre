<?php

namespace App\Http\Controllers;

use App\Models\Embarcacao;
use App\Models\Encomenda;
use App\Models\Evento;
use App\Models\Localidade;
use App\Models\Pagamento;
use App\Models\Pessoa;
use App\Models\Produto;
use App\Models\Rota;
use App\Models\Viagem;
use App\Models\Setor;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EncomendaController extends Controller
{
    public function inicio()
    {
        return view('home');
    }

    // ------------------------------------------------------------------
    // LISTA / FILTRO
    // ------------------------------------------------------------------
    public function index(Request $request)
    {
        $f = $this->filtros($request);
        $base = Encomenda::filtrar($f);

        $totais = [
            'qtd'       => (clone $base)->count(),
            'volumes'   => (clone $base)->sum('volumes'),
            'total'     => (clone $base)->sum('total'),
            'recebido'  => (clone $base)->sum('pago'),
            'a_receber' => (clone $base)->sum('devido'),
            'desconto'  => (clone $base)->sum('desconto'),
        ];

        $ids = (clone $base)->select('id');
        $porMeio = Pagamento::whereIn('encomenda_id', $ids)
            ->where('meio', '!=', 'desconto')
            ->selectRaw('meio, SUM(valor) as valor')
            ->groupBy('meio')->pluck('valor', 'meio');

        $encomendas = (clone $base)->with(['embarcacao', 'operador'])
            ->orderByDesc('id')->paginate(50)->withQueryString();

        // Viagens para o filtro: vêm do calendário das rotas (dia da semana + hora).
        $temViagens = Tenant::empresa()->temRecurso('viagens');
        $rotas = $temViagens ? Rota::where('ativo', true)->with('paradas')->orderBy('nome')->get() : collect();
        $viagens = collect();
        if ($temViagens) {
            foreach ($rotas as $rota) {
                $hora = $rota->horaPartida();
                foreach ($rota->proximasViagens() as $v) {
                    $v->rota_nome = $rota->nome;
                    $v->hora_label = $hora;
                    $viagens->push($v);
                }
            }
            $viagens = $viagens->sortByDesc(fn ($v) => optional($v->data_partida)->timestamp ?? 0)->values();
        }

        return view('encomendas.index', [
            'encomendas' => $encomendas,
            'totais' => $totais,
            'porMeio' => $porMeio,
            'f' => $f,
            'localidades' => Rota::todasLocalidades(),
            'embarcacoes' => Embarcacao::where('ativo', true)->orderBy('nome')->get(),
            'rotas' => $rotas,
            'viagens' => $viagens,
        ]);
    }

    protected function filtros(Request $request): array
    {
        $f = $request->only(['inicio', 'fim', 'hora_inicio', 'hora_fim', 'origem', 'destino', 'embarcacao_id', 'viagem_id', 'rota_id', 'status_pagamento', 'entregue', 'forma', 'busca', 'user_id']);
        if (! $request->has('inicio') && ! $request->filled('busca')) {
            $f['inicio'] = now()->subDays(7)->toDateString();
            $f['fim'] = now()->toDateString();
        }
        return $f;
    }

    // ------------------------------------------------------------------
    // CADASTRO
    // ------------------------------------------------------------------
    public function create()
    {
        $u = auth()->user();

        // pré-cadastro (cliente preencheu pelo QR) para pré-preencher o formulário
        $pre = [];
        if ($preId = request('pre')) {
            if ($p = \App\Models\PreCadastro::where('status', 'pendente')->find($preId)) {
                $pre = [
                    'id' => $p->id, 'codigo' => $p->codigo, 'remetente' => $p->remetente_nome,
                    'origem' => $p->origem, 'destino' => $p->destino,
                    'remetente_nome' => $p->remetente_nome, 'remetente_telefone' => $p->remetente_telefone,
                    'destinatario_nome' => $p->destinatario_nome, 'destinatario_telefone' => $p->destinatario_telefone,
                    'observacao' => $p->observacao,
                    'itens' => collect($p->itens ?? [])->map(fn ($i) => [
                        'id' => uniqid(), 'descricao' => $i['descricao'] ?? '', 'quantidade' => $i['quantidade'] ?? 1, 'valor' => 0, 'obs' => '',
                    ])->values(),
                ];
            }
        }

        $cidadesPerm = $u->cidadesPermitidas();
        $setoresPerm = $u->setoresPermitidos();
        $transpPerm = $u->transportesPermitidos();

        $embarcacoes = Embarcacao::where('ativo', true)->orderBy('nome')->get();
        if ($transpPerm) $embarcacoes = $embarcacoes->whereIn('id', $transpPerm)->values();

        $setores = Setor::orderBy('nome')->pluck('nome');
        if ($setoresPerm) $setores = $setores->intersect($setoresPerm)->values();

        $localidades = Rota::todasLocalidades();
        if ($cidadesPerm) $localidades = $localidades->intersect($cidadesPerm)->values();

        $rotas = Tenant::empresa()->temRecurso('viagens') ? Rota::paraSelecao() : collect();
        // linhas (com paradas) para o JS resolver a viagem a partir de origem/destino
        $linhas = $rotas->map(fn ($r) => [
            'id' => $r->id,
            'nome' => $r->nome,
            'paradas' => $r->paradas->map(fn ($p) => ['n' => $p->localidade, 'd' => $p->dia_semana, 'h' => $p->hora ? substr($p->hora, 0, 5) : null])->values(),
        ])->values();

        $catalogo = Produto::where('ativo', true)->orderByDesc('usos')->orderBy('nome')
            ->get(['id', 'nome', 'valor_padrao'])
            ->map(fn ($p) => ['id' => $p->id, 'nome' => $p->nome, 'valor' => (float) $p->valor_padrao])->values();

        return view('encomendas.create', [
            'localidades' => $localidades,
            'cidades' => $localidades->values(),
            'linhas' => $linhas,
            'embarcacoes' => $embarcacoes,
            'catalogo' => $catalogo,
            'setores' => $setores,
            'rotas' => $rotas,
            'ultimaOrigem' => session('ultima_origem') ?: (Tenant::empresa()->cidade ?: $localidades->first()),
            'embarcacaoPrincipal' => session('ultima_embarcacao') ?: $u->embarcacao_principal_id,
            'restringeSetor' => ! empty($setoresPerm),
            'podeRetroativo' => $u->podeRetroativo(),
            'pre' => $pre,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'origem' => 'required|string|max:80',
            'destino' => 'required|string|max:80',
            'embarcacao_id' => 'nullable|integer',
            'rota_id' => 'nullable|integer',
            'viagem_id' => 'nullable|integer',
            'viagem_data' => 'nullable|date',
            'lancamento_em' => 'nullable|date',
            'pre_id' => 'nullable|integer',
            'remetente_nome' => 'required|string|max:120',
            'remetente_telefone' => 'nullable|string|max:30',
            'remetente_documento' => 'nullable|string|max:20',
            'destinatario_nome' => 'required|string|max:120',
            'destinatario_telefone' => 'nullable|string|max:30',
            'destinatario_documento' => 'nullable|string|max:20',
            'volumes' => 'required|integer|min:1|max:999',
            'forma' => 'required|in:avista,areceber,faturado',
            'setor' => 'nullable|string|max:60',
            'observacao' => 'nullable|string|max:1000',
            'itens' => 'required|array|min:1',
            'itens.*.descricao' => 'required|string|max:120',
            'itens.*.quantidade' => 'required|numeric|min:0.01',
            'itens.*.valor_unitario' => 'required|numeric|min:0',
            'itens.*.observacao' => 'nullable|string|max:200',
            'desconto' => 'nullable|numeric|min:0',
            'pagamentos' => 'nullable|array',
            'pagamentos.*.meio' => 'required_with:pagamentos|in:dinheiro,pix,debito,credito',
            'pagamentos.*.valor' => 'required_with:pagamentos|numeric|min:0.01',
            'imprimir' => 'nullable|in:navegador,direto,nao',
            'uuid' => 'nullable|uuid',
        ]);

        if (! auth()->user()->podeRetroativo()) {
            unset($data['lancamento_em']);
            if (! empty($data['viagem_data']) && $data['viagem_data'] < now()->toDateString()) unset($data['viagem_data']);
        }

        if (! auth()->user()->podeParcial()) {
            $total = collect($data['itens'])->sum(fn ($i) => (float) $i['quantidade'] * (float) $i['valor_unitario']) - (float) ($data['desconto'] ?? 0);
            $pago = collect($data['pagamentos'] ?? [])->sum(fn ($p) => (float) $p['valor']);
            abort_if($pago > 0.009 && $pago < $total - 0.009, 422, 'Pagamento parcial não permitido. Receba o total ou deixe a receber.');
        }

        $encomenda = app(\App\Services\EncomendaService::class)->criar($data, auth()->id(), $request->input('uuid'));

        if (! empty($data['pre_id'])) {
            \App\Models\PreCadastro::where('status', 'pendente')->where('id', $data['pre_id'])
                ->update(['status' => 'finalizado', 'encomenda_id' => $encomenda->id]);
        }

        session(['ultima_origem' => $data['origem']]);

        $imprimir = $data['imprimir'] ?? 'navegador';

        if ($imprimir === 'direto') {
            app(ImpressaoController::class)->enfileirar($encomenda, 'comprovante');
            app(ImpressaoController::class)->enfileirar($encomenda, 'etiqueta');
            return redirect()->route('encomendas.show', $encomenda)->with('ok', "Encomenda {$encomenda->numeroFormatado()} salva e enviada para impressão.");
        }

        return redirect()->route('encomendas.show', ['encomenda' => $encomenda, 'print' => $imprimir === 'navegador' ? 1 : 0])
            ->with('ok', "Encomenda {$encomenda->numeroFormatado()} salva.");
    }

    public function buscarPessoa(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if (strlen($q) < 2) return [];
        return Pessoa::where(fn ($w) => $w->where('nome', 'like', "%$q%")->orWhere('telefone', 'like', "%$q%"))
            ->orderBy('nome')->limit(8)->get(['nome', 'telefone', 'documento']);
    }

    // ------------------------------------------------------------------
    // DETALHE
    // ------------------------------------------------------------------
    public function show(Encomenda $encomenda)
    {
        $encomenda->load(['itens', 'pagamentos.user', 'embarcacao', 'operador', 'entreguePor', 'eventos.user']);
        return view('encomendas.show', ['e' => $encomenda, 'meios' => Pagamento::MEIOS]);
    }

    // ------------------------------------------------------------------
    // BAIXA DE PAGAMENTO
    // ------------------------------------------------------------------
    public function pagar(Request $request, Encomenda $encomenda)
    {
        abort_if($encomenda->cancelada, 422, 'Encomenda cancelada.');

        $data = $request->validate([
            'dinheiro' => 'nullable|numeric|min:0',
            'pix' => 'nullable|numeric|min:0',
            'debito' => 'nullable|numeric|min:0',
            'credito' => 'nullable|numeric|min:0',
            'desconto' => 'nullable|numeric|min:0',
            'entregar' => 'nullable|boolean',
            'entregue_para' => 'nullable|string|max:120',
            'uuid' => 'nullable|uuid',
        ]);

        if (! auth()->user()->podeParcial()) {
            $aplicado = (float) ($data['dinheiro'] ?? 0) + (float) ($data['pix'] ?? 0) + (float) ($data['debito'] ?? 0) + (float) ($data['credito'] ?? 0) + (float) ($data['desconto'] ?? 0);
            abort_if($aplicado > 0.009 && ($encomenda->devido - $aplicado) > 0.009, 422, 'Pagamento parcial não permitido. Receba o valor total.');
        }

        app(\App\Services\EncomendaService::class)->baixa($encomenda, $data, auth()->id(), $request->input('uuid'));

        return redirect()->route('encomendas.show', ['encomenda' => $encomenda, 'print' => 1])
            ->with('ok', 'Pagamento registrado.');
    }

    // ------------------------------------------------------------------
    // BAIXA DE ENTREGA
    // ------------------------------------------------------------------
    public function entregar(Request $request, Encomenda $encomenda)
    {
        abort_if($encomenda->cancelada, 422, 'Encomenda cancelada.');
        abort_if($encomenda->devido > 0.009 && $encomenda->forma !== 'faturado', 422, 'Registre o pagamento antes de dar baixa na entrega.');
        $data = $request->validate(['entregue_para' => 'nullable|string|max:120', 'uuid' => 'nullable|uuid']);

        app(\App\Services\EncomendaService::class)->baixa($encomenda, $data + ['entregar' => true], auth()->id(), $request->input('uuid'));

        return redirect()->route('encomendas.show', ['encomenda' => $encomenda, 'print' => 1])
            ->with('ok', 'Entrega registrada.');
    }

    public function cancelar(Request $request, Encomenda $encomenda)
    {
        $data = $request->validate(['motivo' => 'required|string|min:3|max:300'],
            ['motivo.required' => 'Informe o motivo do cancelamento.']);

        $encomenda->forceFill(['cancelada' => true, 'motivo_cancelamento' => $data['motivo']])->save();
        $encomenda->pagamentos()->update(['estornado' => true]);
        $encomenda->recalcular();
        Evento::registrar('cancelamento', $encomenda, ['motivo' => $data['motivo']]);
        return redirect()->route('encomendas.show', $encomenda)->with('ok', 'Encomenda cancelada.');
    }
}
