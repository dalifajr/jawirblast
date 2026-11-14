<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_login_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('telegram_id')->nullable();
            $table->enum('status', ['pending', 'verified', 'used', 'expired'])->default('pending')->index();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_login_tokens');
    }
};
