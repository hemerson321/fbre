<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conexões de WhatsApp (oficial = Meta Cloud API; não oficial = Evolution API e afins).
 * Módulo central reutilizável: cada serviço (encomenda, passagem, RIONAVE...) escolhe uma conexão.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_conexoes')) {
            Schema::create('whatsapp_conexoes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $t->string('nome', 80);
                $t->string('tipo', 20)->default('nao_oficial'); // nao_oficial | oficial
                $t->boolean('ativo')->default(true);
                // não oficial (Evolution API e similares)
                $t->string('base_url', 255)->nullable();
                $t->string('instancia', 120)->nullable();
                $t->string('api_key', 255)->nullable();
                // oficial (Meta WhatsApp Cloud API)
                $t->string('phone_number_id', 60)->nullable();
                $t->text('token')->nullable();              // access token
                $t->string('business_id', 60)->nullable();
                $t->string('api_version', 12)->default('v21.0');
                $t->timestamps();
                $t->index(['empresa_id', 'ativo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conexoes');
    }
};
