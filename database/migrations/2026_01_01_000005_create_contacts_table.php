<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('name');
            $table->string('phone_number', 25)->index();
            $table->string('raw_phone', 35)->nullable();
            $table->json('custom_fields')->nullable();
            $table->enum('status', ['active', 'invalid', 'unsubscribed'])->default('active')->index();
            $table->timestamps();

            $table->index(['user_id', 'phone_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
