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

#[Fillable(['name', 'email', 'password', 'telegram_id', 'telegram_username', 'locale', 'role', 'photo_url'])]
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
     * Cek apakah pengguna memiliki peran administrator.
     */
    public function isAdmin(): bool
    {
        return ($this->role ?? 'user') === 'admin';
    }
}
