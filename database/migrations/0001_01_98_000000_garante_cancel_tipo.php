<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passagem_passageiros', function (Blueprint $t) {
            if (! Schema::hasColumn('passagem_passageiros', 'cancelado')) $t->boolean('cancelado')->default(false);
            if (! Schema::hasColumn('passagem_passageiros', 'cancelado_em')) $t->timestamp('cancelado_em')->nullable();
            if (! Schema::hasColumn('passagem_passageiros', 'cancelado_motivo')) $t->string('cancelado_motivo', 300)->nullable();
            if (! Schema::hasColumn('passagem_passageiros', 'cancelado_tipo')) $t->string('cancelado_tipo', 20)->nullable();
        });
        Schema::table('passagens', function (Blueprint $t) {
            if (! Schema::hasColumn('passagens', 'cancel_tipo')) $t->string('cancel_tipo', 20)->nullable();
        });
    }
};
