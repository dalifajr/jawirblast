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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->after('password');
            $table->string('username', 60)->nullable()->unique()->after('name');
            $table->string('avatar')->nullable()->after('username');
            $table->decimal('wallet_balance', 15, 2)->default(0.00)->after('locale');
            $table->decimal('referral_balance', 15, 2)->default(0.00)->after('wallet_balance');
            $table->decimal('total_earnings', 15, 2)->default(0.00)->after('referral_balance');
            $table->decimal('max_daily_earnings', 15, 2)->default(0.00)->after('total_earnings');
            $table->boolean('is_suspended')->default(false)->after('max_daily_earnings');
            $table->text('suspended_reason')->nullable()->after('is_suspended');
            $table->timestamp('last_active_at')->nullable()->after('suspended_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'username',
                'avatar',
                'wallet_balance',
                'referral_balance',
                'total_earnings',
                'max_daily_earnings',
                'is_suspended',
                'suspended_reason',
                'last_active_at',
            ]);
        });
    }
};
