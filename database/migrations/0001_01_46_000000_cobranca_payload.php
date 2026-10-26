<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda o pedido (encomenda/passagem) na própria cobrança para que o SERVIDOR
 * crie o registro assim que o PIX confirmar — a tela só redireciona, sem depender
 * de o navegador salvar depois do pagamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobrancas', function (Blueprint $t) {
            if (! Schema::hasColumn('cobrancas', 'user_id')) {
                $t->unsignedBigInteger('user_id')->nullable()->after('pagamento_api_id');
            }
            if (! Schema::hasColumn('cobrancas', 'payload')) {
                $t->longText('payload')->nullable()->after('referencia_id'); // JSON do pedido a criar
            }
            if (! Schema::hasColumn('cobrancas', 'imprimir')) {
                $t->string('imprimir', 20)->nullable()->after('payload'); // direto|navegador|pix|nao
            }
            if (! Schema::hasColumn('cobrancas', 'erro')) {
                $t->string('erro', 300)->nullable()->after('imprimir'); // falha ao criar pós-pagamento
            }
        });
    }

    public function down(): void
    {
        Schema::table('cobrancas', function (Blueprint $t) {
            foreach (['user_id', 'payload', 'imprimir', 'erro'] as $c) {
                if (Schema::hasColumn('cobrancas', $c)) $t->dropColumn($c);
            }
        });
    }
};
