<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Caixa geral unificado: o pagamento deixa de ser só de encomenda.
 * `modulo` diz de qual módulo veio (encomenda/passagem/frete/camarote/lanchonete/wifi/avulso/recibo)
 * e `origem_id` aponta para o registro daquele módulo.
 * `tipo_mov` = entrada|saida (saída = sangria/despesa no avulso).
 * Encomendas antigas continuam idênticas: modulo='encomenda', origem_id=encomenda_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagamentos', function (Blueprint $t) {
            if (! Schema::hasColumn('pagamentos', 'modulo')) {
                $t->string('modulo', 20)->default('encomenda')->index();
            }
            if (! Schema::hasColumn('pagamentos', 'origem_id')) {
                $t->unsignedBigInteger('origem_id')->nullable()->index();
            }
            if (! Schema::hasColumn('pagamentos', 'tipo_mov')) {
                $t->string('tipo_mov', 10)->default('entrada');
            }
            if (! Schema::hasColumn('pagamentos', 'descricao')) {
                $t->string('descricao', 200)->nullable();
            }
        });

        // encomenda_id precisa ser nullable (pagamentos de outros módulos não têm encomenda)
        try {
            DB::statement('ALTER TABLE `pagamentos` DROP FOREIGN KEY `pagamentos_encomenda_id_foreign`');
        } catch (\Throwable $e) {
        }
        try {
            DB::statement('ALTER TABLE `pagamentos` MODIFY `encomenda_id` BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
        }
        try {
            DB::statement('ALTER TABLE `pagamentos` ADD CONSTRAINT `pagamentos_encomenda_id_foreign` FOREIGN KEY (`encomenda_id`) REFERENCES `encomendas` (`id`) ON DELETE CASCADE');
        } catch (\Throwable $e) {
        }

        // backfill: todo pagamento existente é de encomenda
        if (Schema::hasColumn('pagamentos', 'encomenda_id')) {
            DB::statement("UPDATE pagamentos SET origem_id = encomenda_id WHERE origem_id IS NULL AND encomenda_id IS NOT NULL");
            DB::statement("UPDATE pagamentos SET modulo = 'encomenda' WHERE modulo IS NULL OR modulo = ''");
        }
    }

    public function down(): void
    {
        Schema::table('pagamentos', function (Blueprint $t) {
            foreach (['modulo', 'origem_id', 'tipo_mov', 'descricao'] as $c) {
                if (Schema::hasColumn('pagamentos', $c)) $t->dropColumn($c);
            }
        });
    }
};
