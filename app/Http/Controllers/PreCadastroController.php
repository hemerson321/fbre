<?php

namespace App\Http\Controllers;

use App\Models\PreCadastro;
use App\Support\Tenant;
use Illuminate\Http\Request;

/** Lado do operador: lista de pré-cadastros + QR/banner. */
class PreCadastroController extends Controller
{
    public function index(Request $request)
    {
        $busca = trim((string) $request->input('busca'));

        $pendentes = PreCadastro::where('status', 'pendente')
            ->when($busca, fn ($q) => $q->where(fn ($w) => $w
                ->where('codigo', 'like', "%$busca%")
                ->orWhere('remetente_nome', 'like', "%$busca%")
                ->orWhere('destinatario_nome', 'like', "%$busca%")))
            ->orderBy('created_at')
            ->get();

        return view('pre-cadastros.index', ['pendentes' => $pendentes, 'busca' => $busca]);
    }

    public function cancelar(PreCadastro $pre)
    {
        if ($pre->status === 'pendente') $pre->update(['status' => 'cancelado']);
        return back()->with('ok', 'Pré-cadastro removido.');
    }

    public function qr()
    {
        $empresa = Tenant::empresa();
        return view('pre-cadastros.qr', [
            'empresa' => $empresa,
            'url' => url('/pre/' . $empresa->slug),
        ]);
    }
}
