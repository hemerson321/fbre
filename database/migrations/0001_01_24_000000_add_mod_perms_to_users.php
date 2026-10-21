<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permissão por MÓDULO e por AÇÃO (cadastra/baixa/imprimir), em JSON.
 * Ex.: {"passagem":{"cadastra":true,"baixa":false,"imprimir":true}}
 * Vazio = cai no fallback das permissões antigas (compatível com o que já existe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'mod_perms')) $t->json('mod_perms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'mod_perms')) $t->dropColumn('mod_perms');
        });
    }
};
