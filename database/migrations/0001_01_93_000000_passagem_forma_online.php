<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amplia o ENUM da coluna 'forma' das passagens para aceitar 'online'
     * (forma de pagamento "Compra Online"). Sem isso o MySQL trunca o valor
     * e a venda online dá erro 500.
     */
    public function up(): void
    {
        if (Schema::hasColumn('passagens', 'forma')) {
            DB::statement("ALTER TABLE passagens MODIFY forma ENUM('avista','areceber','faturado','online') NOT NULL DEFAULT 'avista'");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('passagens', 'forma')) {
            // Volta ao enum antigo (passagens 'online' viram 'avista' pra nao quebrar)
            DB::statement("UPDATE passagens SET forma='avista' WHERE forma='online'");
            DB::statement("ALTER TABLE passagens MODIFY forma ENUM('avista','areceber','faturado') NOT NULL DEFAULT 'avista'");
        }
    }
};
