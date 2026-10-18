<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encomendas', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');          // id gerado no aparelho (offline)
            $table->string('numero_provisorio', 30)->nullable()->after('numero'); // ex.: P-A1B2C3 (impresso offline)
            $table->timestamp('criada_no_aparelho_em')->nullable()->after('created_at');
            $table->boolean('origem_offline')->default(false)->after('cancelada');
        });
        Schema::table('pagamentos', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });
        Schema::table('eventos', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('encomendas', fn (Blueprint $t) => $t->dropColumn(['uuid', 'numero_provisorio', 'criada_no_aparelho_em', 'origem_offline']));
        Schema::table('pagamentos', fn (Blueprint $t) => $t->dropColumn('uuid'));
        Schema::table('eventos', fn (Blueprint $t) => $t->dropColumn('uuid'));
    }
};
