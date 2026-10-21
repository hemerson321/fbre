<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permissões granulares por MÓDULO de cadastro.
 * Cada operador pode ter liberado individualmente quais módulos vende.
 * Vazio/false = não acessa aquele módulo. Admin/gerente acessam tudo.
 */
return new class extends Migration
{
    public array $cols = [
        'perm_passagem', 'perm_frete', 'perm_camarote',
        'perm_lanchonete', 'perm_wifi', 'perm_avulso', 'perm_recibo',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            foreach ($this->cols as $c) {
                if (! Schema::hasColumn('users', $c)) {
                    $t->boolean($c)->default(false);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            foreach ($this->cols as $c) {
                if (Schema::hasColumn('users', $c)) $t->dropColumn($c);
            }
        });
    }
};
