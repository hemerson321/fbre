<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Adiciona o tipo 'passagem' à fila de impressão (bilhete por passageiro no agente). */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `fila_impressao` MODIFY `tipo` ENUM('comprovante','etiqueta','protocolo','relatorio','pix','passagem') NOT NULL");
    }

    public function down(): void
    {
        // não reverte (dados podem usar o novo tipo)
    }
};
