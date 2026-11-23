<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escopo da trava de poltrona: pra quem a poltrona fica indisponível.
 * escopo_tipo: todos | operadores | revendedores | ia | usuario
 * escopo_id: id do usuário quando escopo_tipo = usuario (senão null)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('poltronas_bloqueadas', 'escopo_tipo')) {
            Schema::table('poltronas_bloqueadas', fn (Blueprint $t) => $t->string('escopo_tipo', 20)->default('todos'));
        }
        if (! Schema::hasColumn('poltronas_bloqueadas', 'escopo_id')) {
            Schema::table('poltronas_bloqueadas', fn (Blueprint $t) => $t->unsignedBigInteger('escopo_id')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('poltronas_bloqueadas', function (Blueprint $table) {
            foreach (['escopo_tipo', 'escopo_id'] as $c) {
                if (Schema::hasColumn('poltronas_bloqueadas', $c)) $table->dropColumn($c);
            }
        });
    }
};
