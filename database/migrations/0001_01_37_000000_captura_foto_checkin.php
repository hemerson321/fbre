<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Captura por foto (RG via IA) + check-in de passageiro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passagem_passageiros', function (Blueprint $t) {
            if (! Schema::hasColumn('passagem_passageiros', 'sexo')) $t->string('sexo', 1)->nullable();
            if (! Schema::hasColumn('passagem_passageiros', 'telefone')) $t->string('telefone', 30)->nullable();
            if (! Schema::hasColumn('passagem_passageiros', 'nacionalidade')) $t->string('nacionalidade', 40)->nullable();
            if (! Schema::hasColumn('passagem_passageiros', 'checkin')) $t->boolean('checkin')->default(true);
            if (! Schema::hasColumn('passagem_passageiros', 'foto_rg')) $t->string('foto_rg', 255)->nullable();
            if (! Schema::hasColumn('passagem_passageiros', 'foto')) $t->string('foto', 255)->nullable();
        });

        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'checkin_auto')) $t->boolean('checkin_auto')->default(true);
        });

        if (! Schema::hasTable('capturas')) {
            Schema::create('capturas', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->string('token', 40)->index();      // sessão do operador (do QR)
                $t->foreignId('user_id')->nullable();   // operador dono da sessão
                $t->string('origem', 80)->nullable();
                $t->string('destino', 80)->nullable();
                $t->string('nome', 120)->nullable();
                $t->date('nascimento')->nullable();
                $t->string('sexo', 1)->nullable();
                $t->string('nacionalidade', 40)->nullable();
                $t->string('documento', 30)->nullable();
                $t->string('foto_rg', 255)->nullable();
                $t->string('foto', 255)->nullable();
                $t->json('dados')->nullable();          // retorno bruto da IA
                $t->string('status', 12)->default('nova'); // nova | usada
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('capturas');
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'checkin_auto')) $t->dropColumn('checkin_auto');
        });
        Schema::table('passagem_passageiros', function (Blueprint $t) {
            foreach (['sexo', 'telefone', 'nacionalidade', 'checkin', 'foto_rg', 'foto'] as $c) {
                if (Schema::hasColumn('passagem_passageiros', $c)) $t->dropColumn($c);
            }
        });
    }
};
