<?php

namespace App\Support;

/**
 * Catálogo central dos módulos de cadastro: rótulo, ícone, cor e permissão.
 * A cor identifica visualmente em qual módulo o operador está.
 */
class Modulos
{
    /** [chave => [rótulo, ícone, cor base Tailwind, hex principal]] */
    public const LISTA = [
        'encomenda'  => ['Encomendas', '📦', 'blue',    '#2563eb'],
        'passagem'   => ['Passagem',   '🎫', 'emerald', '#059669'],
        'frete'      => ['Frete',      '🚢', 'amber',   '#d97706'],
        'camarote'   => ['Camarote',   '🛏️', 'violet',  '#7c3aed'],
        'lanchonete' => ['Lanchonete', '🍔', 'rose',    '#e11d48'],
        'wifi'       => ['WiFi',       '📶', 'cyan',     '#0891b2'],
        'avulso'     => ['Avulso',     '💵', 'slate',   '#475569'],
        'recibo'     => ['Recibo',     '🧾', 'indigo',  '#4f46e5'],
    ];

    public static function rotulo(string $m): string { return self::LISTA[$m][0] ?? ucfirst($m); }
    public static function icone(string $m): string  { return self::LISTA[$m][1] ?? '•'; }
    public static function cor(string $m): string    { return self::LISTA[$m][2] ?? 'slate'; }
    public static function hex(string $m): string    { return self::LISTA[$m][3] ?? '#475569'; }
}
