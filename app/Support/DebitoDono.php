<?php

namespace App\Support;

use App\Models\Pagamento;
use Illuminate\Support\Collection;

/** Débito do proprietário por passagem JÁ ACERTADA que foi cancelada ou devolvida — desconta na próxima prestação. */
class DebitoDono
{
    /** Nome exato do desconto conforme os itens: "Devolução", "Cancelamento" ou os dois. */
    public static function rotulo(Collection $itens): string
    {
        $tipos = $itens->pluck('tipo')->filter(fn ($t) => $t !== 'Comissão')->unique()->values();
        if ($tipos->count() === 1) return $tipos[0];
        return $tipos->count() > 1 ? 'Cancelamento e devolução' : 'Devolução';
    }

    /** Quanto o proprietário ainda deve (lançado − já abatido). */
    public static function pendente(int $embarcacaoId): float
    {
        $v = (float) Pagamento::where('embarcacao_id', $embarcacaoId)->where('estornado', false)->where('categoria', 'Débito do dono (devolução)')->sum('valor')
           - (float) Pagamento::where('embarcacao_id', $embarcacaoId)->where('estornado', false)->where('categoria', 'Débito do dono — abatido')->sum('valor');
        return max(0.0, round($v, 2));
    }

    /**
     * Os lançamentos que compõem o que ainda está pendente (do mais novo pro mais antigo, até fechar o valor).
     * @return Collection<int, object{tipo:string, texto:string, data:?string, valor:float}>
     */
    public static function itens(int $embarcacaoId): Collection
    {
        $falta = self::pendente($embarcacaoId);
        $out = collect();
        if ($falta <= 0.009) return $out;
        $lancs = Pagamento::where('embarcacao_id', $embarcacaoId)->where('estornado', false)->where('categoria', 'Débito do dono (devolução)')
            ->orderByDesc('id')->limit(60)->get(['id', 'valor', 'descricao', 'created_at']);
        foreach ($lancs as $l) {
            if ($falta <= 0.009) break;
            $desc = (string) $l->descricao;
            $low = mb_strtolower($desc);
            $tipo = str_contains($low, 'cancelamento') ? 'Cancelamento' : (str_contains($low, 'recebeu direto') ? 'Comissão' : 'Devolução');
            $bil = preg_match('/passagem\s+([A-Z0-9\-]+)/iu', $desc, $m) ? $m[1] : null;
            $nome = preg_match('/·\s*(.+)$/u', $desc, $m2) ? trim($m2[1]) : null;
            // confere na própria passagem se foi CANCELAMENTO ou DEVOLUÇÃO (lançamentos antigos não guardavam o tipo nem o nome)
            if ($bil && $tipo !== 'Comissão') {
                $pas = \App\Models\Passagem::where('embarcacao_id', $embarcacaoId)->where('numero_completo', $bil)->with('passageiros:id,passagem_id,nome')->first();
                if ($pas) {
                    $tipo = ($pas->cancel_tipo ?? '') === 'cancelamento' ? 'Cancelamento' : 'Devolução';
                    if (! $nome) $nome = (string) (optional($pas->passageiros->first())->nome ?: $pas->comprador_nome ?: '') ?: null;
                }
            }
            $out->push((object) [
                'tipo' => $tipo,
                'texto' => $tipo === 'Comissão' ? 'Comissão sobre valor que o proprietário recebeu direto' : trim(($bil ? 'bilhete '.$bil : 'passagem').($nome ? ' · '.$nome : '')),
                'data' => optional($l->created_at)->format('d/m/Y'),
                'valor' => round(min((float) $l->valor, $falta), 2),
            ]);
            $falta = round($falta - (float) $l->valor, 2);
        }
        return $out;
    }
}
