<?php

namespace App\Support;

use App\Models\Empresa;

/**
 * Empresa ativa na requisição.
 * Definida pelo middleware a partir do usuário logado (ou pelo token da estação de impressão).
 */
class Tenant
{
    protected static ?int $id = null;
    protected static ?Empresa $empresa = null;

    public static function set(?int $id): void
    {
        static::$id = $id;
        static::$empresa = null;
    }

    public static function id(): ?int
    {
        return static::$id;
    }

    public static function empresa(): ?Empresa
    {
        if (static::$id && ! static::$empresa) {
            static::$empresa = Empresa::find(static::$id);
        }
        return static::$empresa;
    }

    public static function clear(): void
    {
        static::$id = null;
        static::$empresa = null;
    }
}
