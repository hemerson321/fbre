<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * tipo da fila_impressao vira VARCHAR (antes era ENUM). Assim qualquer tipo novo
 * de impressão (ex.: 'generico') funciona sem precisar de nova migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `fila_impressao` MODIFY `tipo` VARCHAR(20) NOT NULL");
    }

    public function down(): void
    {
        // não reverte
    }
};
