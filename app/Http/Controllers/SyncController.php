<?php

namespace App\Http\Controllers;

use App\Models\Embarcacao;
use App\Models\Encomenda;
use App\Models\Localidade;
use App\Models\Produto;
use App\Models\Rota;
use App\Models\Setor;
use App\Services\EncomendaService;
use App\Support\Tenant;
use Illuminate\Http\Request;

/**
 * Sincronização do modo offline (chamado pelo JS do navegador, com sessão + CSRF).
 */
class SyncController extends Controller
{
    /** Dados que o navegador guarda para funcionar sem internet. */
    public function bootstrap()
    {
        $e = Tenant::empresa();
        $u = auth()->user();
        return [
            'empresa' => [
                'nome' => $e->nomeExibicao(), 'cnpj' => $e->cnpj, 'telefone' => $e->telefone, 'whatsapp' => $e->whatsapp,
                'pix' => $e->pix, 'rodape' => $e->rodape_comprovante, 'logo' => $e->logo ? '/'.$e->logo : null,
                'cor1' => $e->cor_primaria, 'cor2' => $e->cor_secundaria, 'cidade' => $e->cidade,
            ],
            'usuario' => ['id' => $u->id, 'nome' => $u->name, 'setor' => $u->setor,
                'cadastra' => $u->podeCadastrar(), 'baixa' => $u->podeBaixar()],
            'produtos' => Produto::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'valor_padrao']),
            'localidades' => Rota::todasLocalidades(),
            'embarcacoes' => Embarcacao::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'rotas' => $e->temRecurso('viagens') ? Rota::where('ativo', true)->with('paradas:id,rota_id,localidade,dia_semana,hora,ordem')->orderBy('nome')->get(['id', 'nome', 'embarcacao_id']) : [],
            'setores' => Setor::orderBy('nome')->pluck('nome'),
            'recentes' => Encomenda::orderByDesc('id')->limit(200)->get([
                'id', 'uuid', 'numero', 'origem', 'destino', 'remetente_nome', 'remetente_telefone', 'destinatario_nome', 'destinatario_telefone',
                'volumes', 'total', 'pago', 'devido', 'status_pagamento', 'entregue', 'created_at',
            ]),
            'csrf' => csrf_token(),
            'em' => now()->toIso8601String(),
        ];
    }

    /** Recebe uma encomenda criada no aparelho. Idempotente por uuid. */
    public function encomenda(Request $request, EncomendaService $svc)
    {
        abort_unless(auth()->user()->podeCadastrar(), 403);

        $data = $request->validate([
            'uuid' => 'required|uuid',
            'numero_completo' => 'nullable|string|max:30',
            'numero_seq' => 'nullable|integer|min:1',
            'numero_provisorio' => 'nullable|string|max:30',
            'criada_em' => 'nullable|date',
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
            'desconto' => 'nullable|numeric|min:0',
            'itens' => 'required|array|min:1',
            'itens.*.descricao' => 'required|string|max:120',
            'itens.*.quantidade' => 'required|numeric|min:0.01',
            'itens.*.valor_unitario' => 'required|numeric|min:0',
            'itens.*.observacao' => 'nullable|string|max:200',
            'pagamentos' => 'nullable|array',
            'pagamentos.*.meio' => 'required_with:pagamentos|in:dinheiro,pix,debito,credito',
            'pagamentos.*.valor' => 'required_with:pagamentos|numeric|min:0',
            'pix_cobranca_id' => 'nullable|integer',
        ]);

        // retroativo só para quem tem permissão
        if (! auth()->user()->podeRetroativo()) {
            unset($data['lancamento_em']);
            if (! empty($data['viagem_data']) && $data['viagem_data'] < now()->toDateString()) unset($data['viagem_data']);
        }

        // pagamento parcial só para quem tem permissão
        if (! auth()->user()->podeParcial()) {
            $total = collect($data['itens'])->sum(fn ($i) => (float) $i['quantidade'] * (float) $i['valor_unitario']) - (float) ($data['desconto'] ?? 0);
            $pago = collect($data['pagamentos'] ?? [])->sum(fn ($p) => (float) $p['valor']);
            if ($pago > 0.009 && $pago < $total - 0.009) {
                return response()->json(['ok' => false, 'erro' => 'Pagamento parcial não permitido. Receba o total ou deixe a receber.'], 422);
            }
        }

        try {
            $enc = $svc->criar($data, auth()->id(), $data['uuid'], (bool) $request->input('offline', false));
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'erro' => class_basename($e).': '.mb_substr($e->getMessage(), 0, 300)], 500);
        }

        // liga a cobrança PIX dinâmica (gerada antes, já paga) a esta encomenda
        if (! empty($data['pix_cobranca_id'])) {
            \App\Models\Cobranca::where('id', $data['pix_cobranca_id'])->where('empresa_id', \App\Support\Tenant::id())
                ->whereNull('referencia_id')
                ->update(['referencia_tipo' => \App\Models\Encomenda::class, 'referencia_id' => $enc->id, 'modulo' => 'encomenda']);
        }

        // pré-cadastro atendido → finalizado
        if (! empty($data['pre_id'])) {
            \App\Models\PreCadastro::where('status', 'pendente')->where('id', $data['pre_id'])
                ->update(['status' => 'finalizado', 'encomenda_id' => $enc->id]);
        }

        // lembra os últimos escolhidos p/ já virem preenchidos no próximo cadastro
        session(['ultima_origem' => $data['origem'] ?? null, 'ultima_embarcacao' => $data['embarcacao_id'] ?? null]);

        return ['ok' => true, 'id' => $enc->id, 'numero' => $enc->numero, 'numero_formatado' => $enc->numeroFormatado(),
            'url' => route('encomendas.show', $enc)];
    }

    /** Recebe uma passagem criada no aparelho. Idempotente por uuid. */
    public function passagem(Request $request, \App\Services\PassagemService $svc)
    {
        abort_unless(auth()->user()->podeModulo('passagem'), 403);
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'numero_completo' => 'nullable|string|max:30',
            'numero_seq' => 'nullable|integer|min:1',
            'criada_em' => 'nullable|date',
            'origem' => 'required|string|max:80',
            'destino' => 'required|string|max:80',
            'embarcacao_id' => 'nullable|integer',
            'rota_id' => 'nullable|integer',
            'viagem_id' => 'nullable|integer',
            'data_viagem' => 'nullable|date',
            'comprador_nome' => 'nullable|string|max:120',
            'comprador_telefone' => 'nullable|string|max:30',
            'comprador_documento' => 'nullable|string|max:20',
            'forma' => 'required|in:avista,areceber,faturado',
            'observacao' => 'nullable|string|max:1000',
            'desconto' => 'nullable|numeric|min:0',
            'passageiros' => 'required|array|min:1',
            'passageiros.*.nome' => 'required|string|max:120',
            'passageiros.*.tipo' => 'required|string|max:60',
            'passageiros.*.percentual' => 'nullable|integer|min:0|max:100',
            'passageiros.*.documento' => 'nullable|string|max:20',
            'passageiros.*.nascimento' => 'nullable|date',
            'passageiros.*.poltrona' => 'nullable|string|max:10',
            'passageiros.*.valor_passagem' => 'required|numeric|min:0',
            'passageiros.*.valor_taxa' => 'nullable|numeric|min:0',
            'passageiros.*.observacao' => 'nullable|string|max:200',
            'pagamentos' => 'nullable|array',
            'pagamentos.*.meio' => 'required_with:pagamentos|in:dinheiro,pix,debito,credito',
            'pagamentos.*.valor' => 'required_with:pagamentos|numeric|min:0',
            'pix_cobranca_id' => 'nullable|integer',
        ]);
        try {
            $p = $svc->criar($data, auth()->id(), $data['uuid'], (bool) $request->input('offline', false));
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'erro' => class_basename($e).': '.mb_substr($e->getMessage(), 0, 300)], 500);
        }
        if (! empty($data['pix_cobranca_id'])) {
            \App\Models\Cobranca::where('id', $data['pix_cobranca_id'])->where('empresa_id', \App\Support\Tenant::id())
                ->whereNull('referencia_id')
                ->update(['referencia_tipo' => \App\Models\Passagem::class, 'referencia_id' => $p->id, 'modulo' => 'passagem']);
        }
        session(['ultima_origem_pass' => $data['origem'] ?? null]);
        return ['ok' => true, 'id' => $p->id, 'numero_formatado' => $p->numeroFormatado(), 'url' => route('passagens.show', $p)];
    }

    /** Recebe um frete criado no aparelho. Idempotente por uuid. */
    public function frete(Request $request, \App\Services\FreteService $svc)
    {
        abort_unless(auth()->user()->podeModulo('frete'), 403);
        $data = $request->validate([
            'uuid' => 'required|uuid', 'numero_completo' => 'nullable|string|max:30', 'numero_seq' => 'nullable|integer|min:1', 'criada_em' => 'nullable|date',
            'origem' => 'required|string|max:80', 'destino' => 'required|string|max:80',
            'embarcacao_id' => 'nullable|integer', 'rota_id' => 'nullable|integer', 'viagem_id' => 'nullable|integer', 'data_viagem' => 'nullable|date',
            'remetente_nome' => 'required|string|max:120', 'remetente_telefone' => 'nullable|string|max:30', 'remetente_documento' => 'nullable|string|max:20',
            'destinatario_nome' => 'required|string|max:120', 'destinatario_telefone' => 'nullable|string|max:30', 'destinatario_documento' => 'nullable|string|max:20',
            'descricao' => 'required|string|max:200', 'peso' => 'nullable|numeric|min:0', 'volumes' => 'required|integer|min:1|max:9999', 'preco_kilo' => 'nullable|numeric|min:0',
            'subtotal' => 'required|numeric|min:0', 'forma' => 'required|in:avista,areceber,faturado', 'setor' => 'nullable|string|max:60', 'observacao' => 'nullable|string|max:1000', 'desconto' => 'nullable|numeric|min:0',
            'quem_paga' => 'nullable|in:destinatario,remetente', 'nome_responsavel' => 'nullable|string|max:120',
            'nf_chave' => 'nullable|string|max:60', 'nf_numero' => 'nullable|string|max:30', 'nf_dados' => 'nullable|array',
            'veiculos' => 'nullable|array', 'veiculos.*.placa' => 'nullable|string|max:12', 'veiculos.*.modelo' => 'nullable|string|max:60',
            'veiculos.*.cor' => 'nullable|string|max:30', 'veiculos.*.documento' => 'nullable|string|max:40', 'veiculos.*.obs' => 'nullable|string|max:200',
            'itens' => 'nullable|array', 'itens.*.descricao' => 'required_with:itens|string|max:120', 'itens.*.quantidade' => 'nullable|numeric|min:0.01',
            'itens.*.valor_unitario' => 'nullable|numeric|min:0', 'itens.*.observacao' => 'nullable|string|max:200',
            'pagamentos' => 'nullable|array', 'pagamentos.*.meio' => 'required_with:pagamentos|in:dinheiro,pix,debito,credito', 'pagamentos.*.valor' => 'required_with:pagamentos|numeric|min:0',
        ]);
        try {
            $f = $svc->criar($data, auth()->id(), $data['uuid'], (bool) $request->input('offline', false));
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'erro' => class_basename($e).': '.mb_substr($e->getMessage(), 0, 300)], 500);
        }
        session(['ultima_origem_frete' => $data['origem'] ?? null]);
        return ['ok' => true, 'id' => $f->id, 'numero_formatado' => $f->numeroFormatado(), 'url' => route('fretes.show', $f)];
    }

    /** Baixa feita offline (pagamento e/ou entrega). */
    public function baixa(Request $request, EncomendaService $svc)
    {
        abort_unless(auth()->user()->podeBaixar(), 403);
        $data = $request->validate([
            'uuid' => 'required|uuid',                  // uuid do evento de baixa
            'encomenda_uuid' => 'nullable|uuid',
            'encomenda_id' => 'nullable|integer',
            'dinheiro' => 'nullable|numeric|min:0', 'pix' => 'nullable|numeric|min:0',
            'debito' => 'nullable|numeric|min:0', 'credito' => 'nullable|numeric|min:0', 'desconto' => 'nullable|numeric|min:0',
            'entregar' => 'nullable|boolean', 'entregue_para' => 'nullable|string|max:120', 'entregue_documento' => 'nullable|string|max:20', 'entregue_em' => 'nullable|date',
        ]);

        $enc = $data['encomenda_uuid'] ? Encomenda::where('uuid', $data['encomenda_uuid'])->first() : Encomenda::find($data['encomenda_id'] ?? 0);
        if (! $enc) {
            return response()->json(['ok' => false, 'erro' => 'encomenda_nao_encontrada', 'reter' => true], 409);
        }
        if (! auth()->user()->podeParcial()) {
            $aplicado = (float) ($data['dinheiro'] ?? 0) + (float) ($data['pix'] ?? 0) + (float) ($data['debito'] ?? 0) + (float) ($data['credito'] ?? 0) + (float) ($data['desconto'] ?? 0);
            if ($aplicado > 0.009 && ($enc->devido - $aplicado) > 0.009) {
                return response()->json(['ok' => false, 'erro' => 'Pagamento parcial não permitido. Receba o valor total.', 'reter' => true], 422);
            }
        }
        try {
            $svc->baixa($enc, $data, auth()->id(), $data['uuid']);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'erro' => class_basename($e).': '.mb_substr($e->getMessage(), 0, 300)], 500);
        }
        return ['ok' => true, 'id' => $enc->id, 'numero_formatado' => $enc->numeroFormatado()];
    }
}
