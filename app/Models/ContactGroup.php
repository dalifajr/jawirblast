<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class ContactGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (ContactGroup $group) {
            if (empty($group->slug) && !empty($group->name)) {
                $group->slug = Str::slug($group->name);
            }
        });
    }

    /**
     * Relasi ke pengguna pemilik grup kontak.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi many-to-many ke kontak yang tergabung dalam grup ini.
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_group_pivot', 'group_id', 'contact_id');
    }
}
