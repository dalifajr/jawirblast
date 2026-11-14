<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ResetAdminPasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jawirblast:reset-admin {email? : Alamat email admin} {password? : Password baru} {--role= : Role pengguna (superadmin/admin/user)} {--username= : Username akun}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset password admin atau buat akun admin/superadmin baru dari terminal';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('Masukkan alamat email admin:');
        $email = trim((string) $email);

        $validator = Validator::make(['email' => $email], [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            $this->error('Alamat email tidak valid: ' . $email);
            return self::FAILURE;
        }

        $password = $this->argument('password');
        if (!$password) {
            $password = $this->secret('Masukkan password baru (minimal 8 karakter):');
        }

        if (empty($password) || strlen($password) < 8) {
            $this->error('Password harus memiliki panjang minimal 8 karakter.');
            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        // Default role: jika email dzulfikri set superadmin
        $isDzulfikri = str_contains(strtolower($email), 'dzulfikri');
        $defaultRole = $isDzulfikri ? 'superadmin' : 'admin';
        $role = $this->option('role') ?: $defaultRole;

        $defaultUsername = $isDzulfikri ? 'dzulfikri' : explode('@', $email)[0];
        $username = $this->option('username') ?: $defaultUsername;

        if ($user) {
            $user->password = Hash::make($password);
            if ($role) {
                $user->role = $role;
            }
            if (empty($user->username) || $isDzulfikri) {
                $user->username = strtolower($username);
            }
            $user->is_suspended = false;
            $user->save();

            $this->info("Password untuk admin [{$email}] berhasil direset.");
        } else {
            $name = $this->ask('Akun belum ditemukan. Masukkan nama untuk akun admin baru:', $isDzulfikri ? 'Dzulfikri Alifajri' : 'Administrator');

            $user = User::create([
                'name' => trim((string) $name),
                'username' => strtolower($username),
                'email' => $email,
                'role' => $role,
                'password' => Hash::make($password),
                'locale' => 'id',
                'is_suspended' => false,
            ]);

            $this->info("Akun admin baru [{$email}] berhasil dibuat dengan ID: {$user->id}.");
        }

        return self::SUCCESS;
    }
}
