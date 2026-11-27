<?php

namespace App\Services;

use App\Models\Passagem;
use App\Support\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Gera o PDF do BILHETE da passagem (com QR de check-in) — mesmo desenho do bilhete que o operador
 * gera na tela. Best-effort: se as libs (dompdf / php-qrcode) não estiverem instaladas, retorna null.
 */
class PdfComprovante
{
    public function gerar(Passagem $p): ?string
    {
        if (! class_exists(\Dompdf\Dompdf::class)) return null;
        try {
            $p->loadMissing('passageiros', 'operador', 'embarcacao', 'camarote', 'pagamentos', 'rota.paradas');
            $empresa = Tenant::empresa() ?: \App\Models\Empresa::find($p->empresa_id);
            $track = trim((string) ($p->embarcacao?->gps_url ?? ''));

            $html = view('pdf.passagem', [
                'p' => $p, 'empresa' => $empresa,
                'logo' => $this->logo($empresa),
                'qr' => $this->qr(route('checkin', $p)),
                'qrTrack' => $track !== '' ? $this->qr($track) : null,
            ])->render();

            // bobina de 80mm, igual ao bilhete impresso. A altura depende do conteúdo: tenta do menor pro maior
            // até caber 1 bilhete por página (sem sobrar meio bilhete na página seguinte).
            $bilhetes = max(1, $p->passageiros->filter(fn ($x) => (($x->papel ?? '') !== 'colo'))->count());
            $mm = fn ($v) => $v * 72 / 25.4;
            $saida = null;
            foreach ([240, 270, 300, 340, 390, 460] as $alt) {
                $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->setPaper([0, 0, $mm(80), $mm($alt)]);
                $dompdf->render();
                $saida = $dompdf->output();
                if ($dompdf->getCanvas()->get_page_count() <= $bilhetes) break;
            }
            return $saida;
        } catch (\Throwable $e) {
            Log::warning('PdfComprovante erro: '.$e->getMessage());
            return null;
        }
    }

    /** Logo da empresa embutida (data URI) — o gerador de PDF roda sem acesso remoto. */
    protected function logo($empresa): ?string
    {
        try {
            $arq = $empresa?->logo ? public_path($empresa->logo) : null;
            if (! $arq || ! is_file($arq)) return null;
            $mime = match (strtolower(pathinfo($arq, PATHINFO_EXTENSION))) { 'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', default => null };
            return $mime ? 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($arq)) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** QR como data URI (ou null). */
    protected function qr(string $url): ?string
    {
        try {
            if (class_exists(\chillerlan\QRCode\QRCode::class)) {
                return (new \chillerlan\QRCode\QRCode())->render($url);
            }
        } catch (\Throwable $e) {
            Log::warning('QR pdf erro: '.$e->getMessage());
        }
        return null;
    }
}
