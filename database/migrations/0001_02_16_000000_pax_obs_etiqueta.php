<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Observação digitada no check-in, impressa na etiqueta de bagagem e na via do passageiro. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passagem_passageiros', function (Blueprint $t) {
            if (! Schema::hasColumn('passagem_passageiros', 'obs_etiqueta')) {
                $t->string('obs_etiqueta', 200)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('passagem_passageiros', function (Blueprint $t) {
            if (Schema::hasColumn('passagem_passageiros', 'obs_etiqueta')) $t->dropColumn('obs_etiqueta');
        });
    }
};
