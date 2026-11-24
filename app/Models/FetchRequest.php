<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class FetchRequest extends Model
{
    protected $fillable = [
        'item_name',
        'details',
        'budget',
        'delivery_location',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    protected function live(Builder $query): void
    {
        $now = now();

        $query->where(function (Builder $query) use ($now): void {
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
}
