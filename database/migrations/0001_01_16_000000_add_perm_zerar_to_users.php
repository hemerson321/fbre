<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Permissão para zerar os dados do sistema (começar do zero). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'perm_zerar')) {
                $t->boolean('perm_zerar')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'perm_zerar')) {
                $t->dropColumn('perm_zerar');
            }
        });
    }
};
