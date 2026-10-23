<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * APIs de pagamento (Mercado Pago e futuros). Módulo central reutilizável:
 * cada módulo (encomenda, passagem, frete...) escolhe uma API e gera PIX dinâmico.
 * 'cobrancas' guarda cada PIX gerado e seu status (para o agente IA confirmar o pagamento).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pagamento_apis')) {
            Schema::create('pagamento_apis', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->string('nome', 80);
                $t->string('provider', 20)->default('mercadopago'); // mercadopago | ...
                $t->boolean('ativo')->default(true);
                $t->text('access_token')->nullable();   // secret
                $t->string('public_key', 255)->nullable();
                $t->string('webhook_secret', 255)->nullable();
                $t->json('config')->nullable();
                $t->timestamps();
                $t->index(['empresa_id', 'ativo']);
            });
        }

        if (! Schema::hasTable('cobrancas')) {
            Schema::create('cobrancas', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->foreignId('pagamento_api_id')->nullable()->constrained('pagamento_apis')->nullOnDelete();
                $t->string('modulo', 20)->nullable();          // passagem, encomenda...
                $t->string('referencia_tipo', 40)->nullable(); // App\Models\Passagem
                $t->unsignedBigInteger('referencia_id')->nullable();
                $t->string('provider', 20)->default('mercadopago');
                $t->string('provider_payment_id', 60)->nullable();
                $t->decimal('valor', 10, 2)->default(0);
                $t->string('status', 20)->default('pendente'); // pendente | pago | expirado | cancelado
                $t->text('qr_copia_cola')->nullable();         // PIX copia e cola
                $t->longText('qr_base64')->nullable();         // imagem do QR
                $t->string('ticket_url', 255)->nullable();
                $t->string('cliente_nome', 120)->nullable();
                $t->string('cliente_telefone', 30)->nullable();
                $t->timestamp('pago_em')->nullable();
                $t->timestamps();
                $t->index(['empresa_id', 'status']);
                $t->index('provider_payment_id');
                $t->index(['referencia_tipo', 'referencia_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas');
        Schema::dropIfExists('pagamento_apis');
    }
};
