<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Define o login do administrador do cliente como "admin" e aplica a senha
 * vinda da variável de ambiente SEED_ADMIN_PASSWORD (nunca escrita no repositório).
 * Roda uma vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        $empresa = DB::table('empresas')->where('slug', 'oliveira')->first();
        if (! $empresa) return;

        $senha = env('SEED_ADMIN_PASSWORD');

        $update = ['login' => 'admin', 'updated_at' => now()];
        if (! empty($senha)) $update['password'] = Hash::make($senha);

        // acha o admin do cliente (por e-mail do seed ou pelo login antigo = slug)
        $q = DB::table('users')->where('empresa_id', $empresa->id)
            ->where(function ($w) {
                $w->where('email', 'admin@oliveira.fbre')->orWhere('login', 'oliveira');
            })
            ->where('super_admin', false);

        $q->update($update);
    }

    public function down(): void {}
};
