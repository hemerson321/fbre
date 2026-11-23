<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Vincula um hold de poltrona a uma conversa do WhatsApp (reserva feita pelo agente). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('poltrona_holds', 'conversa_id')) {
            Schema::table('poltrona_holds', fn (Blueprint $t) => $t->unsignedBigInteger('conversa_id')->nullable()->index());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('poltrona_holds', 'conversa_id')) {
            Schema::table('poltrona_holds', fn (Blueprint $t) => $t->dropColumn('conversa_id'));
        }
    }
};
