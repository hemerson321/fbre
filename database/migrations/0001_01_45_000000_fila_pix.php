<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Adiciona o tipo 'pix' à fila de impressão (QR do PIX impresso pelo agente). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fila_impressao')) {
            try {
                DB::statement("ALTER TABLE `fila_impressao` MODIFY `tipo` ENUM('comprovante','etiqueta','protocolo','relatorio','pix') NOT NULL");
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        // não reverte (dados podem usar o novo tipo)
    }
};
