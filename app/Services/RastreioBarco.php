<?php

namespace App\Services;

use App\Models\Embarcacao;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Lê a posição de um barco pela URL de rastreio cadastrada na embarcação
 * (ex.: rionave riotag). Reutilizado pela tela da encomenda (mostra quando
 * chega) e pelo comando que avisa o cliente ~1h antes.
 */
class RastreioBarco
{
    /** Posição do barco (JSON decodificado), cacheada ~60s. Null se indisponível. */
    public function posicao(Embarcacao $barco): ?array
    {
        $url = trim((string) $barco->rastreio_url);
        if ($url === '') {
            return null;
        }

        return Cache::remember('rastreio:'.md5($url), 60, function () use ($url) {
            try {
                $r = Http::timeout(8)->acceptJson()->get($url);
                $j = $r->ok() ? $r->json() : null;

                return (is_array($j) && ! empty($j['ok'])) ? $j : null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    /**
     * Dados do trecho até uma cidade (compara ignorando acento e maiúscula).
     * Retorna o item de "cidades_a_frente" + barco/online, ou null se a cidade
     * não está à frente (barco já passou, ou não está nessa rota).
     */
    public function trechoAte(Embarcacao $barco, string $cidade): ?array
    {
        $j = $this->posicao($barco);
        if (! $j) {
            return null;
        }

        $alvo = $this->chave($cidade);
        foreach ((array) ($j['cidades_a_frente'] ?? []) as $c) {
            if ($this->chave((string) ($c['cidade'] ?? '')) === $alvo && $alvo !== '') {
                $c['barco'] = $j['barco'] ?? $barco->nome;
                $c['online'] = (bool) data_get($j, 'posicao.online', false);

                return $c;
            }
        }

        return null;
    }

    /** Chegada em linguagem fácil: "hoje às 09:47", "amanhã às 14:00", "quarta às 08:00". */
    public function quandoChega(?string $iso): ?string
    {
        if (! $iso) {
            return null;
        }
        try {
            $d = Carbon::parse($iso);
        } catch (\Throwable $e) {
            return null;
        }

        $dias = (int) Carbon::now($d->timezone)->startOfDay()->diffInDays($d->copy()->startOfDay(), false);
        $hora = $d->format('H:i');

        if ($dias <= 0) {
            return "hoje às {$hora}";
        }
        if ($dias === 1) {
            return "amanhã às {$hora}";
        }
        if ($dias < 7) {
            $sem = [0 => 'domingo', 1 => 'segunda', 2 => 'terça', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sábado'];

            return $sem[$d->dayOfWeek].' às '.$hora;
        }

        return $d->format('d/m').' às '.$hora;
    }

    protected function chave(string $s): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', Str::ascii($s))));
    }
}
