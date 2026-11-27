<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Orcamento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/** Gera o PDF de um orçamento salvo (pra mandar no WhatsApp). Best-effort: sem dompdf retorna null. */
class PdfOrcamento
{
    public function gerar(Orcamento $o): ?string
    {
        if (! class_exists(\Dompdf\Dompdf::class)) return null;
        try {
            $empresa = Empresa::find($o->empresa_id);
            if (! $empresa) return null;

            $itens = array_map(fn ($it) => [
                'd' => (string) ($it['d'] ?? 'Passagem'), 'q' => (int) ($it['q'] ?? 0), 'u' => (float) ($it['u'] ?? 0),
                't' => round(((int) ($it['q'] ?? 0)) * ((float) ($it['u'] ?? 0)), 2),
            ], (array) ($o->itens ?: []));

            $emit = Carbon::now();
            $dias = (int) ($empresa->config('orcamento_validade_dias', 7) ?: 7);
            $venc = $o->data_viagem ? Carbon::parse($o->data_viagem) : $emit->copy()->addDays($dias);
            $cidade = trim(($empresa->cidade ?: '').($empresa->uf ? ' / '.$empresa->uf : ''));
            $contato = collect([$empresa->cnpj ? 'CNPJ '.$empresa->cnpj : '', $cidade, $empresa->whatsapp ?: $empresa->telefone])->filter()->implode(' · ');

            $html = view('pdf.orcamento', [
                'emp' => $empresa->nomeExibicao(),
                'logo' => $this->logo($empresa),
                'num' => str_pad((string) ($o->numero ?: $o->id), 4, '0', STR_PAD_LEFT),
                'cliente' => $o->cliente ?: 'Cliente',
                'doc' => (string) $o->documento,
                'origem' => $o->origem ?: '—',
                'destino' => $o->destino ?: '—',
                'emb' => (string) $o->embarcacao_nome,
                'saida' => $o->data_viagem ? Carbon::parse($o->data_viagem)->format('d/m/Y') : '—',
                'pax' => (int) $o->pax,
                'emitido' => $emit->format('d/m/Y'),
                'validade' => $venc->format('d/m/Y'),
                'itens' => $itens,
                'total' => (float) $o->total,
                'contato' => $contato,
            ])->render();

            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            return $dompdf->output();
        } catch (\Throwable $e) {
            Log::warning('PdfOrcamento erro: '.$e->getMessage());
            return null;
        }
    }

    /** Logo da empresa embutida no PDF (data URI) — o dompdf roda sem acesso remoto. */
    protected function logo(Empresa $empresa): ?string
    {
        foreach ([$empresa->logo ? public_path($empresa->logo) : null, public_path('img/ajato-logo.png')] as $arq) {
            if (! $arq || ! is_file($arq)) continue;
            $mime = match (strtolower(pathinfo($arq, PATHINFO_EXTENSION))) { 'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', default => null };
            if (! $mime) continue; // svg/webp: o dompdf não garante — cai no nome da empresa
            return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($arq));
        }
        return null;
    }
}
