<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('title');
            $table->longText('message_template');
            $table->string('media_path', 500)->nullable();
            $table->enum('status', ['draft', 'scheduled', 'processing', 'completed', 'paused', 'cancelled', 'failed'])->default('draft')->index();
            $table->unsignedInteger('min_delay_seconds')->default(2);
            $table->unsignedInteger('max_delay_seconds')->default(5);
            $table->unsignedInteger('total_contacts')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
