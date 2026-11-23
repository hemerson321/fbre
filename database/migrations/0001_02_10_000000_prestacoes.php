<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prestacoes')) {
            Schema::create('prestacoes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('empresa_id')->index();
                $t->unsignedBigInteger('embarcacao_id')->nullable()->index();
                $t->unsignedBigInteger('viagem_id')->nullable()->index();
                $t->date('data_viagem')->nullable()->index();
                $t->unsignedBigInteger('revendedor_id')->nullable()->index(); // null = balcão (empresa)
                $t->string('tipo', 10)->default('balcao'); // balcao|agente
                $t->string('lote', 40)->index();
                $t->unsignedInteger('numero')->default(0)->index();
                $t->unsignedInteger('qtd')->default(0);
                $t->decimal('total_bruto', 12, 2)->default(0);
                $t->decimal('comissao_pct', 5, 2)->default(0);
                $t->decimal('comissao', 12, 2)->default(0);
                $t->decimal('total_devido', 12, 2)->default(0);
                $t->decimal('valor_pago', 12, 2)->default(0);
                $t->decimal('saldo', 12, 2)->default(0);
                $t->string('forma', 20)->nullable();
                $t->string('status', 12)->default('pendente'); // pendente|parcial|acertada
                $t->string('obs', 255)->nullable();
                $t->timestamp('fechada_em')->nullable();
                $t->unsignedBigInteger('fechada_por')->nullable();
                $t->boolean('desfeita')->default(false)->index();
                $t->timestamp('desfeita_em')->nullable();
                $t->unsignedBigInteger('desfeita_por')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasColumn('passagens', 'prestacao_id')) {
            Schema::table('passagens', fn (Blueprint $t) => $t->unsignedBigInteger('prestacao_id')->nullable()->index());
        }
        if (! Schema::hasColumn('pagamentos', 'prestacao_id')) {
            Schema::table('pagamentos', fn (Blueprint $t) => $t->unsignedBigInteger('prestacao_id')->nullable()->index());
        }
        if (! Schema::hasColumn('pagamentos', 'viagem_id')) {
            Schema::table('pagamentos', fn (Blueprint $t) => $t->unsignedBigInteger('viagem_id')->nullable()->index());
        }
        if (! Schema::hasColumn('pagamentos', 'data_viagem')) {
            Schema::table('pagamentos', fn (Blueprint $t) => $t->date('data_viagem')->nullable()->index());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('passagens', 'prestacao_id')) {
            Schema::table('passagens', fn (Blueprint $t) => $t->dropColumn('prestacao_id'));
        }
        if (Schema::hasColumn('pagamentos', 'prestacao_id')) {
            Schema::table('pagamentos', fn (Blueprint $t) => $t->dropColumn('prestacao_id'));
        }
        Schema::dropIfExists('prestacoes');
    }
};
