<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\SignageMidia;
use App\Models\SignageTela;
use App\Support\Versao;
use Illuminate\Http\Request;

/** Player público do Digital Signage: a TV Box abre /tv/{token} em tela cheia (quiosque), sem login. */
class SignagePlayerController extends Controller
{
    protected function tela(string $token): SignageTela
    {
        return SignageTela::withoutGlobalScopes()->where('token', $token)->firstOrFail();
    }

    public function show(Request $request, string $token)
    {
        $tela = $this->tela($token);
        $empresa = (string) (Empresa::find($tela->empresa_id)?->nome ?? '');
        return response()->view('signage.player', ['tela' => $tela, 'empresa' => $empresa, 'demo' => $request->boolean('demo')])
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }

    public function dados(Request $request, string $token)
    {
        $tela = $this->tela($token);
        if (! $request->boolean('demo')) {
            SignageTela::withoutGlobalScopes()->whereKey($tela->id)->update(['visto_em' => now()]);
        }
        $itens = [];
        if ($tela->ativo) {
            $itens = SignageMidia::withoutGlobalScopes()->where('tela_id', $tela->id)->where('ativo', true)
                ->orderBy('ordem')->orderBy('id')->get()
                ->filter(fn ($m) => $m->vigente())
                ->map(fn ($m) => $m->paraPlayer())->values()->all();
        }
        return response()->json([
            'empresa' => (string) (Empresa::find($tela->empresa_id)?->nome ?? ''),
            'orientacao' => $tela->orientacao,
            'versao' => Versao::ATUAL,
            'itens' => $itens,
        ])->header('Cache-Control', 'no-store');
    }
}
