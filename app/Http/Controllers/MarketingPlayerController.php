<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\MarketingAudio;
use App\Models\MarketingDisparo;
use App\Models\MarketingProgramacao;
use App\Models\MarketingTerminal;
use Illuminate\Http\Request;

/** Player público do áudio terminal: um tablet/PC com som abre /audio/{token} e toca a fila. */
class MarketingPlayerController extends Controller
{
    protected function terminal(string $token): MarketingTerminal
    {
        return MarketingTerminal::withoutGlobalScopes()->where('token', $token)->firstOrFail();
    }

    public function show(Request $request, string $token)
    {
        $terminal = $this->terminal($token);
        $empresa = (string) (Empresa::find($terminal->empresa_id)?->nome ?? '');

        return response()->view('marketing.player', ['terminal' => $terminal, 'empresa' => $empresa])
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }

    public function dados(Request $request, string $token)
    {
        $terminal = $this->terminal($token);
        MarketingTerminal::withoutGlobalScopes()->whereKey($terminal->id)->update(['visto_em' => now()]);

        $itens = [];
        if ($terminal->ativo) {
            $emp = (int) $terminal->empresa_id;
            $audios = MarketingAudio::withoutGlobalScopes()->where('empresa_id', $emp)->where('ativo', true)->get()->keyBy('id');

            // "Tocar agora": disparos dos últimos 5 minutos pra este terminal (ou todos)
            $disparos = MarketingDisparo::withoutGlobalScopes()->where('empresa_id', $emp)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->where(fn ($q) => $q->whereNull('terminal_id')->orWhere('terminal_id', $terminal->id))
                ->orderBy('id')->get();
            foreach ($disparos as $d) {
                if ($a = $audios->get($d->audio_id)) $itens[] = $a->paraPlayer('disp-'.$d->id);
            }

            // Programação: casa com dia da semana + horário (minuto atual)
            $dow = (int) now()->dayOfWeek;         // 0=dom..6=sáb
            $hora = now()->format('H:i');
            $marca = now()->format('Ymd-Hi');
            $progs = MarketingProgramacao::withoutGlobalScopes()->where('empresa_id', $emp)->where('ativo', true)
                ->where('hora', $hora)
                ->where(fn ($q) => $q->whereNull('terminal_id')->orWhere('terminal_id', $terminal->id))
                ->get();
            foreach ($progs as $p) {
                if (! $p->tocaNoDia($dow)) continue;
                if ($a = $audios->get($p->audio_id)) $itens[] = $a->paraPlayer('prog-'.$p->id.'-'.$marca);
            }
        }

        return response()->json([
            'terminal' => (string) $terminal->nome,
            'ativo' => (bool) $terminal->ativo,
            'itens' => $itens,
        ])->header('Cache-Control', 'no-store');
    }
}
