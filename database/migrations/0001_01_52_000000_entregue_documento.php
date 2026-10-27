<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CPF/documento de quem retirou a encomenda, registrado na baixa de entrega. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encomendas', function (Blueprint $t) {
            if (! Schema::hasColumn('encomendas', 'entregue_documento')) {
                $t->string('entregue_documento', 20)->nullable()->after('entregue_para');
            }
        });
    }

    public function down(): void
    {
        Schema::table('encomendas', function (Blueprint $t) {
            if (Schema::hasColumn('encomendas', 'entregue_documento')) $t->dropColumn('entregue_documento');
        });
    }
};
