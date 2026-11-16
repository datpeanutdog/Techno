<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reporter_id',
    'reported_user_id',
    'category',
    'details',
    'proof_paths',
    'status',
    'admin_note',
    'reviewed_by',
    'resolved_at',
])]
#[Hidden(['proof_paths'])]
class SupportReport extends Model
{
    public const CATEGORIES = [
        'inappropriate_content' => 'Inappropriate content',
        'scam' => 'Scam or fraud',
        'harassment' => 'Harassment or bullying',
        'other' => 'Other',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proof_paths' => 'array',
            'resolved_at' => 'datetime',
        ];
    }
}
