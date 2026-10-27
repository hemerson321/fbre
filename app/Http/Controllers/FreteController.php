<?php

namespace App\Http\Controllers;

use App\Models\Embarcacao;
use App\Models\Frete;
use App\Models\Pagamento;
use App\Models\Pessoa;
use App\Models\Rota;
use App\Support\Tenant;
use Illuminate\Http\Request;

class FreteController extends Controller
{
    public function index(Request $request)
    {
        $f = $this->filtros($request);
        $base = Frete::filtrar($f);
        $totais = [
            'qtd' => (clone $base)->count(),
            'peso' => (clone $base)->sum('peso'),
            'volumes' => (clone $base)->sum('volumes'),
            'total' => (clone $base)->sum('total'),
            'recebido' => (clone $base)->sum('pago'),
            'a_receber' => (clone $base)->sum('devido'),
        ];
        $ids = (clone $base)->select('id');
        $porMeio = Pagamento::where('modulo', 'frete')->whereIn('origem_id', $ids)
            ->where('meio', '!=', 'desconto')->selectRaw('meio, SUM(valor) as valor')->groupBy('meio')->pluck('valor', 'meio');
        $fretes = (clone $base)->with(['embarcacao', 'operador'])->orderByDesc('id')->paginate(50)->withQueryString();
        return view('fretes.index', [
            'fretes' => $fretes, 'totais' => $totais, 'porMeio' => $porMeio, 'f' => $f,
            'localidades' => Rota::todasLocalidades(),
            'embarcacoes' => Embarcacao::where('ativo', true)->orderBy('nome')->get(),
        ]);
    }

    protected function filtros(Request $request): array
    {
        $f = $request->only(['inicio', 'fim', 'hora_inicio', 'hora_fim', 'origem', 'destino', 'embarcacao_id', 'viagem_id', 'status_pagamento', 'entregue', 'forma', 'busca', 'user_id']);
        if (! $request->has('inicio') && ! $request->filled('busca')) {
            $f['inicio'] = now()->subDays(7)->toDateString();
            $f['fim'] = now()->toDateString();
        }
        return $f;
    }

