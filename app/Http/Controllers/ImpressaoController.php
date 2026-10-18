<?php

namespace App\Http\Controllers;

use App\Models\Encomenda;
use App\Models\Estacao;
use App\Models\Evento;
use App\Models\FilaImpressao;
use App\Support\Tenant;
use Illuminate\Http\Request;

class ImpressaoController extends Controller
{
    protected function template(string $tipo): string
    {
        $empresa = Tenant::empresa();
        $nome = $tipo === 'comprovante' ? $empresa->template_comprovante : $empresa->template_etiqueta;
        $view = "impressao.$tipo.$nome";
        return view()->exists($view) ? $view : "impressao.$tipo.padrao";
    }

    public function comprovante(Encomenda $encomenda, Request $request)
    {
        $encomenda->load(['itens', 'pagamentos', 'embarcacao', 'operador', 'entreguePor']);
        if (! $request->boolean('silencioso')) {
            Evento::registrar('reimpressao', $encomenda, ['tipo' => 'comprovante']);
        }
        return view($this->template('comprovante'), [
            'e' => $encomenda,
            'empresa' => Tenant::empresa(),
            'via' => $request->query('via', 'cliente'),
        ]);
    }

    public function etiqueta(Encomenda $encomenda)
    {
        $encomenda->load(['embarcacao']);
        return view($this->template('etiqueta'), [
            'e' => $encomenda,
            'empresa' => Tenant::empresa(),
        ]);
    }

    public function protocolo(Request $request)
    {
        $f = $request->only(['inicio', 'fim', 'origem', 'destino', 'embarcacao_id', 'status_pagamento', 'entregue', 'forma', 'busca']);
        $encomendas = Encomenda::filtrar($f)->with('embarcacao')->orderBy('numero')->get();
        return view('impressao.protocolo', ['encomendas' => $encomendas, 'empresa' => Tenant::empresa(), 'f' => $f]);
    }

    public function listagem(Request $request)
    {
        $f = $request->only(['inicio', 'fim', 'origem', 'destino', 'embarcacao_id', 'status_pagamento', 'entregue', 'forma', 'busca']);
        $encomendas = Encomenda::filtrar($f)->with('embarcacao')->orderBy('numero')->get();
        return view('impressao.listagem', ['encomendas' => $encomendas, 'empresa' => Tenant::empresa(), 'f' => $f]);
    }

    /** Envia para a fila do FBRE Print Agent. */
    public function direto(Encomenda $encomenda, string $tipo, Request $request)
    {
        abort_unless(in_array($tipo, ['comprovante', 'etiqueta']), 404);
        $job = $this->enfileirar($encomenda, $tipo, $request->input('estacao_id'));
        if (! $job) {
            return response()->json(['ok' => false, 'erro' => 'Nenhuma estação de impressão ativa.'], 422);
        }
        return ['ok' => true, 'job' => $job->id];
    }

    public function enfileirar(Encomenda $encomenda, string $tipo, ?int $estacaoId = null): ?FilaImpressao
    {
        $estacao = $estacaoId
            ? Estacao::where('ativo', true)->find($estacaoId)
            : Estacao::where('ativo', true)->orderByDesc('visto_em')->first();

        if (! $estacao) return null;

        $encomenda->loadMissing(['itens', 'pagamentos', 'embarcacao', 'operador']);
        $empresa = Tenant::empresa();

        $dados = [
            'empresa' => [
                'nome' => $empresa->nomeExibicao(),
                'cnpj' => $empresa->cnpj,
                'telefone' => $empresa->telefone,
                'whatsapp' => $empresa->whatsapp,
                'pix' => $empresa->pix,
                'rodape' => $empresa->rodape_comprovante,
            ],
            'encomenda' => [
                'numero' => $encomenda->numeroFormatado(),
                'data' => $encomenda->created_at->format('d/m/Y H:i'),
                'origem' => $encomenda->origem,
                'destino' => $encomenda->destino,
                'embarcacao' => $encomenda->embarcacao?->nome,
                'remetente' => $encomenda->remetente_nome,
                'remetente_tel' => $encomenda->remetente_telefone,
                'destinatario' => $encomenda->destinatario_nome,
                'destinatario_tel' => $encomenda->destinatario_telefone,
                'volumes' => $encomenda->volumes,
                'itens' => $encomenda->itens->map(fn ($i) => [
                    'descricao' => $i->descricao, 'qtd' => (float) $i->quantidade,
                    'unit' => (float) $i->valor_unitario, 'total' => (float) $i->valor_total,
                ])->all(),
                'subtotal' => (float) $encomenda->subtotal,
                'desconto' => (float) $encomenda->desconto,
                'total' => (float) $encomenda->total,
                'pago' => (float) $encomenda->pago,
                'devido' => (float) $encomenda->devido,
                'forma' => Encomenda::FORMAS[$encomenda->forma],
                'status' => $encomenda->statusTexto(),
                'operador' => $encomenda->operador?->name,
                'observacao' => $encomenda->observacao,
            ],
        ];

        return FilaImpressao::create([
            'estacao_id' => $estacao->id,
            'encomenda_id' => $encomenda->id,
            'tipo' => $tipo,
            'dados' => $dados,
        ]);
    }
}
