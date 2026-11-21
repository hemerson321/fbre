<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pagamentos', 'pago_proprietario')) {
            Schema::table('pagamentos', function (Blueprint $table) {
                $table->boolean('pago_proprietario')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pagamentos', 'pago_proprietario')) {
            Schema::table('pagamentos', fn (Blueprint $t) => $t->dropColumn('pago_proprietario'));
        }
    }
};
