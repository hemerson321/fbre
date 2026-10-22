<?php

namespace App\Http\Controllers;

use App\Models\Captura;
use App\Models\Empresa;
use App\Models\Rota;
use App\Models\TrechoPreco;
use App\Models\User;
use App\Services\LeitorDocumento;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Captura por foto: operador gera QR (sessão), celular lê o RG,
 * a IA extrai os dados e o computador (Nova passagem) preenche sozinho.
 */
class CapturaController extends Controller
{
    /** Operador: cria sessão e devolve a URL assinada do QR. */
    public function novo(Request $request)
    {
        $u = auth()->user();
        $sid = Str::random(16);
        $url = URL::temporarySignedRoute('captura.mobile', now()->addHours(3), [
            'e' => Tenant::id(), 'u' => $u->id, 's' => $sid,
        ]);
        return response()->json(['sid' => $sid, 'url' => $url]);
    }

    /** Operador: polling das capturas novas desta sessão. */
    public function pendentes(Request $request)
    {
        $sid = (string) $request->query('s');
        $desde = (int) $request->query('desde', 0);
        if ($sid === '') return response()->json(['itens' => []]);

        $itens = Captura::where('token', $sid)->where('id', '>', $desde)
            ->where('status', 'nova')->orderBy('id')->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'nome' => $c->nome,
                'nascimento' => optional($c->nascimento)->format('Y-m-d'),
                'sexo' => $c->sexo,
                'nacionalidade' => $c->nacionalidade,
                'documento' => $c->documento,
                'origem' => $c->origem,
                'destino' => $c->destino,
                'foto_rg' => $c->foto_rg,
            ]);

        return response()->json(['itens' => $itens]);
    }

    /** Operador: marca uma captura como usada (depois de preencher). */
    public function usar(Request $request, Captura $captura)
    {
        abort_unless($captura->empresa_id === Tenant::id(), 404);
        $captura->update(['status' => 'usada']);
        return response()->json(['ok' => true]);
    }

    /** Público (link assinado): mini-form no celular. */
    public function mobile(Request $request)
    {
        abort_unless($request->hasValidSignature(), 403, 'Link expirado. Gere um novo QR no computador.');
        $empresa = Empresa::find((int) $request->query('e'));
        abort_unless($empresa, 404);
        $operador = User::find((int) $request->query('u'));

        $cidades = TrechoPreco::where('empresa_id', $empresa->id)->pluck('origem')
            ->merge(TrechoPreco::where('empresa_id', $empresa->id)->pluck('destino'))
            ->merge(Rota::where('empresa_id', $empresa->id)->with('paradas')->get()->flatMap(fn ($r) => $r->paradas->pluck('localidade')))
            ->filter()->unique()->sort()->values();
        if ($operador && ($perm = $operador->cidadesPermitidas())) {
            $cidades = $cidades->intersect($perm)->values();
        }

        return view('publico.captura', [
            'empresa' => $empresa,
            'e' => (int) $request->query('e'),
            'u' => (int) $request->query('u'),
            's' => (string) $request->query('s'),
            'cidades' => $cidades,
        ]);
    }

    /** Público: recebe a foto do RG, roda a IA e grava a captura. */
    public function enviar(Request $request, LeitorDocumento $leitor)
    {
        $data = $request->validate([
            'e' => 'required|integer',
            'u' => 'nullable|integer',
            's' => 'required|string|max:40',
            'origem' => 'nullable|string|max:80',
            'destino' => 'nullable|string|max:80',
            'foto' => 'required|string',
        ]);
        $empresa = Empresa::find($data['e']);
        abort_unless($empresa, 404);

        $pathRg = $this->salvarFoto($data['foto']);
        $res = $leitor->extrair($data['foto'], $empresa->iaConfig());
        $campos = $res['ok'] ? ($res['campos'] ?? []) : [];

        Captura::create([
            'empresa_id' => $empresa->id,
            'token' => $data['s'],
            'user_id' => $data['u'] ?? null,
            'origem' => $data['origem'] ?? null,
            'destino' => $data['destino'] ?? null,
            'nome' => $campos['nome'] ?? null,
            'nascimento' => $this->paraData($campos['nascimento'] ?? null),
            'sexo' => $this->paraSexo($campos['sexo'] ?? null),
            'nacionalidade' => $campos['nacionalidade'] ?? null,
            'documento' => $campos['documento'] ?? null,
            'foto_rg' => $pathRg,
            'dados' => $res,
            'status' => 'nova',
        ]);

        return response()->json(['ok' => true, 'lido' => (bool) ($res['ok'] ?? false), 'erro' => $res['erro'] ?? null]);
    }

    protected function salvarFoto(string $dataUrl): ?string
    {
        if (preg_match('/^data:image\/\w+;base64,(.+)$/s', $dataUrl, $m)) {
            $bin = base64_decode($m[1], true);
            if ($bin !== false && strlen($bin) <= 6 * 1024 * 1024) {
                $nome = 'capturas/'.date('Y/m').'/'.Str::random(24).'.jpg';
                Storage::disk('public')->put($nome, $bin);
                return 'storage/'.$nome;
            }
        }
        return null;
    }

    protected function paraData(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') return null;
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $v, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $v)) return $v;
        return null;
    }

    protected function paraSexo(?string $v): ?string
    {
        $v = mb_strtoupper(trim((string) $v));
        if ($v === '') return null;
        return str_starts_with($v, 'M') ? 'M' : (str_starts_with($v, 'F') ? 'F' : null);
    }
}
