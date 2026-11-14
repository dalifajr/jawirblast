<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 50)->unique();
            $table->string('user_jid', 50)->nullable();
            $table->enum('status', ['disconnected', 'connecting', 'connected'])->default('disconnected')->index();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
};
