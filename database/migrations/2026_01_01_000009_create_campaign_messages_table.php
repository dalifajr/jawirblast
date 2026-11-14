<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete()->cascadeOnUpdate();
            $table->string('phone_number', 25)->index();
            $table->string('recipient_name', 100)->nullable();
            $table->text('message_text');
            $table->string('baileys_msg_id', 100)->nullable()->index();
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_messages');
    }
};
