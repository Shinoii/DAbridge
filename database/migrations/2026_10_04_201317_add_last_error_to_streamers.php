<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('streamers', function (Blueprint $t) {
            $t->string('last_error')->nullable()->after('is_connected');
            $t->timestamp('last_error_at')->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('streamers', function (Blueprint $t) {
            $t->dropColumn(['last_error', 'last_error_at']);
        });
    }
};
