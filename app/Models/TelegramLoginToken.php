<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramLoginToken extends Model
{
    use HasFactory;

    protected $table = 'telegram_login_tokens';

    protected $fillable = [
        'token',
        'telegram_id',
        'status',
        'expires_at',
    ];

    /**
     * Property virtual penampung atribut pembantu pengujian.
     */
    public ?int $user_id = null;
    public ?string $telegram_username = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'telegram_id' => 'integer',
        ];
    }

    /**
     * Relasi ke model User (opsional jika token ditautkan ke akun tertentu).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope token berstatus pending dan belum kedaluwarsa.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '>', now());
    }

    /**
     * Periksa apakah token sudah kedaluwarsa.
     */
    public function isExpired(): bool
    {
        return $this->expires_at ? now()->greaterThan($this->expires_at) : false;
    }

    /**
     * Periksa apakah token berstatus verified.
     */
    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    /**
     * Periksa apakah token berstatus pending dan masih aktif.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }
}
