<?php

namespace App\Services;

use App\Models\Camarote;
use App\Models\Passagem;
use App\Models\Rota;
use App\Support\Tenant;

/**
 * Regras da venda de camarote (preço por trecho, anti-sobreposição na viagem e
 * distribuição dos ocupantes em titular/incluído/adicional). Reutilizado pelo
 * balcão (POST) e pelo PIX dinâmico (cria no servidor após o pagamento).
 */
class CamaroteVenda
{
    /**
     * Monta o payload de Passagem (tipo=camarote) a partir dos dados crus da venda.
     * Lança \RuntimeException quando não há preço ou quando há conflito de ocupação.
     */
    public function montarPayload(array $data): array
    {
        $cam = Camarote::where('id', $data['camarote_id'] ?? 0)->first();
        if (! $cam || $cam->empresa_id !== Tenant::id()) {
            throw new \RuntimeException('Camarote não encontrado.');
        }
        $origem = (string) ($data['origem'] ?? '');
        $destino = (string) ($data['destino'] ?? '');
        if ($origem === '' || $destino === '' || $origem === $destino) {
            throw new \RuntimeException('Origem e destino inválidos.');
        }

        $base = $cam->precoPara($origem, $destino);
        if ($base === null) {
            throw new \RuntimeException('Não há preço cadastrado para esse trecho neste camarote.');
        }
        $cap = max((int) $cam->capacidade, 1);

        // não vender o mesmo camarote em trecho que se sobrepõe na mesma viagem
        $conf = $this->conflito($cam->id, $data['rota_id'] ?? null, $data['data_viagem'] ?? null, $data['viagem_id'] ?? null, $origem, $destino);
        if ($conf) {
            $num = $conf->numero_completo ?: $conf->numero;
            throw new \RuntimeException("Camarote {$cam->nome} já ocupado/reservado nessa viagem no trecho {$conf->origem} → {$conf->destino} (Nº {$num}".($conf->comprador_nome ? ', '.$conf->comprador_nome : '').').');
        }

        $ocupantes = array_values($data['ocupantes'] ?? []);
        if (! $ocupantes) throw new \RuntimeException('Informe ao menos um ocupante.');

        $passageiros = [];
        foreach ($ocupantes as $i => $oc) {
            if ($i === 0) { $papel = 'titular'; $valor = (float) $base; $obs = 'Camarote '.$cam->nome; }
            elseif ($i < $cap) { $papel = 'incluido'; $valor = 0.0; $obs = 'Incluído no camarote'; }
            else { $papel = 'adicional'; $valor = round((float) ($oc['adicional'] ?? $cam->valor_adicional), 2); $obs = 'Adicional'; }
            $passageiros[] = [
                'nome' => $oc['nome'] ?? 'Ocupante', 'tipo' => 'Camarote', 'papel' => $papel, 'percentual' => 100,
                'documento' => $oc['documento'] ?? null, 'nascimento' => $oc['nascimento'] ?? null,
                'telefone' => $oc['telefone'] ?? null, 'checkin' => (bool) ($oc['checkin'] ?? true),
                'valor_passagem' => $valor, 'valor_taxa' => 0, 'valor_alimentacao' => 0, 'valor_integral' => $valor,
                'observacao' => $obs,
            ];
        }

        return [
            'tipo' => 'camarote', 'camarote_id' => $cam->id,
            'origem' => $origem, 'destino' => $destino,
            'rota_id' => $data['rota_id'] ?? null, 'viagem_id' => $data['viagem_id'] ?? null,
            'data_viagem' => $data['data_viagem'] ?? null,
            'comprador_nome' => $data['comprador_nome'] ?? null,
            'comprador_telefone' => $data['comprador_telefone'] ?? null,
            'comprador_documento' => $data['comprador_documento'] ?? null,
            'forma' => $data['forma'] ?? 'avista', 'observacao' => $data['observacao'] ?? null,
            'desconto' => $data['desconto'] ?? 0,
            'passageiros' => $passageiros,
            'pagamentos' => $data['pagamentos'] ?? [],
        ];
    }

    /** Retorna a passagem em conflito (trecho sobreposto) no mesmo camarote/viagem, ou null. */
    public function conflito(int $camaroteId, $rotaId, $dataViagem, $viagemId, string $origem, string $destino): ?Passagem
    {
        if (! $viagemId && ! $dataViagem) return null;

        $rota = $rotaId ? Rota::find($rotaId) : null;
        if (! $rota) {
            $rota = Rota::with('paradas')->get()->first(function ($r) use ($origem, $destino) {
                $locs = array_map('mb_strtolower', $r->localidades());
                return in_array(mb_strtolower($origem), $locs, true) && in_array(mb_strtolower($destino), $locs, true);
            });
        }
        $ordem = $rota ? array_map('mb_strtolower', $rota->localidades()) : [];
        $idx = fn ($c) => array_search(mb_strtolower((string) $c), $ordem, true);
        $reqS = $idx($origem); $reqD = $idx($destino);
        $temTrecho = $ordem && $reqS !== false && $reqD !== false;
        $reqMin = $temTrecho ? min($reqS, $reqD) : null;
        $reqMax = $temTrecho ? max($reqS, $reqD) : null;

        $q = Passagem::where('tipo', 'camarote')->where('camarote_id', $camaroteId)->where('cancelada', false)
            ->when($viagemId, fn ($q) => $q->where('viagem_id', $viagemId))
            ->when(! $viagemId && $dataViagem, fn ($q) => $q->whereDate('data_viagem', $dataViagem));

        foreach ($q->get(['id', 'numero', 'numero_completo', 'origem', 'destino', 'comprador_nome']) as $pg) {
            if ($temTrecho) {
                $c = $idx($pg->origem); $d = $idx($pg->destino);
                if ($c !== false && $d !== false) {
                    [$cMin, $cMax] = [min($c, $d), max($c, $d)];
                    if (! ($reqMin < $cMax && $cMin < $reqMax)) continue; // não cruza -> livre
                }
            }
            return $pg; // conflito
        }
        return null;
    }
}
