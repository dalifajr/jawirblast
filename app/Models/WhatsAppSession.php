<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppSession extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_sessions';

    protected $fillable = [
        'session_id',
        'user_jid',
        'status',
        'last_connected_at',
    ];

    protected $casts = [
        'last_connected_at' => 'datetime',
    ];

    /**
     * Relasi ke kampanye yang menggunakan sesi WhatsApp ini.
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'whats_app_session_id');
    }

    /**
     * Mengecek apakah sesi dalam status terhubung (connected).
     */
    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    /**
     * Mengecek apakah sesi sedang dalam proses menghubungkan (connecting).
     */
    public function isConnecting(): bool
    {
        return $this->status === 'connecting';
    }

    /**
     * Mengecek apakah sesi dalam status terputus (disconnected).
     */
    public function isDisconnected(): bool
    {
        return $this->status === 'disconnected';
    }

    /**
     * Accessor untuk mengekstrak nomor telepon dari user_jid (misal: 6281234567890:1@s.whatsapp.net -> 6281234567890).
     */
    public function getPhoneNumberAttribute(): ?string
    {
        if (empty($this->user_jid)) {
            return null;
        }

        $jidPart = explode('@', $this->user_jid)[0];
        return explode(':', $jidPart)[0];
    }
}
