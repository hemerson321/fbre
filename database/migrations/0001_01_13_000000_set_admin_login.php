<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Define o login do administrador do cliente como "admin" e aplica a senha.
 * A variável de ambiente SEED_ADMIN_PASSWORD tem prioridade (pra trocar sem
 * mexer no código); se não existir, usa a senha padrão abaixo. Repo privado.
 * Roda uma vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        $empresa = DB::table('empresas')->where('slug', 'oliveira')->first();
        if (! $empresa) return;

        $senha = env('SEED_ADMIN_PASSWORD') ?: 'Malu2013@123';

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
