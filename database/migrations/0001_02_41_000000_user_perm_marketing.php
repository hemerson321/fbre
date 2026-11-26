<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Permissão "Marketing / Mídia indoor" (TV indoor + Áudio terminal) por usuário. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'perm_marketing')) {
            Schema::table('users', function (Blueprint $t) {
                $t->boolean('perm_marketing')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'perm_marketing')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropColumn('perm_marketing');
            });
        }
    }
};
