<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // escopo do operador no cadastro (vazio/null = tudo liberado)
            $table->json('loc_permitidas')->nullable()->after('setor');          // cidades (origem/destino)
            $table->json('setores_permitidos')->nullable()->after('loc_permitidas');
            $table->json('transportes_permitidos')->nullable()->after('setores_permitidos'); // ids de embarcação
            $table->unsignedBigInteger('embarcacao_principal_id')->nullable()->after('transportes_permitidos'); // barco padrão do operador
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['loc_permitidas', 'setores_permitidos', 'transportes_permitidos', 'embarcacao_principal_id']);
        });
    }
};
