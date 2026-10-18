<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('login', 40)->nullable()->after('name');
            $table->string('email')->nullable()->change();
        });

        // preenche login a partir do e-mail existente
        foreach (DB::table('users')->whereNull('login')->get(['id', 'email']) as $u) {
            $base = strtolower(preg_replace('/[^a-z0-9._-]/i', '', strstr($u->email ?? "user{$u->id}", '@', true) ?: "user{$u->id}"));
            $login = $base; $i = 1;
            while (DB::table('users')->where('login', $login)->exists()) { $login = $base.(++$i); }
            DB::table('users')->where('id', $u->id)->update(['login' => $login]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('login', 40)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['login']);
            $table->dropColumn('login');
        });
    }
};
