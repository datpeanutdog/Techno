<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'bio', 'campus', 'avatar_path'])]
#[Hidden(['password', 'remember_token', 'avatar_path'])]
class User extends Authenticatable implements MustVerifyEmail
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_USER = 'user';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $appends = ['avatar_url'];

    protected $attributes = [
        'role' => self::ROLE_USER,
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if ($user->email && in_array(Str::lower($user->email), config('app.admin_emails', []), true)) {
                $user->role = self::ROLE_ADMIN;
            }
        });
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function conversationsAsBuyer(): HasMany
    {
        return $this->hasMany(Conversation::class, 'buyer_id');
    }

    public function conversationsAsSeller(): HasMany
    {
        return $this->hasMany(Conversation::class, 'seller_id');
    }

    public function receivedReviews(): HasMany
    {
        return $this->hasMany(SellerReview::class, 'seller_id');
    }

    public function writtenReviews(): HasMany
    {
        return $this->hasMany(SellerReview::class, 'reviewer_id');
    }

    public function supportReportsFiled(): HasMany
    {
        return $this->hasMany(SupportReport::class, 'reporter_id');
    }

    public function supportReportsReceived(): HasMany
    {
        return $this->hasMany(SupportReport::class, 'reported_user_id');
    }

    public function suspensionAppeals(): HasMany
    {
        return $this->hasMany(SuspensionAppeal::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function getAccountIdentifierAttribute(): string
    {
        return 'ACC-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return Str::startsWith($this->avatar_path, ['http://', 'https://'])
            ? $this->avatar_path
            : request()->getSchemeAndHttpHost().'/storage/'.ltrim($this->avatar_path, '/');
    }

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
            'suspended_at' => 'datetime',
        ];
    }
}
