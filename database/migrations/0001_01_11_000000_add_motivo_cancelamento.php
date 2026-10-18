<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encomendas', function (Blueprint $table) {
            $table->string('motivo_cancelamento', 300)->nullable()->after('cancelada');
        });
    }

    public function down(): void
    {
        Schema::table('encomendas', function (Blueprint $table) {
            $table->dropColumn('motivo_cancelamento');
        });
    }
};
