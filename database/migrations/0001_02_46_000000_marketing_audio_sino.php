<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tocar um sinal (tim-tim de aeroporto) antes da fala. Padrão: ligado. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketing_audios', 'sino')) {
            Schema::table('marketing_audios', function (Blueprint $t) {
                $t->boolean('sino')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketing_audios', 'sino')) {
            Schema::table('marketing_audios', function (Blueprint $t) {
                $t->dropColumn('sino');
            });
        }
    }
};
