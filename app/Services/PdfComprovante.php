<?php

namespace App\Services;

use App\Models\Passagem;
use App\Support\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Gera o PDF do comprovante da passagem (com QR de check-in). Best-effort:
 * se as libs (dompdf / php-qrcode) não estiverem instaladas, retorna null.
 */
class PdfComprovante
{
    public function gerar(Passagem $p): ?string
    {
        if (! class_exists(\Dompdf\Dompdf::class)) return null;
        try {
            $p->loadMissing('passageiros', 'operador', 'embarcacao', 'camarote');
            $empresa = Tenant::empresa();
            $qr = $this->qr(route('checkin', $p));

            $html = view('pdf.passagem', ['p' => $p, 'empresa' => $empresa, 'qr' => $qr])->render();

            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A5', 'portrait');
            $dompdf->render();
            return $dompdf->output();
        } catch (\Throwable $e) {
            Log::warning('PdfComprovante erro: '.$e->getMessage());
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
