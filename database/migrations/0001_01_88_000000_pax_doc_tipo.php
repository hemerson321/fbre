<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passagem_passageiros', function (Blueprint $t) {
            if (! Schema::hasColumn('passagem_passageiros', 'doc_tipo')) {
                $t->string('doc_tipo', 20)->nullable()->after('documento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('passagem_passageiros', function (Blueprint $t) {
            if (Schema::hasColumn('passagem_passageiros', 'doc_tipo')) {
                $t->dropColumn('doc_tipo');
            }
        });
    }
};
