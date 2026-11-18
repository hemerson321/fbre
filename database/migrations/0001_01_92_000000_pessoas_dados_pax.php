<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pessoas', function (Blueprint $t) {
            if (! Schema::hasColumn('pessoas', 'sexo'))          $t->string('sexo', 1)->nullable()->after('documento');
            if (! Schema::hasColumn('pessoas', 'doc_tipo'))      $t->string('doc_tipo', 20)->nullable()->after('documento');
            if (! Schema::hasColumn('pessoas', 'nascimento'))    $t->date('nascimento')->nullable()->after('sexo');
            if (! Schema::hasColumn('pessoas', 'nacionalidade')) $t->string('nacionalidade', 40)->nullable()->after('nascimento');
            if (! Schema::hasColumn('pessoas', 'foto'))          $t->string('foto', 255)->nullable()->after('nacionalidade');
            if (! Schema::hasColumn('pessoas', 'foto_rg'))       $t->string('foto_rg', 255)->nullable()->after('foto');
        });
    }

    public function down(): void
    {
        Schema::table('pessoas', function (Blueprint $t) {
            foreach (['sexo','doc_tipo','nascimento','nacionalidade','foto','foto_rg'] as $c) {
                if (Schema::hasColumn('pessoas', $c)) $t->dropColumn($c);
            }
        });
    }
};
