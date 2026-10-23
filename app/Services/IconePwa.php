<?php

namespace App\Services;

/**
 * Gera icones quadrados (PWA) a partir da logo da empresa, com GD.
 * A logo entra centralizada, com margem, sobre fundo branco (bom p/ "maskable").
 */
class IconePwa
{
    /** @return array{192?:string,512?:string} caminhos publicos (storage/...) */
    public function gerar(string $srcAbs, int $empresaId): array
    {
        if (! function_exists('imagecreatetruecolor') || ! is_file($srcAbs)) {
            return [];
        }
        $info = @getimagesize($srcAbs);
        if (! $info) {
            return [];
        }
        [$w, $h] = $info;
        $src = $this->abrir($srcAbs, $info['mime'] ?? '');
        if (! $src) {
            return [];
        }

        $dir = dirname($srcAbs); // .../storage/app/public/logos
        $out = [];
        foreach ([192, 512] as $size) {
            $canvas = imagecreatetruecolor($size, $size);
            // fundo branco opaco (maskable precisa de fundo)
            $branco = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $size, $size, $branco);
            imagealphablending($canvas, true);

            $pad = (int) round($size * 0.14);
            $max = $size - 2 * $pad;
            $escala = min($max / $w, $max / $h);
            $nw = max(1, (int) round($w * $escala));
            $nh = max(1, (int) round($h * $escala));
            $dx = (int) round(($size - $nw) / 2);
            $dy = (int) round(($size - $nh) / 2);
            imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $nw, $nh, $w, $h);

            $arquivo = $dir.'/pwa-'.$empresaId.'-'.$size.'.png';
            imagepng($canvas, $arquivo);
            imagedestroy($canvas);
            $out[(string) $size] = 'storage/logos/pwa-'.$empresaId.'-'.$size.'.png';
        }
        imagedestroy($src);

        return $out;
    }

    protected function abrir(string $path, string $mime)
    {
        return match ($mime) {
            'image/png'  => @imagecreatefrompng($path),
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            'image/gif'  => @imagecreatefromgif($path),
            default      => null,
        };
    }
}
