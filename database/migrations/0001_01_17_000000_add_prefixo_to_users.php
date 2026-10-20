<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prefixo (código) do operador + contador próprio de sequência.
 * O número da encomenda nasce no aparelho como PREFIXO-0001 (definitivo),
 * então cada operador numera sozinho (offline inclusive) sem colidir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'prefixo')) {
                $t->string('prefixo', 8)->nullable()->after('login');
            }
            if (! Schema::hasColumn('users', 'proximo_seq')) {
                $t->unsignedInteger('proximo_seq')->default(1)->after('prefixo');
            }
        });

        // backfill: quem não tem prefixo ganha um único (OP<id>); o admin troca por RE/AG depois.
        foreach (DB::table('users')->whereNull('prefixo')->orWhere('prefixo', '')->get(['id']) as $u) {
            DB::table('users')->where('id', $u->id)->update(['prefixo' => 'OP' . $u->id]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            foreach (['prefixo', 'proximo_seq'] as $c) {
                if (Schema::hasColumn('users', $c)) $t->dropColumn($c);
            }
        });
    }
};
