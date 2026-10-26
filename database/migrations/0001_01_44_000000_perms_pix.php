<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Permissões de PIX por usuário: usar a caixa manual e gerar PIX dinâmico. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'perm_pix_manual')) $t->boolean('perm_pix_manual')->default(true);
            if (! Schema::hasColumn('users', 'perm_pix_dinamico')) $t->boolean('perm_pix_dinamico')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            foreach (['perm_pix_manual', 'perm_pix_dinamico'] as $c) {
                if (Schema::hasColumn('users', $c)) $t->dropColumn($c);
            }
        });
    }
};
