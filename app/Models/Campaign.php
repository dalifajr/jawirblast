<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'whats_app_session_id',
        'title',
        'name',
        'message_template',
        'media_path',
        'status',
        'min_delay_seconds',
        'max_delay_seconds',
        'total_contacts',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
        'scheduled_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'min_delay_seconds' => 'integer',
            'max_delay_seconds' => 'integer',
            'total_contacts' => 'integer',
            'sent_count' => 'integer',
            'delivered_count' => 'integer',
            'read_count' => 'integer',
            'failed_count' => 'integer',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // --- Accessor & Mutator Kompatibilitas Ganda ---

    public function getNameAttribute(): string
    {
        return $this->attributes['title'] ?? '';
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['title'] = (string) $value;
    }

    public function getTotalRecipientsAttribute(): int
    {
        return (int) ($this->attributes['total_contacts'] ?? 0);
    }

    public function setTotalRecipientsAttribute($value): void
    {
        $this->attributes['total_contacts'] = (int) $value;
    }

    public function setStatusAttribute($value): void
    {
        $val = strtolower((string) $value);
        if ($val === 'canceled') {
            $val = 'cancelled';
        }
        $this->attributes['status'] = $val;
    }

    // --- Relasi Eloquent ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function whatsAppSession(): BelongsTo
    {
        return $this->belongsTo(WhatsAppSession::class, 'whats_app_session_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class);
    }

    // --- Status Helper Methods ---

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancelled', 'canceled']);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function canBeDispatched(): bool
    {
        return in_array($this->status, ['draft', 'paused', 'scheduled']);
    }

    public function canBePaused(): bool
    {
        return $this->status === 'processing';
    }

    public function canBeResumed(): bool
    {
        return $this->status === 'paused';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'scheduled', 'processing', 'paused']);
    }

    // --- Kalkulator Metrik Real-Time ---

    public function getProgressPercentAttribute(): int
    {
        $total = $this->total_contacts;
        if ($total <= 0) {
            return 0;
        }
        $processed = $this->sent_count + $this->failed_count;
        return (int) min(100, round(($processed / $total) * 100));
    }

    // --- Query Scopes ---

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    public function scopePaused(Builder $query): Builder
    {
        return $query->where('status', 'paused');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }
        $term = trim($term);
        return $query->where('title', 'like', "%{$term}%");
    }
}
