<?php

namespace App\Services;

/**
 * Desenha (PNG) o pedaço do mapa da lancha onde o cliente vai sentar, pra mandar no WhatsApp.
 * Mostra só as fileiras das poltronas reservadas (e as vizinhas), no mesmo visual do mapa do operador.
 */
class MapaPoltronaImagem
{
    /**
     * @param array $d ['lancha'=>string,'linhas'=>[[esq[],dir[]],...],'minhas'=>string[],'ocupadas'=>string[]]
     * @return ?string PNG binário (null se não der pra desenhar)
     */
    public function png(array $d, ?string $subtitulo = null): ?string
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagettftext')) return null;
        $fonte = resource_path('fonts/DejaVuSans-Bold.ttf');
        if (! is_file($fonte)) return null;

        $linhas = $d['linhas'] ?? [];
        $minhas = array_map('mb_strtolower', array_map('strval', $d['minhas'] ?? []));
        $ocup = array_map('mb_strtolower', array_map('strval', $d['ocupadas'] ?? []));
        if (! $linhas || ! $minhas) return null;

        // fileiras que têm poltrona do cliente (+1 antes e depois pra dar contexto)
        $com = [];
        foreach ($linhas as $i => $ln) {
            foreach (array_merge($ln[0], $ln[1]) as $s) if (in_array(mb_strtolower((string) $s), $minhas, true)) { $com[] = $i; break; }
        }
        if (! $com) return null;
        $ini = max(0, min($com) - 1);
        $fim = min(count($linhas) - 1, max($com) + 1);
        if ($fim - $ini > 9) { $ini = min($com); $fim = min(count($linhas) - 1, $ini + 9); }
        $vis = array_slice($linhas, $ini, $fim - $ini + 1);

        $maxE = 1; $maxD = 0;
        foreach ($linhas as $ln) { $maxE = max($maxE, count($ln[0])); $maxD = max($maxD, count($ln[1])); }

        $cw = 124; $ch = 96; $gap = 14; $corr = $maxD > 0 ? 70 : 0; $pad = 44;
        $gridW = $maxE * $cw + ($maxE - 1) * $gap + $corr + ($maxD > 0 ? $maxD * $cw + ($maxD - 1) * $gap : 0);
        $W = max(760, $gridW + $pad * 2 + 40);
        $topo = 190; $rod = 150;
        $gridH = count($vis) * ($ch + $gap);
        $H = $topo + $gridH + 40 + $rod;

        $im = imagecreatetruecolor($W, $H);
        imageantialias($im, true);
        $c = fn ($hex) => imagecolorallocate($im, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
        $branco = $c('#ffffff'); $fundo = $c('#f1f5f9'); $casco = $c('#e5e7eb'); $bordaCasco = $c('#0f172a');
        $txt = $c('#0f172a'); $cinza = $c('#64748b'); $bordaLivre = $c('#cbd5e1');
        $verde = $c('#16a34a'); $verdeEsc = $c('#14532d');
        $verm = $c('#fecaca'); $vermTxt = $c('#b91c1c');
        imagefilledrectangle($im, 0, 0, $W, $H, $fundo);

        $centro = function (string $t, int $tam, int $cx, int $y, $cor) use ($im, $fonte) {
            $b = imagettfbbox($tam, 0, $fonte, $t);
            imagettftext($im, $tam, 0, (int) ($cx - ($b[2] - $b[0]) / 2), $y, $cor, $fonte, $t);
        };
        $caixa = function (int $x, int $y, int $w, int $h, int $r, $cor) use ($im) {
            imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $cor);
            imagefilledrectangle($im, $x, $y + $r, $x + $w, $y + $h - $r, $cor);
            foreach ([[$x + $r, $y + $r], [$x + $w - $r, $y + $r], [$x + $r, $y + $h - $r], [$x + $w - $r, $y + $h - $r]] as $p) {
                imagefilledellipse($im, $p[0], $p[1], $r * 2, $r * 2, $cor);
            }
        };

        // título
        $centro(mb_strtoupper((string) ($d['lancha'] ?? 'LANCHA')), 26, (int) ($W / 2), 58, $txt);
        if ($subtitulo) $centro($subtitulo, 17, (int) ($W / 2), 96, $cinza);
        $centro($ini === 0 ? '↑ PROA (frente)' : '↑ frente da lancha', 15, (int) ($W / 2), 150, $cinza);

        // casco
        $cx0 = (int) (($W - $gridW) / 2) - 26; $cy0 = $topo - 22;
        $caixa($cx0 - 3, $cy0 - 3, $gridW + 58, $gridH + 36, 40, $bordaCasco);
        $caixa($cx0, $cy0, $gridW + 52, $gridH + 30, 38, $casco);

        $x0 = (int) (($W - $gridW) / 2);
        $y = $topo;
        foreach ($vis as $ln) {
            foreach ([0, 1] as $lado) {
                $seats = $ln[$lado];
                $n = count($seats);
                // esquerda alinha à direita do bloco (encosta no corredor); direita alinha à esquerda
                $base = $lado === 0
                    ? $x0 + ($maxE - $n) * ($cw + $gap)
                    : $x0 + $maxE * $cw + ($maxE - 1) * $gap + $corr;
                foreach ($seats as $k => $s) {
                    $x = $base + $k * ($cw + $gap);
                    $low = mb_strtolower((string) $s);
                    if (in_array($low, $minhas, true)) {
                        $caixa($x - 4, $y - 4, $cw + 8, $ch + 8, 20, $verdeEsc);
                        $caixa($x, $y, $cw, $ch, 18, $verde);
                        $centro((string) $s, 24, $x + (int) ($cw / 2), $y + 48, $branco);
                        $centro('SUA', 12, $x + (int) ($cw / 2), $y + 78, $branco);
                    } elseif (in_array($low, $ocup, true)) {
                        $caixa($x, $y, $cw, $ch, 18, $verm);
                        $centro((string) $s, 21, $x + (int) ($cw / 2), $y + 58, $vermTxt);
                    } else {
                        $caixa($x - 2, $y - 2, $cw + 4, $ch + 4, 19, $bordaLivre);
                        $caixa($x, $y, $cw, $ch, 18, $branco);
                        $centro((string) $s, 21, $x + (int) ($cw / 2), $y + 58, $txt);
                    }
                }
            }
            if ($corr > 0) $centro('corredor', 9, $x0 + $maxE * $cw + ($maxE - 1) * $gap + (int) ($corr / 2), $y + 54, $cinza);
            $y += $ch + $gap;
        }

        // legenda
        $ly = $topo + $gridH + 78; $lx = (int) ($W / 2) - 300;
        foreach ([[$verde, 'sua poltrona'], [$branco, 'livre'], [$verm, 'ocupada']] as $i => $it) {
            $px = $lx + $i * 215;
            $caixa($px - 2, $ly - 26, 36, 36, 9, $bordaLivre);
            $caixa($px, $ly - 24, 32, 32, 8, $it[0]);
            imagettftext($im, 15, 0, $px + 46, $ly, $txt, $fonte, $it[1]);
        }
        $lista = implode(', ', array_map('strval', $d['minhas']));
        $centro((count($d['minhas']) > 1 ? 'Suas poltronas: ' : 'Sua poltrona: ').$lista, 19, (int) ($W / 2), $ly + 62, $verdeEsc);

        ob_start();
        imagepng($im, null, 6);
        $png = ob_get_clean();
        imagedestroy($im);
        return $png ?: null;
    }
}
