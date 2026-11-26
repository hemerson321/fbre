<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Áudio indoor (áudio terminal): terminais de reprodução, áudios (fala/TTS ou arquivo),
 * programação (dia da semana + horário) e disparos ("tocar agora").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketing_terminais')) {
            Schema::create('marketing_terminais', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('empresa_id')->index();
                $t->string('nome', 120);
                $t->string('local', 200)->nullable();   // onde fica a caixa de som / ponto
                $t->string('token', 48)->unique();       // link público do player (tablet/PC com som)
                $t->boolean('ativo')->default(true);
                $t->timestamp('visto_em')->nullable();   // última vez que o terminal buscou a fila
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('marketing_audios')) {
            Schema::create('marketing_audios', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('empresa_id')->index();
                $t->string('nome', 140);
                $t->string('tipo', 12)->default('tts');  // tts (fala) | arquivo
                $t->text('texto')->nullable();           // tts: o que vai ser falado
                $t->string('voz', 60)->nullable();       // tts: dica de voz (opcional)
                $t->string('arquivo', 255)->nullable();  // arquivo: caminho público (storage/...) ou URL
                $t->boolean('ativo')->default(true);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('marketing_programacoes')) {
            Schema::create('marketing_programacoes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('empresa_id')->index();
                $t->unsignedBigInteger('audio_id')->index();
                $t->unsignedBigInteger('terminal_id')->nullable()->index(); // null = todos os terminais
                $t->string('hora', 5);                   // "HH:MM"
                $t->json('dias')->nullable();            // 0=dom..6=sáb; vazio/null = todos os dias
                $t->boolean('ativo')->default(true);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('marketing_disparos')) {
            Schema::create('marketing_disparos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('empresa_id')->index();
                $t->unsignedBigInteger('audio_id')->index();
                $t->unsignedBigInteger('terminal_id')->nullable()->index(); // null = todos
                $t->unsignedBigInteger('user_id')->nullable();
                $t->timestamps();                        // created_at = quando tocar agora foi acionado
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_disparos');
        Schema::dropIfExists('marketing_programacoes');
        Schema::dropIfExists('marketing_audios');
        Schema::dropIfExists('marketing_terminais');
    }
};
