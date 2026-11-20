<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passagens', function (Blueprint $t) {
            if (! Schema::hasColumn('passagens', 'parcelas')) $t->unsignedSmallInteger('parcelas')->default(1);
        });
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'perm_forma_avista')) $t->boolean('perm_forma_avista')->nullable();
            if (! Schema::hasColumn('users', 'perm_forma_parcelado')) $t->boolean('perm_forma_parcelado')->nullable();
            if (! Schema::hasColumn('users', 'perm_forma_faturado')) $t->boolean('perm_forma_faturado')->nullable();
            if (! Schema::hasColumn('users', 'perm_forma_online')) $t->boolean('perm_forma_online')->nullable();
        });
        // Usuarios que JA existem mantem acesso a todas as formas (nao quebra quem usava "A receber"/Faturado).
        // Marca so os que ainda nao foram marcados (migration idempotente).
        if (Schema::hasColumn('users', 'perm_forma_parcelado')) {
            \DB::table('users')->whereNull('perm_forma_parcelado')->update(['perm_forma_parcelado' => true, 'perm_forma_faturado' => true, 'perm_forma_avista' => true, 'perm_forma_online' => true]);
        }
    }
};
