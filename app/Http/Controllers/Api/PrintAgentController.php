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

        // Trava: cada impressão é entregue a UM pedido só. Se o programa de impressão estiver aberto duas vezes
        // (ou em dois computadores com o mesmo token), o segundo não recebe a mesma impressão -> não sai em dobro.
        // Se o agente travar no meio, a impressão volta pra fila depois de 2 minutos.
        static $temTrava = null;
        $temTrava ??= \Illuminate\Support\Facades\Schema::hasColumn('fila_impressao', 'pego_em');
        if ($temTrava) {
            $limite = now()->subMinutes(2);
            $jobs = $jobs->filter(function ($j) use ($limite) {
                return FilaImpressao::where('id', $j->id)->where('status', 'pendente')
                    ->where(fn ($w) => $w->whereNull('pego_em')->orWhere('pego_em', '<', $limite))
                    ->update(['pego_em' => now()]) === 1;
            })->values();
        }

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
