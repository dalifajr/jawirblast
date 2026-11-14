<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'contact_id',
        'phone_number',
        'recipient_name',
        'message_text',
        'resolved_message',
        'baileys_msg_id',
        'wa_message_id',
        'status',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    // --- Accessor & Mutator Kompatibilitas Ganda ---

    public function getResolvedMessageAttribute(): string
    {
        return $this->attributes['message_text'] ?? '';
    }

    public function setResolvedMessageAttribute($value): void
    {
        $this->attributes['message_text'] = (string) $value;
    }

    public function getWaMessageIdAttribute(): ?string
    {
        return $this->attributes['baileys_msg_id'] ?? null;
    }

    public function setWaMessageIdAttribute($value): void
    {
        $this->attributes['baileys_msg_id'] = $value;
    }

    // --- Relasi Eloquent ---

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    // --- Status Helper Methods ---

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isRead(): bool
    {
        return $this->status === 'read';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Mengembalikan representasi simbol centang atau ikon status pengiriman.
     */
    public function getTickStateAttribute(): string
    {
        return match ($this->status) {
            'read' => 'blue_tick',       // Centang 2 Biru
            'delivered' => 'double_tick', // Centang 2 Abu-abu
            'sent' => 'single_tick',     // Centang 1 Abu-abu
            'failed' => 'failed',
            default => 'pending',
        };
    }

    // --- Query Scopes ---

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', 'delivered');
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->where('status', 'read');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeByBaileysMsgId(Builder $query, string $waMsgId): Builder
    {
        return $query->where('baileys_msg_id', $waMsgId);
    }
}
