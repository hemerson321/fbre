<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Estacao;
use App\Models\FilaImpressao;
use App\Support\Tenant;
use Illuminate\Http\Request;

/**
 * API consumida pelo FBRE Print Agent (programa no PC do cliente).
 * Autenticação: header X-Estacao-Token.
 */
class PrintAgentController extends Controller
{
    protected function estacao(Request $request): Estacao
    {
        $token = $request->header('X-Estacao-Token') ?: $request->query('token');
        $estacao = Estacao::todasEmpresas()->where('token', $token)->where('ativo', true)->first();
        abort_unless($estacao, 401, 'Token inválido');
        Tenant::set($estacao->empresa_id);
        $estacao->forceFill(['visto_em' => now()])->save();
        return $estacao;
    }

    public function ping(Request $request)
    {
        $e = $this->estacao($request);
        return ['ok' => true, 'estacao' => $e->nome, 'empresa' => $e->empresa->nomeExibicao(), 'colunas' => (int) ($e->colunas ?: 48)];
    }

    public function next(Request $request)
    {
        $e = $this->estacao($request);
        $jobs = FilaImpressao::where('estacao_id', $e->id)
            ->where('status', 'pendente')
            ->orderBy('id')
            ->limit(5)
            ->get(['id', 'tipo', 'dados', 'created_at']);

        return ['jobs' => $jobs];
    }

    public function done(Request $request, int $job)
    {
        $e = $this->estacao($request);
        FilaImpressao::where('estacao_id', $e->id)->where('id', $job)
            ->update(['status' => 'impresso', 'impresso_em' => now()]);
        return ['ok' => true];
    }

    public function error(Request $request, int $job)
    {
        $e = $this->estacao($request);
        FilaImpressao::where('estacao_id', $e->id)->where('id', $job)
            ->update(['status' => 'erro', 'erro' => substr((string) $request->input('erro'), 0, 250)]);
        return ['ok' => true];
    }
}
