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
        Schema::create('streamers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('da_user_id')->unique();
            $t->string('name');
            $t->string('avatar')->nullable();
            $t->text('access_token');
            $t->text('refresh_token')->nullable();
            $t->timestamp('token_expires_at')->nullable();
            $t->string('widget_token', 64)->unique();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_connected')->default(false);
            $t->unsignedInteger('donations_count')->default(0);
            $t->timestamp('last_connected_at')->nullable();
            $t->timestamp('last_donation_at')->nullable();
            $t->timestamps();
        });

        Schema::create('donations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('streamer_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('da_id');
            $t->string('username')->nullable();
            $t->text('message')->nullable();
            $t->decimal('amount', 12, 2);
            $t->string('currency', 8);
            $t->json('raw')->nullable();
            $t->timestamps();
            $t->unique(['streamer_id', 'da_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
        Schema::dropIfExists('streamers');
    }
};
