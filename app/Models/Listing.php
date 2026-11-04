<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Listing $listing): void {
            $listing->slug ??= Str::slug($listing->title).'-'.Str::lower(Str::random(6));
        });
    }
}
