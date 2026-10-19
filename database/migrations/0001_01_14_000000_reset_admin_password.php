<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Força o login "admin" e a SENHA do administrador do cliente.
 * A migration anterior (0001_01_13) só rodou uma vez, antes da senha existir,
 * e o seeder não mexe na senha de quem já existe — por isso esta aplica de fato.
 * SEED_ADMIN_PASSWORD tem prioridade; senão usa a senha padrão (repo privado).
 */
return new class extends Migration
{
    public function up(): void
    {
        $empresa = DB::table('empresas')->where('slug', 'oliveira')->first();
        if (! $empresa) return;

        $senha = env('SEED_ADMIN_PASSWORD') ?: 'Malu2013@123';

        DB::table('users')
            ->where('empresa_id', $empresa->id)
            ->where('super_admin', false)
            ->where(function ($w) {
                $w->where('login', 'admin')
                  ->orWhere('login', 'oliveira')
                  ->orWhere('email', 'admin@oliveira.fbre');
            })
            ->update([
                'login'      => 'admin',
                'password'   => Hash::make($senha),
                'updated_at' => now(),
            ]);
    }

    public function down(): void {}
};
