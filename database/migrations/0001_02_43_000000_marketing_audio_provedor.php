<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Provedor de voz por áudio (openai | google). Vazio = usa o padrão das Integrações. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketing_audios', 'provedor')) {
            Schema::table('marketing_audios', function (Blueprint $t) {
                $t->string('provedor', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketing_audios', 'provedor')) {
            Schema::table('marketing_audios', function (Blueprint $t) {
                $t->dropColumn('provedor');
            });
        }
    }
};
