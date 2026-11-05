<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('embarcacoes', 'cancel_antes_pct')) {
                $t->unsignedTinyInteger('cancel_antes_pct')->nullable(); // null = usa o padrao da empresa
            }
            if (! Schema::hasColumn('embarcacoes', 'cancel_depois_pct')) {
                $t->unsignedTinyInteger('cancel_depois_pct')->nullable();
            }
        });
        Schema::table('passagens', function (Blueprint $t) {
            if (! Schema::hasColumn('passagens', 'cancel_retido')) {
                $t->decimal('cancel_retido', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('passagens', 'cancel_reembolso')) {
                $t->decimal('cancel_reembolso', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('passagens', 'cancel_fase')) {
                $t->string('cancel_fase', 10)->nullable(); // antes | depois
            }
        });
    }

    public function down(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            foreach (['cancel_antes_pct', 'cancel_depois_pct'] as $c) {
                if (Schema::hasColumn('embarcacoes', $c)) $t->dropColumn($c);
            }
        });
        Schema::table('passagens', function (Blueprint $t) {
            foreach (['cancel_retido', 'cancel_reembolso', 'cancel_fase'] as $c) {
                if (Schema::hasColumn('passagens', $c)) $t->dropColumn($c);
            }
        });
    }
};
