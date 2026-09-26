<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchReopenLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'user_id',
        'previous_status',
        'previous_state',
        'events_deleted',
        'suspensions_deleted',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'previous_state' => 'array',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
