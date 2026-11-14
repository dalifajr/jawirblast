<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'username',
    'avatar',
    'email',
    'password',
    'telegram_id',
    'telegram_username',
    'locale',
    'role',
    'photo_url',
    'wallet_balance',
    'referral_balance',
    'total_earnings',
    'max_daily_earnings',
    'is_suspended',
    'suspended_reason',
    'last_active_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'telegram_id' => 'integer',
            'wallet_balance' => 'decimal:2',
            'referral_balance' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'max_daily_earnings' => 'decimal:2',
            'is_suspended' => 'boolean',
            'last_active_at' => 'datetime',
        ];
    }

    /**
     * Relasi ke token login Telegram pengguna.
     */
    public function telegramLoginTokens(): HasMany
    {
        return $this->hasMany(TelegramLoginToken::class, 'telegram_id', 'telegram_id');
    }

    /**
     * Relasi ke riwayat penarikan dana pengguna.
     */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class)->latest();
    }

    /**
     * Relasi ke daftar kontak milik pengguna.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Relasi ke daftar grup kontak milik pengguna.
     */
    public function contactGroups(): HasMany
    {
        return $this->hasMany(ContactGroup::class);
    }

    /**
     * Relasi ke daftar kampanye milik pengguna.
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Cek apakah pengguna memiliki peran superadmin.
     */
    public function isSuperAdmin(): bool
    {
        return ($this->role ?? 'user') === 'superadmin';
    }

    /**
     * Cek apakah pengguna memiliki peran administrator atau superadmin.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role ?? 'user', ['admin', 'superadmin'], true);
    }

    /**
     * Cek apakah akun pengguna dalam status dibatasi/suspended.
     */
    public function isSuspended(): bool
    {
        return (bool) ($this->is_suspended ?? false);
    }

    /**
     * Lama waktu bergabung dalam format teks yang ramah manusia.
     */
    public function getTimeJoinedAttribute(): string
    {
        return $this->created_at ? $this->created_at->diffForHumans() : '-';
    }
}