    /**
     * Consulta uma NF-e pela chave (44 díg.) e devolve remetente/destinatário/itens
     * para preencher o frete. Usa a API da meudanfe quando o token estiver configurado.
     */
    public function consultarNf(Request $request)
    {
        $chave = preg_replace('/\D/', '', (string) $request->query('chave', ''));
        if (strlen($chave) !== 44) {
            return response()->json(['ok' => false, 'erro' => 'Chave da NF-e inválida (precisa de 44 dígitos).']);
        }

        $token = config('services.meudanfe.token') ?: (Tenant::empresa()?->config('meudanfe_token'));
        if (! $token) {
            return response()->json(['ok' => false, 'erro' => 'Consulta de NF não configurada. Cole a Api-Key da meudanfe em Administração → Minha empresa.']);
        }

        try {
            $base = 'https://api.meudanfe.com.br/v2';
            $http = \Illuminate\Support\Facades\Http::timeout(30)->withHeaders(['Api-Key' => $token])->acceptJson();

            // 1) busca e adiciona a NF na Área do Cliente (cobra R$0,03; se já existe, é grátis)
            $http->put($base.'/fd/add/'.$chave);
            // 2) baixa o XML (grátis depois de adicionada)
            $xmlResp = $http->get($base.'/fd/get/xml/'.$chave);
            if (! $xmlResp->ok()) {
                return response()->json(['ok' => false, 'erro' => 'NF não encontrada ou indisponível na SEFAZ ('.$xmlResp->status().').']);
            }

            $xml = $this->extrairXml($xmlResp->body());
            if (! $xml) {
                return response()->json(['ok' => false, 'erro' => 'A NF foi consultada, mas não consegui ler o XML. Preencha manualmente.']);
            }

            $nf = $this->parseNfe($xml);
            $nf['chave'] = $chave;
            return response()->json(['ok' => true, 'nf' => $nf]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'erro' => 'Falha ao consultar a NF (verifique a Api-Key e a liberação de rede do servidor).']);
        }
    }

    /** Tenta extrair o XML da NF-e da resposta (XML puro, base64 ou JSON com o XML dentro). */
    private function extrairXml(string $body): ?string
    {
        $body = trim($body);
        if (str_contains($body, '<nfeProc') || str_contains($body, '<NFe')) return $body;
        // JSON embrulhando o XML (campos comuns)
        $j = json_decode($body, true);
        if (is_array($j)) {
            foreach (['xml', 'XML', 'data', 'content', 'conteudo', 'danfe', 'base64'] as $k) {
                $v = data_get($j, $k);
                if (is_string($v) && $v !== '') {
                    if (str_contains($v, '<nfeProc') || str_contains($v, '<NFe')) return $v;
                    $dec = base64_decode($v, true);
                    if ($dec && (str_contains($dec, '<nfeProc') || str_contains($dec, '<NFe'))) return $dec;
                }
            }
        }
        // o próprio corpo em base64
        $dec = base64_decode($body, true);
        if ($dec && (str_contains($dec, '<nfeProc') || str_contains($dec, '<NFe'))) return $dec;
        return null;
    }

    /** Lê emitente (remetente), destinatário, número e itens de um XML de NF-e. */
    private function parseNfe(string $xml): array
    {
        $flat = preg_replace('/xmlns(:\w+)?="[^"]*"/', '', $xml); // tira namespaces pra acessar direto
        $sx = @simplexml_load_string($flat);
        $inf = $sx->NFe->infNFe ?? ($sx->infNFe ?? $sx);

        $doc = fn ($no) => (string) ($no->CNPJ ?? $no->CPF ?? '');
        $itens = [];
        foreach ($inf->det ?? [] as $det) {
            $p = $det->prod ?? null;
            if (! $p) continue;
            $itens[] = [
                'descricao'      => trim((string) $p->xProd),
                'quantidade'     => (float) ($p->qCom ?? 1) ?: 1,
                'valor_unitario' => round((float) ($p->vUnCom ?? 0), 2),
            ];
        }

        return [
            'numero'       => (string) ($inf->ide->nNF ?? ''),
            'remetente'    => ['nome' => (string) ($inf->emit->xNome ?? ''), 'documento' => $doc($inf->emit ?? new \stdClass)],
            'destinatario' => ['nome' => (string) ($inf->dest->xNome ?? ''), 'documento' => $doc($inf->dest ?? new \stdClass)],
            'itens'        => $itens,
            'descricao'    => collect($itens)->pluck('descricao')->filter()->take(6)->implode(', '),
        ];
    }

    public function create()
    {
        $u = auth()->user();
        $localidades = Rota::todasLocalidades();
        $cidadesPerm = $u->cidadesPermitidas();
        if ($cidadesPerm) $localidades = $localidades->intersect($cidadesPerm)->values();
        $rotas = Tenant::empresa()->temRecurso('viagens') ? Rota::paraSelecao() : collect();
        $linhas = $rotas->map(fn ($r) => [
            'id' => $r->id, 'nome' => $r->nome,
            'paradas' => $r->paradas->map(fn ($p) => ['n' => $p->localidade, 'd' => $p->dia_semana, 'h' => $p->hora ? substr($p->hora, 0, 5) : null])->values(),
        ])->values();
        $catalogo = \App\Models\Produto::where('ativo', true)->where('modulo', 'frete')->orderByDesc('usos')->orderBy('nome')
            ->get(['id', 'nome', 'valor_padrao'])
            ->map(fn ($p) => ['id' => $p->id, 'nome' => $p->nome, 'valor' => (float) $p->valor_padrao])->values();

        return view('fretes.create', [
            'embarcacoes' => Embarcacao::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'cidades' => $localidades->values(),
            'linhas' => $linhas,
            'catalogo' => $catalogo,
            'ultimaOrigem' => session('ultima_origem_frete') ?: (Tenant::empresa()->cidade ?: $localidades->first()),
            'embarcacaoPrincipal' => session('ultima_embarcacao_frete') ?: $u->embarcacao_principal_id,
            'precoKiloPadrao' => (float) (Tenant::empresa()->config['preco_kilo'] ?? 0),
            'podeRetroativo' => $u->podeRetroativo(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $f = app(\App\Services\FreteService::class)->criar($data, auth()->id(), $request->input('uuid'));
        session(['ultima_origem_frete' => $data['origem'], 'ultima_embarcacao_frete' => $data['embarcacao_id'] ?? null]);
        $imprimir = $request->input('imprimir', 'navegador');
        return redirect()->route('fretes.show', ['frete' => $f, 'print' => $imprimir === 'navegador' ? 1 : 0])->with('ok', "Frete {$f->numeroFormatado()} salvo.");
    }

    protected function validar(Request $request): array
    {
        return $request->validate([
            'uuid' => 'nullable|uuid', 'numero_completo' => 'nullable|string|max:30', 'numero_seq' => 'nullable|integer|min:1', 'criada_em' => 'nullable|date',
            'origem' => 'required|string|max:80', 'destino' => 'required|string|max:80',
            'embarcacao_id' => 'nullable|integer', 'rota_id' => 'nullable|integer', 'viagem_id' => 'nullable|integer', 'data_viagem' => 'nullable|date',
            'remetente_nome' => 'required|string|max:120', 'remetente_telefone' => 'nullable|string|max:30', 'remetente_documento' => 'nullable|string|max:20',
            'destinatario_nome' => 'required|string|max:120', 'destinatario_telefone' => 'nullable|string|max:30', 'destinatario_documento' => 'nullable|string|max:20',
            'descricao' => 'required|string|max:200', 'peso' => 'nullable|numeric|min:0', 'volumes' => 'required|integer|min:1|max:9999', 'preco_kilo' => 'nullable|numeric|min:0',
            'subtotal' => 'required|numeric|min:0', 'forma' => 'required|in:avista,areceber,faturado', 'setor' => 'nullable|string|max:60', 'observacao' => 'nullable|string|max:1000', 'desconto' => 'nullable|numeric|min:0',
            'pagamentos' => 'nullable|array', 'pagamentos.*.meio' => 'required_with:pagamentos|in:dinheiro,pix,debito,credito', 'pagamentos.*.valor' => 'required_with:pagamentos|numeric|min:0',
            'imprimir' => 'nullable|in:navegador,direto,nao',
        ]);
    }

    public function show(Frete $frete)
    {
        $frete->load(['pagamentos.user', 'embarcacao', 'operador', 'entreguePor']);
        return view('fretes.show', ['f' => $frete, 'meios' => Pagamento::MEIOS]);
    }

    public function pagar(Request $request, Frete $frete)
    {
        abort_if($frete->cancelada, 422, 'Frete cancelado.');
        $data = $request->validate(['dinheiro' => 'nullable|numeric|min:0', 'pix' => 'nullable|numeric|min:0', 'debito' => 'nullable|numeric|min:0', 'credito' => 'nullable|numeric|min:0', 'desconto' => 'nullable|numeric|min:0', 'entregar' => 'nullable|boolean', 'entregue_para' => 'nullable|string|max:120']);
        app(\App\Services\FreteService::class)->baixa($frete, $data, auth()->id());
        return redirect()->route('fretes.show', ['frete' => $frete, 'print' => 1])->with('ok', 'Pagamento registrado.');
    }

    public function entregar(Request $request, Frete $frete)
    {
        abort_if($frete->cancelada, 422, 'Frete cancelado.');
        abort_if($frete->devido > 0.009 && $frete->forma !== 'faturado', 422, 'Registre o pagamento antes da entrega.');
        $data = $request->validate(['entregue_para' => 'nullable|string|max:120']);
        app(\App\Services\FreteService::class)->baixa($frete, $data + ['entregar' => true], auth()->id());
        return redirect()->route('fretes.show', $frete)->with('ok', 'Entrega registrada.');
    }

    public function cancelar(Request $request, Frete $frete)
    {
        $data = $request->validate(['motivo' => 'required|string|min:3|max:300'], ['motivo.required' => 'Informe o motivo.']);
        $frete->forceFill(['cancelada' => true, 'motivo_cancelamento' => $data['motivo']])->save();
        $frete->pagamentos()->update(['estornado' => true]);
        $frete->recalcular();
        return redirect()->route('fretes.show', $frete)->with('ok', 'Frete cancelado.');
    }
}
