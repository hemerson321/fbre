<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('papel')->nullable()->after('setor'); // operador_barco | agencia_externa | gerente
            $table->boolean('perm_retroativo')->default(false)->after('perm_imprimir'); // pode lançar em datas passadas
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['papel', 'perm_retroativo']);
        });
    }
};
