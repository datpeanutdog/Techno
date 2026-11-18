<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Listing extends Model
{
    protected $hidden = ['user_id'];

    protected $fillable = [
        'title',
        'slug',
        'description',
        'price',
        'type',
        'rent_period',
        'category',
        'location',
        'image_url',
        'image_path',
        'seller_name',
        'seller_initials',
        'seller_rating',
        'condition',
        'featured',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'seller_rating' => 'decimal:1',
            'featured' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * An uploaded photo wins; otherwise fall back to the pasted link.
     */
    public function getImageUrlAttribute(?string $value): ?string
    {
        if ($this->image_path) {
            return request()->getSchemeAndHttpHost().'/storage/'.ltrim($this->image_path, '/');
        }

        return $value;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Listing $listing): void {
            $listing->slug ??= Str::slug($listing->title).'-'.Str::lower(Str::random(6));
        });

        static::deleting(function (Listing $listing): void {
            if ($listing->image_path) {
                Storage::disk('public')->delete($listing->image_path);
            }
        });
    }
}