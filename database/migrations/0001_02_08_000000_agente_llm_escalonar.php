<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelo de escalonamento do agente: quando o modelo principal (barato, ex. Gemini)
 * não dá conta, o sistema repete a resposta com este modelo (ex. Claude Sonnet).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('agentes', 'llm_escalonar_id')) {
            Schema::table('agentes', fn (Blueprint $t) => $t->unsignedBigInteger('llm_escalonar_id')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('agentes', 'llm_escalonar_id')) {
            Schema::table('agentes', fn (Blueprint $t) => $t->dropColumn('llm_escalonar_id'));
        }
    }
};
