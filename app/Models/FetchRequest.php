<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class FetchRequest extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'item_name',
        'details',
        'budget',
        'delivery_location',
        'expires_at',
        'delivery_deadline',
        'status',
        'accepted_by_id',
        'accepted_at',
        'delivered_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'expires_at' => 'datetime',
            'delivery_deadline' => 'datetime',
            'accepted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_id');
    }

    #[Scope]
    protected function live(Builder $query): void
    {
        $now = now();

        $query->where('status', self::STATUS_OPEN)
            ->where(function (Builder $query) use ($now): void {
                $query->where('expires_at', '>', $now)
                    ->orWhere(function (Builder $query) use ($now): void {
                        $query->whereNull('expires_at')
                            ->where('created_at', '>', $now->copy()->subDay());
                    });
            });
    }

    public function effectiveExpiresAt(): Carbon
    {
        return $this->expires_at ?? $this->created_at->copy()->addDay();
    }

    public function effectiveDeliveryDeadline(): Carbon
    {
        return $this->delivery_deadline ?? $this->effectiveExpiresAt();
    }
}
