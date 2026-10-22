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
        // sid fixo por operador: o link do QR é sempre o mesmo e não expira,
        // então o operador escaneia uma vez e continua enviando sem reabrir.
        $sid = substr(hash_hmac('sha256', 'captura:'.Tenant::id().':'.$u->id, (string) config('app.key')), 0, 20);
        $url = URL::signedRoute('captura.mobile', ['e' => Tenant::id(), 'u' => $u->id, 's' => $sid]);
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
                'foto' => $c->foto,
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
        $pathRosto = $this->recortarRosto($data['foto'], $campos['foto_box'] ?? null);

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
            'foto' => $pathRosto,
            'dados' => $res,
            'status' => 'nova',
        ]);

        return response()->json(['ok' => true, 'lido' => (bool) ($res['ok'] ?? false), 'erro' => $res['erro'] ?? null]);
    }

    /** OCR direto (desktop): operador logado envia a foto e recebe os campos na hora. */
    public function ocrDireto(Request $request, LeitorDocumento $leitor)
    {
        $data = $request->validate(['foto' => 'required|string']);
        $empresa = Tenant::empresa();
        $pathRg = $this->salvarFoto($data['foto']);
        $res = $leitor->extrair($data['foto'], $empresa->iaConfig());
        $campos = $res['ok'] ? ($res['campos'] ?? []) : [];
        $rosto = $this->recortarRosto($data['foto'], $campos['foto_box'] ?? null);
        return response()->json([
            'ok' => (bool) ($res['ok'] ?? false),
            'erro' => $res['erro'] ?? null,
            'foto_rg' => $pathRg,
            'foto' => $rosto,
            'nome' => $campos['nome'] ?? null,
            'nascimento' => $this->paraData($campos['nascimento'] ?? null),
            'sexo' => $this->paraSexo($campos['sexo'] ?? null),
            'nacionalidade' => $campos['nacionalidade'] ?? null,
            'documento' => $campos['documento'] ?? null,
        ]);
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

    /** Recorta só a foto 3x4 do titular (privacidade: não expõe o doc inteiro). */
    protected function recortarRosto(string $dataUrl, $box): ?string
    {
        if (! is_array($box) || ! function_exists('imagecreatefromstring')) return null;
        if (! preg_match('/^data:image\/\w+;base64,(.+)$/s', $dataUrl, $m)) return null;
        $bin = base64_decode($m[1], true);
        if ($bin === false) return null;
        $img = @imagecreatefromstring($bin);
        if (! $img) return null;

        $W = imagesx($img); $H = imagesy($img);
        $px = fn ($v, $dim) => $v <= 1 ? (int) round($v * $dim) : ($v <= 100 ? (int) round($v / 100 * $dim) : min((int) $v, $dim));
        $x = max(0, $px((float) ($box['x'] ?? 0), $W));
        $y = max(0, $px((float) ($box['y'] ?? 0), $H));
        $w = $px((float) ($box['w'] ?? 0), $W);
        $h = $px((float) ($box['h'] ?? 0), $H);
        // margem de 8% e limites
        $mx = (int) round($w * 0.08); $my = (int) round($h * 0.08);
        $x = max(0, $x - $mx); $y = max(0, $y - $my);
        $w = min($W - $x, $w + 2 * $mx); $h = min($H - $y, $h + 2 * $my);
        if ($w < 24 || $h < 24) { imagedestroy($img); return null; }

        $crop = imagecrop($img, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
        imagedestroy($img);
        if (! $crop) return null;
        ob_start(); imagejpeg($crop, null, 85); $jpg = ob_get_clean();
        imagedestroy($crop);

        $nome = 'capturas/'.date('Y/m').'/rosto-'.Str::random(20).'.jpg';
        Storage::disk('public')->put($nome, $jpg);
        return 'storage/'.$nome;
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
