<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Estilo (como se comportar) por áudio. A voz já usa a coluna existente "voz". */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketing_audios', 'estilo')) {
            Schema::table('marketing_audios', function (Blueprint $t) {
                $t->string('estilo', 600)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketing_audios', 'estilo')) {
            Schema::table('marketing_audios', function (Blueprint $t) {
                $t->dropColumn('estilo');
            });
        }
    }
};
