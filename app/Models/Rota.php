<?php

namespace App\Models;

use App\Models\Concerns\PertenceEmpresa;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Rota extends Model
{
    use PertenceEmpresa;

    protected $table = 'rotas';
    protected $guarded = [];
    protected $casts = ['ativo' => 'boolean'];

    public const DIAS = [0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado'];
    public const DIAS_CURTO = [0 => 'Dom', 1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];

    public function paradas(): HasMany { return $this->hasMany(RotaParada::class)->orderBy('ordem'); }
    public function embarcacao(): BelongsTo { return $this->belongsTo(Embarcacao::class); }
    public function viagens(): HasMany { return $this->hasMany(Viagem::class); }

    public function localidades(): array
    {
        return $this->paradas->pluck('localidade')->all();
    }

    public function resumoCalendario(): string
    {
        return $this->paradas->map(fn ($p) => $p->localidade.($p->dia_semana !== null ? ' ('.self::DIAS_CURTO[$p->dia_semana].($p->hora ? ' '.substr($p->hora, 0, 5) : '').')' : ''))->implode(' → ');
    }

    /**
     * Resolve (ou cria) a viagem desta rota para a data informada.
     * A viagem é o ciclo da rota: começa no dia-da-semana da 1ª parada.
     * Um cadastro em qualquer dia cai na viagem que partiu no/antes daquele dia.
     */
    public function viagemPara(?Carbon $data = null): Viagem
    {
        $data = ($data ?: now())->copy()->startOfDay();
        $partida = $this->paradas()->first();
        $diaPartida = $partida?->dia_semana;

        if ($diaPartida === null) {
            // rota sem dia fixo: uma viagem única "aberta"
            $dataPartida = null;
        } else {
            // última ocorrência <= data (até 6 dias atrás) do dia-da-semana da partida
            $dataPartida = $data->copy();
            for ($i = 0; $i < 7; $i++) {
                if ($dataPartida->dayOfWeek === (int) $diaPartida) break;
                $dataPartida->subDay();
            }
        }

        return DB::transaction(function () use ($dataPartida) {
            $existe = Viagem::where('rota_id', $this->id)
                ->when($dataPartida, fn ($q) => $q->whereDate('data_partida', $dataPartida), fn ($q) => $q->whereNull('data_partida'))
                ->first();
            if ($existe) return $existe;

            $numero = (int) Viagem::where('empresa_id', $this->empresa_id)->max('numero') + 1;
            $nome = $this->nome.($dataPartida ? ' · '.$dataPartida->format('d/m') : '');

            return Viagem::create([
                'empresa_id' => $this->empresa_id,
                'rota_id' => $this->id,
                'embarcacao_id' => $this->embarcacao_id,
                'numero' => $numero,
                'nome' => $nome,
                'data_partida' => $dataPartida,
                'status' => 'planejada',
            ]);
        });
    }

    /** Hora de partida (1ª parada), formato HH:MM, ou null. */
    public function horaPartida(): ?string
    {
        $h = optional($this->paradas()->first())->hora;
        return $h ? substr($h, 0, 5) : null;
    }

    /**
     * Viagens do calendário desta rota: materializa as partidas recentes e
     * próximas (uma por semana, no dia-da-semana da 1ª parada) para servir de
     * opção no filtro. Idempotente — reaproveita viagens já criadas.
     */
    public function proximasViagens(int $passadas = 3, int $futuras = 5)
    {
        $partida = $this->paradas()->first();

        if (! $partida || $partida->dia_semana === null) {
            return collect([$this->viagemPara()]); // rota aberta: viagem única
        }

        $dia = (int) $partida->dia_semana;
        $base = now()->startOfDay();
        for ($i = 0; $i < 7; $i++) {
            if ($base->dayOfWeek === $dia) break;
            $base->subDay();
        }

        $viagens = collect();
        for ($k = -$passadas; $k <= $futuras; $k++) {
            $viagens->push($this->viagemPara($base->copy()->addWeeks($k)));
        }

        return $viagens;
    }

    public static function paraSelecao()
    {
        return static::where('ativo', true)->with('paradas')->orderBy('nome')->get();
    }
}
