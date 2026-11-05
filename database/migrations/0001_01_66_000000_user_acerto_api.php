<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'acerto_pix_api_id')) {
                $t->unsignedBigInteger('acerto_pix_api_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'acerto_pix_api_id')) {
                $t->dropColumn('acerto_pix_api_id');
            }
        });
    }
};
