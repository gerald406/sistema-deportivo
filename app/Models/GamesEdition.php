<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamesEdition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'host_venue_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function hostVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'host_venue_id');
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    public function medals(): HasMany
    {
        return $this->hasMany(GamesEditionMedal::class);
    }
}
