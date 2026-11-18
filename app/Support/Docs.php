<?php

namespace App\Support;

/** Normaliza/descreve anexos de documentos (passageiro e gerais). */
class Docs
{
    /** Normaliza uma entrada (string legada ou objeto) em ['u','n','t'] ou null. */
    public static function norm($e): ?array
    {
        if (is_string($e)) {
            $e = trim($e);
            if ($e === '') return null;
            return ['u' => $e, 'n' => basename($e), 't' => self::mimePorPath($e)];
        }
        if (is_array($e)) {
            $u = $e['u'] ?? ($e['url'] ?? null);
            if (! $u) return null;
            $n = $e['n'] ?? ($e['nome'] ?? basename($u));
            $t = $e['t'] ?? ($e['tipo'] ?? self::mimePorPath($u));
            return ['u' => $u, 'n' => $n, 't' => $t];
        }
        return null;
    }

    /** Lista normalizada e filtrada. */
    public static function lista($docs): array
    {
        $out = [];
        foreach ((array) $docs as $e) {
            $n = self::norm($e);
            if ($n) $out[] = $n;
        }
        return $out;
    }

    public static function ehImagem(array $d): bool
    {
        if (str_starts_with((string) ($d['t'] ?? ''), 'image/')) return true;
        return (bool) preg_match('/\.(jpe?g|png|webp|gif|bmp|heic|heif)$/i', (string) ($d['u'] ?? ''));
    }

    public static function ehPdf(array $d): bool
    {
        return ($d['t'] ?? '') === 'application/pdf' || (bool) preg_match('/\.pdf$/i', (string) ($d['u'] ?? ''));
    }

    public static function icone(array $d): string
    {
        if (self::ehImagem($d)) return '🖼️';
        if (self::ehPdf($d)) return '📄';
        $u = strtolower((string) ($d['u'] ?? ''));
        $t = strtolower((string) ($d['t'] ?? ''));
        if (preg_match('/\.(docx?|odt)$/', $u) || str_contains($t, 'word')) return '📝';
        if (preg_match('/\.(xlsx?|csv|ods)$/', $u) || str_contains($t, 'sheet') || str_contains($t, 'excel') || str_contains($t, 'csv')) return '📊';
        return '📎';
    }

    public static function mimePorPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
            'gif' => 'image/gif', 'bmp' => 'image/bmp', 'heic' => 'image/heic', 'heif' => 'image/heif',
            'pdf' => 'application/pdf', 'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain', 'csv' => 'text/csv',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }

    public static function extPorMime(string $mime): string
    {
        $map = [
            'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            'image/gif' => 'gif', 'image/bmp' => 'bmp', 'image/heic' => 'heic', 'image/heif' => 'heif',
            'application/pdf' => 'pdf', 'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt', 'text/csv' => 'csv',
        ];
        if (isset($map[$mime])) return $map[$mime];
        if (str_starts_with($mime, 'image/')) {
            $e = preg_replace('/[^a-z0-9]/', '', explode('/', $mime)[1] ?? '');
            return $e ?: 'img';
        }
        return 'bin';
    }
}
