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
            'numero_provisorio' => 'nullable|string|max:30',
            'criada_em' => 'nullable|date',
            'origem' => 'required|string|max:80',
            'destino' => 'required|string|max:80',
            'embarcacao_id' => 'nullable|integer',
            'rota_id' => 'nullable|integer',
            'viagem_id' => 'nullable|integer',
            'viagem_data' => 'nullable|date',
            'lancamento_em' => 'nullable|date',
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
        ]);

        try {
            $enc = $svc->criar($data, auth()->id(), $data['uuid'], (bool) $request->input('offline', false));
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'erro' => class_basename($e).': '.mb_substr($e->getMessage(), 0, 300)], 500);
        }

        return ['ok' => true, 'id' => $enc->id, 'numero' => $enc->numero, 'numero_formatado' => $enc->numeroFormatado(),
            'url' => route('encomendas.show', $enc)];
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
            'entregar' => 'nullable|boolean', 'entregue_para' => 'nullable|string|max:120', 'entregue_em' => 'nullable|date',
        ]);

        $enc = $data['encomenda_uuid'] ? Encomenda::where('uuid', $data['encomenda_uuid'])->first() : Encomenda::find($data['encomenda_id'] ?? 0);
        if (! $enc) {
            return response()->json(['ok' => false, 'erro' => 'encomenda_nao_encontrada', 'reter' => true], 409);
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
