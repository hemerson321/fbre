<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (! Schema::hasColumn('embarcacoes', 'dono_nome')) {
                $t->string('dono_nome', 120)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('embarcacoes', function (Blueprint $t) {
            if (Schema::hasColumn('embarcacoes', 'dono_nome')) {
                $t->dropColumn('dono_nome');
            }
        });
    }
};
