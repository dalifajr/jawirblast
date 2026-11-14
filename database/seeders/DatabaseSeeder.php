<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Superadmin: Dzulfikri Alifajri (Satu-satunya Superadmin sesuai arahan rencana pengembangan.txt)
        User::updateOrCreate(
            ['email' => 'dzulfikrialifajri@gmail.com'],
            [
                'name' => 'Dzulfikri Alifajri',
                'username' => 'dzulfikri',
                'role' => 'superadmin',
                'password' => bcrypt('password123'),
                'wallet_balance' => 2500000,
                'referral_balance' => 500000,
                'total_earnings' => 5000000,
                'max_daily_earnings' => 1250000,
                'is_suspended' => false,
            ]
        );

        // 2. Seed User: Administrator (Downgraded ke role 'user' biasa sesuai arahan rencana pengembangan.txt)
        User::updateOrCreate(
            ['email' => 'admin@jawirblast.com'],
            [
                'name' => 'Administrator',
                'username' => 'admin',
                'role' => 'user',
                'password' => bcrypt('password123'),
                'wallet_balance' => 500000,
                'referral_balance' => 100000,
                'is_suspended' => false,
            ]
        );
    }
}
