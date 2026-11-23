<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'perm_excluir_prestacao')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('perm_excluir_prestacao')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'perm_excluir_prestacao')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('perm_excluir_prestacao'));
        }
    }
};
