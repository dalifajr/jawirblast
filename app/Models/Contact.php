<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'phone_number',
        'raw_phone',
        'custom_fields',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
        ];
    }

    /**
     * Relasi ke pengguna pemilik kontak.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi many-to-many ke grup kontak via tabel pivot contact_group_pivot.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class, 'contact_group_pivot', 'contact_id', 'group_id');
    }

    /**
     * Relasi ke pesan kampanye yang ditujukan ke kontak ini.
     */
    public function campaignMessages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class);
    }

    /**
     * Scope untuk menyaring kontak berstatus aktif.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope untuk menyaring kontak berstatus invalid.
     */
    public function scopeInvalid(Builder $query): Builder
    {
        return $query->where('status', 'invalid');
    }

    /**
     * Scope pencarian kontak berdasarkan nama atau nomor telepon.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('phone_number', 'like', "%{$term}%")
              ->orWhere('raw_phone', 'like', "%{$term}%");
        });
    }
}
