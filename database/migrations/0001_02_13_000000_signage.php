<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Digital Signage (mídia indoor): telas (TV/quiosque) e as mídias/avisos que rodam em cada uma. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('signage_telas')) {
            Schema::create('signage_telas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('empresa_id')->index();
                $t->string('nome', 120);
                $t->string('local', 200)->nullable();       // onde fica o ponto (endereço/referência)
                $t->string('orientacao', 12)->default('horizontal'); // horizontal | vertical
                $t->string('token', 48)->unique();           // link público do player (TV Box / quiosque)
                $t->boolean('ativo')->default(true);
                $t->timestamp('visto_em')->nullable();       // última vez que o player buscou a programação
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('signage_midias')) {
            Schema::create('signage_midias', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('empresa_id')->index();
                $t->unsignedBigInteger('tela_id')->index();
                $t->string('tipo', 12);                      // imagem | video | texto
                $t->string('titulo', 120)->nullable();
                $t->text('texto')->nullable();
                $t->unsignedSmallInteger('layout')->default(1); // modelo do aviso (1..10)
                $t->string('arquivo', 255)->nullable();      // caminho público (storage/...) ou URL
                $t->string('audio', 255)->nullable();        // áudio opcional que toca junto
                $t->boolean('som')->default(false);          // vídeo com som
                $t->unsignedInteger('duracao')->default(10); // segundos na tela (vídeo: 0 = até o fim)
                $t->string('modo', 12)->default('sequencia'); // sequencia | intervalo | horario
                $t->unsignedInteger('intervalo_min')->nullable(); // modo intervalo: a cada X minutos
                $t->json('horarios')->nullable();            // modo horario: ["08:00","12:30"]
                $t->json('dias')->nullable();                // dias da semana (0=dom..6=sáb); vazio = todos
                $t->date('data_inicio')->nullable();
                $t->date('data_fim')->nullable();
                $t->unsignedInteger('ordem')->default(0);
                $t->boolean('ativo')->default(true);
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('signage_midias');
        Schema::dropIfExists('signage_telas');
    }
};
