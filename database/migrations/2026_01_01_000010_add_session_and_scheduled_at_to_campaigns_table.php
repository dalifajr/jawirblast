<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('campaigns', 'whats_app_session_id')) {
                $table->foreignId('whats_app_session_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('whatsapp_sessions')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }
            if (!Schema::hasColumn('campaigns', 'scheduled_at')) {
                $table->timestamp('scheduled_at')
                    ->nullable()
                    ->after('status')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'whats_app_session_id')) {
                $table->dropForeign(['whats_app_session_id']);
                $table->dropColumn('whats_app_session_id');
            }
            if (Schema::hasColumn('campaigns', 'scheduled_at')) {
                $table->dropColumn('scheduled_at');
            }
        });
    }
};
