<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Domínio próprio da empresa (white-label): ex. ajatopassagem.com.br -> empresa X. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $t) {
            if (! Schema::hasColumn('empresas', 'dominio')) {
                $t->string('dominio', 120)->nullable()->unique()->after('slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $t) {
            if (Schema::hasColumn('empresas', 'dominio')) $t->dropColumn('dominio');
        });
    }
};
