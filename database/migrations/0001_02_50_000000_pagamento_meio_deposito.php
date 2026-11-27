<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Amplia o ENUM da coluna 'meio' dos pagamentos para aceitar 'deposito'.
 * A prestação de contas (pagar o proprietário) e os avulsos oferecem "Depósito", mas a coluna não aceitava:
 * o MySQL recusava/truncava o valor e fechar a prestação com Depósito dava erro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pagamentos', 'meio') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE pagamentos MODIFY meio ENUM('dinheiro','pix','debito','credito','desconto','deposito') NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pagamentos', 'meio') && DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE pagamentos SET meio = 'dinheiro' WHERE meio = 'deposito'");
            DB::statement("ALTER TABLE pagamentos MODIFY meio ENUM('dinheiro','pix','debito','credito','desconto') NOT NULL");
        }
    }
};
